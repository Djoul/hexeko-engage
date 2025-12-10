<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Exceptions\DeprecatedFeatureException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserFormRequest;
use App\Http\Resources\User\UserResource;
use Dedoc\Scramble\Attributes\Group;

/**
 * @deprecated
 */
#[Group('User')]
class StoreUserController extends Controller
{
    /**
     * Create a new user
     *
     * This endpoint is deprecated. Please use the /api/v1/invited-users endpoint instead.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_USER)]
    public function __invoke(UserFormRequest $request): UserResource
    {
        throw new DeprecatedFeatureException(
            'This endpoint is deprecated. Please use the /api/v1/invited-users endpoint instead.',
            '/api/v1/invited-users');
    }
}
