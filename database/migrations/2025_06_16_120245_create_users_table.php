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
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('temp_password')->nullable();
            $table->string('cognito_id')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->boolean('force_change_email')->default(false);
            $table->date('birthdate')->nullable();
            $table->boolean('terms_confirmed')->default(false);
            $table->boolean('enabled')->default(true);
            $table->string('locale', 5)->default('fr-FR');
            $table->string('currency', 3)->default('EUR');
            $table->string('timezone')->nullable();
            $table->string('stripe_id')->nullable()->index();
            $table->jsonb('external_id')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->boolean('opt_in')->default(false);
            $table->string('phone')->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->text('description')->nullable();
            $table->uuid('team_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
