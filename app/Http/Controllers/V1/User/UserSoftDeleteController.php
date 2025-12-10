<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Actions\User\CRUD\SoftDeleteUserAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Models\Financer;
use App\Services\Models\UserService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Group('User')]
class UserSoftDeleteController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Soft delete user
     *
     * Soft delete a user (can be restored later).
     */
    #[RequiresPermission(PermissionDefaults::DELETE_USER)]
    public function __invoke(string $id, SoftDeleteUserAction $softDeleteUserAction): JsonResponse|Response
    {
        // Get accessible financers from authorization context
        $accessibleFinancers = authorizationContext()->financerIds();

        // Find the user
        try {
            $user = $this->userService->find($id, ['financers']);
        } catch (ModelNotFoundException) {
            throw new NotFoundHttpException('User not found');
        }

        // Get user's active financers
        $activeFinancers = $user->financers()->wherePivot('active', true)->get();

        // Check if user has multiple financers (cannot delete)
        if ($activeFinancers->count() > 1) {
            throw new AccessDeniedHttpException('Cannot delete user attached to multiple financers');
        }

        // If user has one financer, check if it's accessible
        if ($activeFinancers->count() === 1) {
            /** @var Financer|null $firstFinancer */
            $firstFinancer = $activeFinancers->first();
            if ($firstFinancer === null) {
                throw new AccessDeniedHttpException('Invalid financer data');
            }
            $userFinancerId = $firstFinancer->id;
            if (! in_array($userFinancerId, $accessibleFinancers)) {
                throw new AccessDeniedHttpException('User belongs to a financer you do not have access to');
            }
        }

        // Execute soft delete
        $softDeleteUserAction->execute($user);

        return response()->noContent();
    }
}
