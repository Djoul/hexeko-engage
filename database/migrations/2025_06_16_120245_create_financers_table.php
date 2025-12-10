<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('financers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->jsonb('external_id')->nullable();
            $table->string('timezone')->default('Europe/Paris');
            $table->string('registration_number')->nullable();
            $table->string('registration_country')->nullable();
            $table->string('website')->nullable();
            $table->string('iban')->nullable();
            $table->string('vat_number')->nullable();
            $table->uuid('representative_id')->nullable()->index('companies_representative_id_foreign');
            $table->uuid('division_id')->index('companies_division_id_foreign');
            $table->timestamp('created_at')->nullable()->index('companies_created_at_index');
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes()->index('companies_deleted_at_index');
            $table->boolean('active')->default(true);
            $table->json('available_languages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financers');
    }
};
