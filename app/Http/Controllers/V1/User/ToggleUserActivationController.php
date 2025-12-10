<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Actions\User\Activation\ToggleUserActivationAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Services\Models\UserService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Group('User')]
class ToggleUserActivationController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Toggle user activation
     *
     * Enable or disable a user account for a specific financer.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_USER)]
    public function __invoke(
        string $id,
        string $financerId,
        ToggleUserActivationAction $toggleUserActivationAction
    ): JsonResponse {
        // Get accessible financers from authorization context
        $accessibleFinancers = authorizationContext()->financerIds();

        // Check if the requested financer is accessible
        if (! in_array($financerId, $accessibleFinancers)) {
            throw new AccessDeniedHttpException('You do not have access to this financer');
        }

        // Find the user
        try {
            $user = $this->userService->find($id, ['financers']);
        } catch (ModelNotFoundException) {
            throw new NotFoundHttpException('User not found');
        }

        // Check if user is associated with the financer
        $financer = $user->financers()->where('financer_id', $financerId)->first();
        if (! $financer) {
            throw new NotFoundHttpException("User is not associated with financer {$financerId}");
        }

        // Execute the toggle action
        $result = $toggleUserActivationAction->execute($user, $financerId);

        return response()->json([
            'message' => $result['active'] ? 'User activated successfully' : 'User deactivated successfully',
            'user_id' => $user->id,
            'financer_id' => $financerId,
            'active' => $result['active'],
        ]);
    }
}
