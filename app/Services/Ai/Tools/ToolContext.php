<?php

namespace App\Services\Ai\Tools;

/** What a tool needs to know besides its input: privacy choice of the store's AI settings. */
final class ToolContext
{
    public function __construct(public readonly bool $maskPersonal = true) {}

    /** +258 84 123 4567 => +258 84 ••• 4567 when personal data is masked. */
    public function phone(?string $phone): ?string
    {
        if ($phone === null || $phone === "" || !$this->maskPersonal) {
            return $phone;
        }
        $digits = preg_replace('/\D/', "", $phone);

        return strlen($digits) > 4 ? "•••" . substr($digits, -4) : "•••";
    }

    public function email(?string $email): ?string
    {
        if (!$email || !$this->maskPersonal) {
            return $email;
        }
        [$name, $domain] = array_pad(explode("@", $email, 2), 2, "");

        return mb_substr($name, 0, 1) . "•••@" . $domain;
    }
}
