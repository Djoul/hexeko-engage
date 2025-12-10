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
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');
        $morphPrefix = config('audit.user.morph_prefix', 'user');

        // Drop the existing audits table
        Schema::connection($connection)->dropIfExists($table);

        // Recreate the audits table with UUID support
        Schema::connection($connection)->create($table, function (Blueprint $table) use ($morphPrefix): void {
            $table->bigIncrements('id');
            $table->string($morphPrefix.'_type')->nullable();
            $table->string($morphPrefix.'_id', 36)->nullable(); // UUID for user_id
            $table->string('event');

            // Use uuidMorphs instead of morphs for UUID support
            $table->string('auditable_type');
            $table->string('auditable_id', 36);

            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
            $table->index(['auditable_id', 'auditable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');
        $morphPrefix = config('audit.user.morph_prefix', 'user');

        // Drop the modified table
        Schema::connection($connection)->dropIfExists($table);

        // Recreate the original table structure
        Schema::connection($connection)->create($table, function (Blueprint $table) use ($morphPrefix): void {
            $table->bigIncrements('id');
            $table->string($morphPrefix.'_type')->nullable();
            $table->unsignedBigInteger($morphPrefix.'_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
        });
    }
};
