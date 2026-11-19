<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile money (M-Pesa, e-Mola, mKesh):
 *  - every mobile payment carries the operator's transaction reference and the payer's number;
 *  - one ledger account per wallet, apart from the bank;
 *  - push requests (C2B) and refunds (B2C) sent to the operators' APIs;
 *  - wallet statements imported and matched against the recorded payments.
 */
return new class extends Migration
{
    private const WALLET_ACCOUNTS = [
        ["1.2.2", "Carteira móvel M-Pesa"],
        ["1.2.3", "Carteira móvel e-Mola"],
        ["1.2.4", "Carteira móvel mKesh"],
    ];

    private const TEMPLATES = [
        "Pedido de pagamento" => "Olá {customer}, o valor da sua encomenda {order} é {amount}. Pode pagar por M-Pesa ou e-Mola aqui: {pay_link}",
    ];

    public function up(): void
    {
        Schema::table("payments", function (Blueprint $table) {
            $table->string("transaction_ref", 40)->nullable()->after("method");
            $table->string("payer_phone", 20)->nullable()->after("transaction_ref");
            $table->timestamp("reconciled_at")->nullable()->after("payer_phone");
            $table->index(["method", "transaction_ref"]);
        });

        Schema::create("mobile_transactions", function (Blueprint $table) {
            $table->id();
            $table->string("type", 5);                 // c2b (we collect) | b2c (we refund)
            $table->string("provider", 10);            // mpesa | emola
            $table->string("reference", 30)->unique(); // our reference sent to the operator
            $table->foreignId("order_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("customer_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("credit_note_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("courier_job_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("payment_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->string("channel", 15)->default("counter"); // counter | courier | link
            $table->string("phone", 20);
            $table->decimal("amount", 12, 2);
            $table->string("status", 15)->default("pending"); // pending | processing | success | failed | expired
            $table->string("provider_ref", 60)->nullable();     // operator transaction id
            $table->string("conversation_id", 80)->nullable();
            $table->string("response_code", 30)->nullable();
            $table->string("response_message", 255)->nullable();
            $table->json("raw")->nullable();
            $table->unsignedSmallInteger("attempts")->default(0);
            $table->timestamp("completed_at")->nullable();
            $table->timestamps();
            $table->index(["status", "created_at"]);
            $table->index(["provider", "provider_ref"]);
        });

        Schema::create("wallet_statements", function (Blueprint $table) {
            $table->id();
            $table->string("provider", 10);
            $table->string("file_name", 200);
            $table->date("period_from")->nullable();
            $table->date("period_to")->nullable();
            $table->unsignedInteger("lines_count")->default(0);
            $table->unsignedInteger("matched_count")->default(0);
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create("wallet_statement_lines", function (Blueprint $table) {
            $table->id();
            $table->foreignId("wallet_statement_id")->constrained()->cascadeOnDelete();
            $table->string("provider", 10);
            $table->string("transaction_ref", 60);
            $table->dateTime("occurred_at")->nullable();
            $table->decimal("amount", 12, 2);
            $table->string("phone", 20)->nullable();
            $table->string("description", 255)->nullable();
            $table->string("status", 20)->default("unmatched"); // matched | amount_mismatch | unmatched | ignored
            $table->decimal("recorded_amount", 12, 2)->nullable();
            $table->timestamps();
            $table->index(["provider", "transaction_ref"]);
        });

        $parent = DB::table("accounts")->where("code", "1.2")->value("id");
        if ($parent) {
            foreach (self::WALLET_ACCOUNTS as [$code, $name]) {
                if (!DB::table("accounts")->where("code", $code)->exists()) {
                    DB::table("accounts")->insert([
                        "code" => $code, "name" => $name, "type" => "asset", "is_group" => false, "is_active" => true,
                        "parent_id" => $parent, "created_at" => now(), "updated_at" => now(),
                    ]);
                }
            }
        }

        foreach (self::TEMPLATES as $name => $body) {
            if (!DB::table("message_templates")->where("name", $name)->exists()) {
                DB::table("message_templates")->insert(["name" => $name, "body" => $body, "is_active" => true, "created_at" => now(), "updated_at" => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table("message_templates")->whereIn("name", array_keys(self::TEMPLATES))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from("whatsapp_messages")->whereColumn("whatsapp_messages.template_id", "message_templates.id"))
            ->delete();

        // Wallet accounts go only when nothing was posted to them.
        foreach (self::WALLET_ACCOUNTS as [$code]) {
            $id = DB::table("accounts")->where("code", $code)->value("id");
            if ($id && !DB::table("journal_lines")->where("account_id", $id)->exists()) {
                DB::table("accounts")->where("id", $id)->delete();
            }
        }

        Schema::dropIfExists("wallet_statement_lines");
        Schema::dropIfExists("wallet_statements");
        Schema::dropIfExists("mobile_transactions");

        Schema::table("payments", function (Blueprint $table) {
            $table->dropIndex(["method", "transaction_ref"]);
            $table->dropColumn(["transaction_ref", "payer_phone", "reconciled_at"]);
        });
    }
};
