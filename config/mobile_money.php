<?php

/*
 * Mobile money operators. Credentials are set by the admin in
 * Admin > Pagamentos móveis (stored encrypted); this file holds the technical
 * details of each API.
 */
return [
    // Vodacom M-Pesa OpenAPI (developer.mpesa.vm.co.mz)
    "mpesa" => [
        "sandbox_host" => env("MPESA_SANDBOX_HOST", "api.sandbox.vm.co.mz"),
        "live_host" => env("MPESA_LIVE_HOST", "api.vm.co.mz"),
        "ports" => [
            "c2b" => env("MPESA_PORT_C2B", 18352),
            "b2c" => env("MPESA_PORT_B2C", 18345),
            "query" => env("MPESA_PORT_QUERY", 18353),
        ],
        "paths" => [
            "c2b" => "/ipg/v1x/c2bPayment/singleStage/",
            "b2c" => "/ipg/v1x/b2cPayment/",
            "query" => "/ipg/v1x/queryTransactionStatus/",
        ],
    ],

    // Movitel e-Mola: confirm every value below against Movitel's merchant API documentation.
    "emola" => [
        "timeout" => 120,
        "paths" => [
            "c2b" => env("EMOLA_PATH_C2B", "/api/v1/payments/c2b"),
            "b2c" => env("EMOLA_PATH_B2C", "/api/v1/payments/b2c"),
            "query" => env("EMOLA_PATH_QUERY", "/api/v1/payments/status"),
        ],
        // Request field => value source (partner_code, reference, provider_ref, phone, phone_local, amount, description).
        "fields" => [
            "c2b" => ["partnerCode" => "partner_code", "transactionId" => "reference", "msisdn" => "phone", "amount" => "amount", "content" => "description"],
            "b2c" => ["partnerCode" => "partner_code", "transactionId" => "reference", "msisdn" => "phone", "amount" => "amount", "content" => "description"],
            "query" => ["partnerCode" => "partner_code", "transactionId" => "reference", "originalTransactionId" => "provider_ref"],
        ],
        // Where the answer fields are (dot notation) and which codes mean what.
        "response" => [
            "code" => "errorCode",
            "message" => "message",
            "transaction_id" => "data.transactionId",
            "success_codes" => ["0", "00"],
            "pending_codes" => ["01", "PENDING"],
        ],
    ],
];
