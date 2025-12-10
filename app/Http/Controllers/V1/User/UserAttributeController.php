<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Attributes\RequiresPermission;
use App\Enums\Gender;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\TimeZones;
use App\Http\Controllers\Controller;
use App\Http\Resources\Department\DepartmentResource;
use App\Http\Resources\Site\SiteResource;
use App\Models\Department;
use App\Http\Resources\JobTitle\JobTitleResource;
use App\Models\JobTitle;
use App\Http\Resources\JobLevel\JobLevelResource;
use App\Models\JobLevel;
use App\Http\Resources\ContractType\ContractTypeResource;
use App\Http\Resources\Gender\GenderResource;
use App\Models\ContractType;
use App\Http\Resources\WorkMode\WorkModeResource;
use App\Models\WorkMode;
use App\Http\Resources\Tag\TagResource;
use App\Models\Tag;
use Dedoc\Scramble\Attributes\Group;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

#[Group('User')]
class UserAttributeController extends Controller
{
    /**
     * List attributes that can be assigned to a user
     */
    #[RequiresPermission(PermissionDefaults::READ_USER)]
    public function __invoke(Request $request): JsonResponse
    {
        $genders = collect(Gender::asSelectObject())->map(function ($item) {
            return (object) $item;
        });

        return response()->json([
            'departments' => DepartmentResource::collection(
                Department::query()->orderBy('name', 'asc')->get()
            ),
            'contract_types' => ContractTypeResource::collection(
                ContractType::query()->orderBy('name', 'asc')->get()
            ),
            'genders' => GenderResource::collection(array_values($genders->toArray())),
            'job_levels' => JobLevelResource::collection(
                JobLevel::query()->orderBy('name', 'asc')->get()
            ),
            'job_titles' => JobTitleResource::collection(
                JobTitle::query()->orderBy('name', 'asc')->get()
            ),
            'sites' => SiteResource::collection(
                Site::query()->orderBy('name', 'asc')->get()
            ),
            'timezones' => TimeZones::allWithLabels(),
            'tags' => TagResource::collection(
                Tag::query()->orderBy('name', 'asc')->get()
            ),
            'work_modes' => WorkModeResource::collection(
                WorkMode::query()->orderBy('name', 'asc')->get()
            ),
        ]);
    }
}
