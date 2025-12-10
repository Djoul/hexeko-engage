<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserImageRequest;
use App\Http\Resources\User\UserImageResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('User')]
class UserImageController extends Controller
{
    /**
     * List users with profile image
     *
     * Retrieve a paginated list of users with profile image.
     */
    #[RequiresPermission(PermissionDefaults::READ_USER)]
    #[QueryParameter('limit', description: 'Number of items to return.', type: 'integer', example: '15')]
    public function __invoke(UserImageRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $limit */
        $limit = $request->validated('limit');
        $limit = $limit ?? Pagination::SMALL;

        $query = User::query()
            ->with([
                /** @phpstan-ignore method.nonObject */
                'media' => fn ($q) => $q->where('collection_name', 'profile_image'),
            ])
            ->orderByRaw('(SELECT COUNT(*) FROM media WHERE media.model_id = users.id AND media.model_type = ? AND media.collection_name = ?) DESC', [User::class, 'profile_image'])
            ->inRandomOrder();

        $users = $query->limit($limit)->get();

        return UserImageResource::collection($users);
    }
}
