<?php

use App\Models\Integration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Integration::query()->each(function (Integration $integration): void {
            $kebabCaseName = Str::camel($integration->name);
            $integration->update([
                'resources_count_unit' => "{$kebabCaseName}.resourcesCountUnit",
            ]);
        });
    }
};
