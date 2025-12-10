<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Team\CreateTeamAction;
use App\Actions\Team\DeleteTeamAction;
use App\Actions\Team\UpdateTeamAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamFormRequest;
use App\Http\Resources\Team\TeamCollection;
use App\Http\Resources\Team\TeamResource;
use App\Services\Models\TeamService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TeamController
 */
class TeamController extends Controller
{
    /**
     * TeamService constructor.
     */
    public function __construct(protected TeamService $teamService) {}

    /**
     * List teams.
     *
     * @response TeamCollection<TeamResource>
     */
    #[RequiresPermission(PermissionDefaults::READ_TEAM)]
    public function index(): TeamCollection
    {
        return new TeamCollection($this->teamService->all());
    }

    /**
     * Show team.
     */
    #[RequiresPermission(PermissionDefaults::READ_TEAM)]
    public function show(string $id): TeamResource
    {
        return new TeamResource($this->teamService->find($id));
    }

    /**
     * Store team.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_TEAM)]
    public function store(TeamFormRequest $request, CreateTeamAction $createTeamAction): TeamResource
    {
        $validatedData = $request->validated();

        $team = $createTeamAction->handle($validatedData);

        return new TeamResource($team);
    }

    /**
     * Update team.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_TEAM)]
    public function update(TeamFormRequest $request, string $id, UpdateTeamAction $updateTeamAction): TeamResource
    {
        $validatedData = $request->validated();

        $team = $this->teamService->find($id);

        $team = $updateTeamAction->handle($team, $validatedData);

        return new TeamResource($team);
    }

    /**
     * Delete team.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::DELETE_TEAM)]
    public function destroy(string $id, DeleteTeamAction $deleteTeamAction): Response
    {
        $team = $this->teamService->find($id);

        return response()->json(['success' => $deleteTeamAction->handle($team)])->setStatusCode(204);
    }
}
