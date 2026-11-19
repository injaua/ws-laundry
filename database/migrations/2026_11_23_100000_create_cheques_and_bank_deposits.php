<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cash, bank transfer and cheque handling:
 * - bank deposits (cash from the safe and cheques taken to the bank);
 * - cheques with their life cycle (in hand -> deposited -> cleared / bounced);
 * - payments: cheque link, bounce flag (a bounced cheque never paid anything) and the
 *   cash handed over by the customer (to print the change on the receipt);
 * - ledger account 1.3 for cheques not yet deposited.
 */
return new class extends Migration
{
    private const ACCOUNT = ["1.3", "Cheques a depositar"];

    public function up(): void
    {
        Schema::create("bank_deposits", function (Blueprint $table) {
            $table->id();
            $table->date("deposited_at");
            $table->string("slip_number", 40)->nullable();
            $table->decimal("cash_amount", 12, 2)->default(0);
            $table->decimal("cheques_amount", 12, 2)->default(0);
            $table->decimal("total", 12, 2)->default(0);
            $table->string("note", 255)->nullable();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->timestamp("confirmed_at")->nullable(); // seen on the bank statement
            $table->timestamps();
            $table->index("deposited_at");
        });

        Schema::create("cheques", function (Blueprint $table) {
            $table->id();
            $table->foreignId("customer_id")->nullable()->constrained()->nullOnDelete();
            $table->string("number", 40);
            $table->string("bank", 60);
            $table->string("drawer", 120)->nullable(); // who signed the cheque
            $table->date("cheque_date"); // later than the receipt date when post-dated
            $table->decimal("amount", 12, 2)->default(0);
            $table->string("status", 20)->default("in_hand");
            $table->foreignId("bank_deposit_id")->nullable()->constrained()->nullOnDelete();
            $table->timestamp("deposited_at")->nullable();
            $table->timestamp("cleared_at")->nullable();
            $table->timestamp("bounced_at")->nullable();
            $table->string("bounce_reason", 255)->nullable();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(["status", "cheque_date"]);
            $table->index(["bank", "number"]);
        });

        Schema::table("payments", function (Blueprint $table) {
            $table->foreignId("cheque_id")->nullable()->after("transaction_ref")->constrained()->nullOnDelete();
            $table->timestamp("bounced_at")->nullable()->after("reconciled_at");
            $table->decimal("tendered", 12, 2)->nullable()->after("amount");
            $table->index("bounced_at");
        });

        Schema::table("wallet_statement_lines", function (Blueprint $table) {
            $table->string("match_note", 120)->nullable()->after("recorded_amount");
        });

        $parent = DB::table("accounts")->where("code", "1")->value("id");
        if ($parent && !DB::table("accounts")->where("code", self::ACCOUNT[0])->exists()) {
            DB::table("accounts")->insert([
                "code" => self::ACCOUNT[0], "name" => self::ACCOUNT[1], "type" => "asset", "is_group" => false, "is_active" => true,
                "parent_id" => $parent, "created_at" => now(), "updated_at" => now(),
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table("accounts")->where("code", self::ACCOUNT[0])->value("id");
        if ($id && !DB::table("journal_lines")->where("account_id", $id)->exists()) {
            DB::table("accounts")->where("id", $id)->delete();
        }

        Schema::table("wallet_statement_lines", function (Blueprint $table) {
            $table->dropColumn("match_note");
        });

        Schema::table("payments", function (Blueprint $table) {
            $table->dropIndex(["bounced_at"]);
            $table->dropConstrainedForeignId("cheque_id");
            $table->dropColumn(["bounced_at", "tendered"]);
        });

        Schema::dropIfExists("cheques");
        Schema::dropIfExists("bank_deposits");
    }
};
