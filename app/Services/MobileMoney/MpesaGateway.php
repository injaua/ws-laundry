<?php

namespace App\Services\MobileMoney;

use App\Models\MobileTransaction;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Vodacom Mozambique M-Pesa OpenAPI (ipg/v1x).
 *
 * Authentication: the API key is encrypted with the public key from the developer
 * portal (RSA, PKCS#1) and sent as a Bearer token. Hosts and ports follow the
 * portal's defaults and can be overridden in config/mobile_money.php.
 */
class MpesaGateway implements MobileMoneyGateway
{
    /** Answer codes that mean "no final answer yet": ask again later. */
    private const PENDING_CODES = ["INS-9", "INS-13", "INS-993"];

    public function __construct(private array $settings) {}

    public function collect(MobileTransaction $tx): GatewayResult
    {
        return $this->call("post", "c2b", [
            "input_TransactionReference" => $tx->reference,
            "input_CustomerMSISDN" => $tx->phone,
            "input_Amount" => $this->amount($tx),
            "input_ThirdPartyReference" => $tx->reference,
            "input_ServiceProviderCode" => $this->settings["service_provider_code"],
        ]);
    }

    public function refund(MobileTransaction $tx): GatewayResult
    {
        return $this->call("post", "b2c", [
            "input_TransactionReference" => $tx->reference,
            "input_CustomerMSISDN" => $tx->phone,
            "input_Amount" => $this->amount($tx),
            "input_ThirdPartyReference" => $tx->reference,
            "input_ServiceProviderCode" => $this->settings["service_provider_code"],
        ]);
    }

    public function status(MobileTransaction $tx): GatewayResult
    {
        $result = $this->call("get", "query", [
            "input_ThirdPartyReference" => $tx->reference,
            "input_QueryReference" => $tx->provider_ref ?: $tx->reference,
            "input_ServiceProviderCode" => $this->settings["service_provider_code"],
        ]);

        if ($result->status !== GatewayResult::SUCCESS) {
            return $result;
        }

        // The query succeeds as a call; the transaction state is in its own field.
        $state = strtolower((string) ($result->raw["output_ResponseTransactionStatus"] ?? ""));

        return match ($state) {
            "completed" => GatewayResult::success($result->raw["output_TransactionID"] ?? $tx->provider_ref, $result->conversationId, $result->code, $state, $result->raw),
            "cancelled", "expired", "failed" => GatewayResult::failed("STATUS-" . strtoupper($state), $state, $result->raw),
            default => GatewayResult::pending($result->code, $state ?: null, $result->raw),
        };
    }

    private function call(string $verb, string $operation, array $payload): GatewayResult
    {
        try {
            $token = $this->bearerToken();
        } catch (RuntimeException $e) {
            // Bad credentials: nothing was sent, so the request can fail straight away.
            return GatewayResult::failed("CONFIG", $e->getMessage());
        }

        try {
            $request = Http::withHeaders([
                "Authorization" => "Bearer " . $token,
                "Origin" => $this->settings["origin"] ?? "developer.mpesa.vm.co.mz",
                "Accept" => "application/json",
            ])->timeout((int) ($this->settings["timeout"] ?? 120));

            $response = $verb === "get"
                ? $request->get($this->url($operation), $payload)
                : $request->asJson()->post($this->url($operation), $payload);
        } catch (Throwable $e) {
            // No answer (network / timeout): the customer may still have paid, so ask later.
            return GatewayResult::pending("NETWORK", $e->getMessage());
        }

        $body = $response->json() ?? [];
        $code = (string) ($body["output_ResponseCode"] ?? "HTTP-" . $response->status());
        $message = $body["output_ResponseDesc"] ?? $response->reason();

        if ($code === "INS-0") {
            return GatewayResult::success($body["output_TransactionID"] ?? null, $body["output_ConversationID"] ?? null, $code, $message, $body);
        }

        if (in_array($code, self::PENDING_CODES, true) || $response->serverError()) {
            return GatewayResult::pending($code, $message, $body, $body["output_ConversationID"] ?? null);
        }

        return GatewayResult::failed($code, $message, $body);
    }

    private function url(string $operation): string
    {
        $config = config("mobile_money.mpesa");
        $host = ($this->settings["environment"] ?? "sandbox") === "live" ? $config["live_host"] : $config["sandbox_host"];

        return "https://{$host}:{$config["ports"][$operation]}{$config["paths"][$operation]}";
    }

    private function bearerToken(): string
    {
        $key = trim((string) ($this->settings["public_key"] ?? ""));
        if (!str_contains($key, "BEGIN PUBLIC KEY")) {
            $key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(preg_replace("/\s+/", "", $key), 64, "\n") . "-----END PUBLIC KEY-----\n";
        }

        $publicKey = openssl_pkey_get_public($key);
        if ($publicKey === false || !openssl_public_encrypt((string) $this->settings["api_key"], $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException(__("app.mm_invalid_public_key"));
        }

        return base64_encode($encrypted);
    }

    /** M-Pesa takes whole meticais as a string. */
    private function amount(MobileTransaction $tx): string
    {
        return (string) (int) round((float) $tx->amount);
    }
}
