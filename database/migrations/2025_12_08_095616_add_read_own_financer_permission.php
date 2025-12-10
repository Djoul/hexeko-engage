<?php

use App\Enums\IDP\PermissionDefaults;
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => PermissionDefaults::READ_OWN_FINANCER],
            [
                'id' => Str::uuid()->toString(),
                'guard_name' => 'api',
                'is_protected' => true,
            ]
        );

    }
};
