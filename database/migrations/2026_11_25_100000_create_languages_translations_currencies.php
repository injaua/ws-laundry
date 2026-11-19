<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Languages and currencies created by the administrator.
 *  - languages: pt and en ship with the system (lang/ files); new ones start as a copy
 *    of a base language and are translated in the editor.
 *  - translations: per-key texts of a language (new languages, or edits to pt/en).
 *  - currencies: the list the default currency is chosen from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("languages", function (Blueprint $table) {
            $table->id();
            $table->string("code", 10)->unique();
            $table->string("name", 60);
            $table->string("flag", 16)->nullable();
            $table->string("direction", 3)->default("ltr");
            $table->string("base_locale", 10)->default("en");
            $table->boolean("is_active")->default(true);
            $table->boolean("is_system")->default(false);
            $table->timestamps();
        });

        Schema::create("translations", function (Blueprint $table) {
            $table->id();
            $table->string("locale", 10);
            $table->string("group", 40);
            $table->string("key", 191);
            $table->text("value");
            $table->timestamps();
            $table->unique(["locale", "group", "key"]);
        });

        Schema::create("currencies", function (Blueprint $table) {
            $table->id();
            $table->string("code", 3)->unique();
            $table->string("name", 60);
            $table->string("symbol", 10);
            $table->string("position", 6)->default("after");
            $table->unsignedTinyInteger("decimals")->default(2);
            $table->boolean("is_active")->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table("languages")->insert([
            ["code" => "pt", "name" => "Português", "flag" => "🇵🇹", "direction" => "ltr", "base_locale" => "pt", "is_active" => true, "is_system" => true, "created_at" => $now, "updated_at" => $now],
            ["code" => "en", "name" => "English", "flag" => "🇬🇧", "direction" => "ltr", "base_locale" => "en", "is_active" => true, "is_system" => true, "created_at" => $now, "updated_at" => $now],
        ]);

        // The currency in use keeps its current settings; a few common ones are ready (inactive).
        $setting = fn ($key, $default) => DB::table("site_settings")->where("key", $key)->value("value") ?: $default;
        $code = strtoupper($setting("currency_code", "MZN"));
        $currencies = [
            $code => ["name" => $code === "MZN" ? "Metical" : $code, "symbol" => $setting("currency_symbol", "Mts"), "position" => $setting("currency_position", "after"), "decimals" => (int) $setting("currency_decimals", 0), "is_active" => true],
            "USD" => ["name" => "Dólar americano", "symbol" => "$", "position" => "before", "decimals" => 2, "is_active" => false],
            "ZAR" => ["name" => "Rand sul-africano", "symbol" => "R", "position" => "before", "decimals" => 2, "is_active" => false],
            "EUR" => ["name" => "Euro", "symbol" => "€", "position" => "after", "decimals" => 2, "is_active" => false],
        ];
        foreach ($currencies as $c => $row) {
            DB::table("currencies")->insertOrIgnore(["code" => $c] + $row + ["created_at" => $now, "updated_at" => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists("currencies");
        Schema::dropIfExists("translations");
        Schema::dropIfExists("languages");
    }
};
