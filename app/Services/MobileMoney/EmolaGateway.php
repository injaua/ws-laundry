<?php

namespace App\Services\MobileMoney;

use App\Models\MobileTransaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Movitel e-Mola merchant API.
 *
 * Movitel hands the API specification to merchants with the contract, so the
 * endpoints, the request field names and the answer codes are all set in
 * config/mobile_money.php ("emola") instead of being fixed here. Check them against
 * the documentation received from Movitel and the sandbox before going live.
 */
class EmolaGateway implements MobileMoneyGateway
{
    public function __construct(private array $settings) {}

    public function collect(MobileTransaction $tx): GatewayResult
    {
        return $this->call("c2b", $tx);
    }

    public function refund(MobileTransaction $tx): GatewayResult
    {
        return $this->call("b2c", $tx);
    }

    public function status(MobileTransaction $tx): GatewayResult
    {
        return $this->call("query", $tx);
    }

    private function call(string $operation, MobileTransaction $tx): GatewayResult
    {
        $config = config("mobile_money.emola");
        $values = [
            "partner_code" => $this->settings["partner_code"] ?? "",
            "reference" => $tx->reference,
            "provider_ref" => $tx->provider_ref ?? "",
            "phone" => $tx->phone,
            "phone_local" => substr($tx->phone, 3),
            "amount" => (string) (int) round((float) $tx->amount),
            "description" => __("app.mm_payment_description", ["ref" => $tx->reference]),
        ];

        // Request body: operator field name => our value.
        $payload = [];
        foreach ($config["fields"][$operation] ?? [] as $field => $source) {
            $payload[$field] = $values[$source] ?? $source;
        }

        $base = rtrim((string) (($this->settings["environment"] ?? "sandbox") === "live" ? ($this->settings["live_url"] ?? "") : ($this->settings["sandbox_url"] ?? "")), "/");

        try {
            $response = Http::withBasicAuth((string) ($this->settings["username"] ?? ""), (string) ($this->settings["password"] ?? ""))
                ->withHeaders(array_filter(["X-Api-Key" => $this->settings["api_key"] ?? null, "Accept" => "application/json"]))
                ->timeout((int) ($config["timeout"] ?? 120))
                ->asJson()
                ->post($base . $config["paths"][$operation], $payload);
        } catch (Throwable $e) {
            return GatewayResult::pending("NETWORK", $e->getMessage());
        }

        $body = $response->json() ?? [];
        $map = $config["response"];
        $code = (string) (Arr::get($body, $map["code"]) ?? "HTTP-" . $response->status());
        $message = Arr::get($body, $map["message"]) ?? $response->reason();
        $providerRef = Arr::get($body, $map["transaction_id"]);

        if ($response->successful() && in_array($code, $map["success_codes"], true)) {
            return GatewayResult::success($providerRef, null, $code, $message, $body);
        }

        if ($response->serverError() || in_array($code, $map["pending_codes"], true)) {
            return GatewayResult::pending($code, $message, $body);
        }

        return GatewayResult::failed($code, $message, $body);
    }
}
