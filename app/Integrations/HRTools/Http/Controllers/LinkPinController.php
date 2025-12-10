<?php

namespace App\Integrations\HRTools\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\HRTools\Actions\ToggleLinkPinAction;
use App\Integrations\HRTools\Models\Link;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Modules/HRTools
 *
 * @authenticated
 */
#[Group('Modules/HRTools')]
class LinkPinController extends Controller
{
    public function __construct(
        private ToggleLinkPinAction $toggleLinkPinAction
    ) {}

    /**
     * Toggle pin status for a link.
     *
     * This endpoint allows users to pin or unpin a link.
     * If the link is already pinned, it will be unpinned, and vice versa.
     *
     * @param  string  $id  The ID of the link to toggle pin status
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::READ_HRTOOLS)]
    public function toggle(string $id): Response
    {
        try {
            $link = Link::findOrFail($id);
            $this->authorize('view', $link);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Link not found'], 404);
        }

        try {
            $user = Auth::user();

            if ($user === null) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            $pinned = $this->toggleLinkPinAction->execute($user, $id);

            return response()->json([
                'message' => $pinned ? 'Link pinned successfully' : 'Link unpinned successfully',
                'pinned' => $pinned,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
