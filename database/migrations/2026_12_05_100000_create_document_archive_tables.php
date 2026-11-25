<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document archive (admin, owner, accountant): Word, Excel, PowerPoint and PDF files of
     * the store, organised in folders and categories, with tags, an optional expiry date and
     * per-user favourites. Files live on the private disk; only the archive serves them.
     */
    public function up(): void
    {
        Schema::create("document_folders", function (Blueprint $table) {
            $table->id();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->string("name", 100);
            $table->string("color", 20)->default("primary");
            $table->timestamps();
            $table->unique(["store_id", "name"]);
        });

        Schema::create("document_categories", function (Blueprint $table) {
            $table->id();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->string("name", 100);
            $table->string("color", 20)->default("secondary");
            $table->timestamps();
            $table->unique(["store_id", "name"]);
        });

        Schema::create("documents", function (Blueprint $table) {
            $table->id();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->foreignId("folder_id")->nullable()->constrained("document_folders")->nullOnDelete();
            $table->foreignId("category_id")->nullable()->constrained("document_categories")->nullOnDelete();
            $table->string("title");
            $table->string("reference", 100)->nullable();
            $table->text("description")->nullable();
            $table->json("tags")->nullable();
            $table->date("document_date")->nullable();
            $table->date("expires_at")->nullable();
            $table->string("path");
            $table->string("original_name");
            $table->string("mime_type", 150)->nullable();
            $table->string("extension", 10);
            $table->unsignedBigInteger("size")->default(0);
            $table->foreignId("uploaded_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();
            $table->index(["store_id", "created_at"]);
            $table->index(["store_id", "expires_at"]);
        });

        Schema::create("document_favorites", function (Blueprint $table) {
            $table->foreignId("document_id")->constrained()->cascadeOnDelete();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->primary(["document_id", "user_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("document_favorites");
        Schema::dropIfExists("documents");
        Schema::dropIfExists("document_categories");
        Schema::dropIfExists("document_folders");
    }
};
