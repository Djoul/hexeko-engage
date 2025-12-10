<?php

namespace App\Http\Controllers\V1\User;

use App\Actions\User\CRUD\UpdateUserSettingsAction;
use App\Enums\Languages;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('User settings')]
class UserSettingsController extends Controller
{
    public function __construct(
        private readonly UpdateUserSettingsAction $updateUserSettingsAction,
    ) {}

    /**
     * Update user settings
     *
     * Update the authenticated user's settings and preferences.
     */
    public function __invoke(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'min:5', 'max:5', 'in:'.implode(',', Languages::asArray())],
        ]);

        $updatedUser = $this->updateUserSettingsAction->execute($user, $validated);

        return response()->json([
            'message' => 'User settings updated successfully',
            'user' => $updatedUser,
        ], Response::HTTP_OK);
    }
}
