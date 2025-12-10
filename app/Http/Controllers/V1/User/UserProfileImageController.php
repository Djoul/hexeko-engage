<?php

namespace App\Http\Controllers\V1\User;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Services\Models\UserProfileImageService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('User settings')]
class UserProfileImageController extends Controller
{
    /**
     * Upload profile image
     *
     * Upload or update the user's profile image.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_USER, PermissionDefaults::SELF_UPDATE_USER])]
    public function update(Request $request, UserProfileImageService $service): JsonResponse
    {
        $request->validate([
            /**
             * The 'profile_image' field is required and must be a string.
             */
            'profile_image' => [
                'required',
                'string',
            ],
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $profileImage = $request->profile_image;
        if (! is_string($profileImage)) {
            return response()->json(['message' => 'Invalid image data'], 422);
        }
        $service->updateProfileImage($user, $profileImage);

        return response()->json(['message' => 'Profile image updated']);
    }
}
