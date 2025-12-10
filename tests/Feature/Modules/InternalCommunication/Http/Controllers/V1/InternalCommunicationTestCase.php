<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\PermissionRegistrar;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
abstract class InternalCommunicationTestCase extends ProtectedRouteTestCase
{
    protected User $user;

    protected Team $team;

    /**
     * Set up the test environment.
     */
    final protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        $this->user = $this->createAuthUser(withContext: true);
    }

    /**
     * Create permissions for articles.
     */
    final protected function createArticlePermissions(): void
    {
        $articlePermissions = [
            'create_article',
            'read_article',
            'update_article',
            'delete_article',
        ];

        foreach ($articlePermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        // Refresh permission cache
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
