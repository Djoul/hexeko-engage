<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Actions\User\CRUD\ShowUserAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use Dedoc\Scramble\Attributes\Group;

#[Group('User')]
class UserShowController extends Controller
{
    public function __construct(
        protected ShowUserAction $showUserAction
    ) {}

    /**
     * Show user details
     *
     * Retrieve detailed information about a specific user.
     */
    #[RequiresPermission(PermissionDefaults::READ_USER)]
    public function __invoke(string $id): UserResource
    {
        $user = $this->showUserAction->execute($id);

        return new UserResource($user);
    }
}
