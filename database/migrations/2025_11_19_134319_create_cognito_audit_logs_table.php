<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cognito_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier_hash', 64)->index(); // SHA256 hash (RGPD compliant)
            $table->string('type', 20); // sms, email
            $table->string('trigger_source', 100); // Cognito trigger (e.g., CustomSMSSender_SignIn)
            $table->string('locale', 10); // fr-FR, en-GB, etc.
            $table->string('status', 20); // queued, sent, failed, retrying
            $table->text('encrypted_payload'); // Laravel Crypt encrypted JSON
            $table->text('error_message')->nullable();
            $table->string('source_ip', 45)->nullable(); // IPv4 or IPv6
            $table->timestamp('created_at')->index(); // Only created_at, no updated_at

            // Composite indexes for common queries
            $table->index(['type', 'status', 'created_at'], 'cognito_audit_type_status_created');
            $table->index(['identifier_hash', 'created_at'], 'cognito_audit_identifier_created');
            $table->index(['status', 'created_at'], 'cognito_audit_status_created');
        });

        // PostgreSQL Event: Auto-delete logs older than 90 days (RGPD retention)
        DB::statement("
            CREATE OR REPLACE FUNCTION delete_old_cognito_audit_logs()
            RETURNS TRIGGER AS $$
            BEGIN
                DELETE FROM cognito_audit_logs
                WHERE created_at < NOW() - INTERVAL '90 days';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement('
            CREATE TRIGGER cognito_audit_logs_retention_trigger
            AFTER INSERT ON cognito_audit_logs
            FOR EACH STATEMENT
            EXECUTE FUNCTION delete_old_cognito_audit_logs();
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS cognito_audit_logs_retention_trigger ON cognito_audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS delete_old_cognito_audit_logs');
        Schema::dropIfExists('cognito_audit_logs');
    }
};
