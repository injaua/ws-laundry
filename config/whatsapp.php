<?php

/*
|--------------------------------------------------------------------------
| WhatsApp Business (Cloud API)
|--------------------------------------------------------------------------
|
| "log" keeps every message inside the platform without contacting Meta, so
| the inbox and the automatic notifications work in development and before the
| business number is approved. "cloud" sends and receives through the official
| WhatsApp Cloud API (graph.facebook.com). "web" is the free connection by QR code
| (a normal number linked like WhatsApp Web, via the service in whatsapp-web/):
| unofficial, so it is used for conversations only, never for notifications.
|
*/

return [
    // Channel for conversations in the inbox: log, cloud or web.
    "driver" => env("WHATSAPP_DRIVER", "log"),

    // Channel for automatic notifications: cloud or log. Empty = same as "driver";
    // with driver "web" and no cloud here, automatic notifications are off.
    "notifications_driver" => env("WHATSAPP_NOTIFICATIONS_DRIVER"),

    "cloud" => [
        "api_version" => env("WHATSAPP_API_VERSION", "v21.0"),
        "phone_number_id" => env("WHATSAPP_PHONE_NUMBER_ID"),
        "access_token" => env("WHATSAPP_ACCESS_TOKEN"),
        // Meta signs every webhook call with the app secret (X-Hub-Signature-256).
        "app_secret" => env("WHATSAPP_APP_SECRET"),
        // Any string you choose; Meta echoes it when you register the webhook URL.
        "verify_token" => env("WHATSAPP_VERIFY_TOKEN"),
        "timeout" => (int) env("WHATSAPP_TIMEOUT", 15),
    ],

    "web" => [
        // The companion service (whatsapp-web/server.mjs); listens on 127.0.0.1 only.
        "url" => env("WHATSAPP_WEB_URL", "http://127.0.0.1:3010"),
        // Shared secret (at least 16 characters): API access and webhook signature.
        "token" => env("WHATSAPP_WEB_TOKEN"),
        "timeout" => (int) env("WHATSAPP_WEB_TIMEOUT", 20),
        // Choosing "QR code" in the settings starts the service (npm install + node server.mjs);
        // the scheduler restarts it after a reboot. false = you run it yourself (pm2, systemd, NSSM…).
        "autostart" => (bool) env("WHATSAPP_WEB_AUTOSTART", true),
        // Path to the node executable when it is not found in the usual folders / PATH of the web server.
        "node" => env("WHATSAPP_WEB_NODE"),
    ],

    // Language code of the approved message templates (Meta format: pt_PT, pt_BR, en_US…).
    "template_language" => env("WHATSAPP_TEMPLATE_LANGUAGE", "pt_PT"),

    // Meta only allows free-form messages within 24h of the customer's last message.
    "session_window_hours" => 24,

    // Inbound keywords that unsubscribe a customer from automatic notifications.
    "opt_out_keywords" => ["PARAR", "STOP", "SAIR", "CANCELAR"],
];
