<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Resources\User\UserIndexResource;
use App\Models\User;
use App\Services\Models\UserService;
use App\Services\RoleManagementService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('User')]
class UserIndexController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected RoleManagementService $roleManagementService
    ) {}

    /**
     * List users
     *
     * Retrieve a paginated list of users with optional filters and sorting.
     */
    #[RequiresPermission(PermissionDefaults::READ_USER)]
    #[QueryParameter('search', description: 'Global search across searchable fields: first_name, last_name, email, phone, description. Minimum 2 characters required.', type: 'string', example: 'John')]
    #[QueryParameter('id', description: 'UUID de l\'utilisateur.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('email', description: 'Email de l\'utilisateur (recherche partielle).', type: 'string', example: 'user@example.com')]
    #[QueryParameter('first_name', description: 'Prénom de l\'utilisateur (recherche partielle).', type: 'string', example: 'John')]
    #[QueryParameter('last_name', description: 'Nom de famille de l\'utilisateur (recherche partielle).', type: 'string', example: 'Doe')]
    #[QueryParameter('enabled', description: 'Statut d\'activation de l\'utilisateur.', type: 'boolean', example: 'true')]
    #[QueryParameter('team_id', description: 'UUID de l\'équipe.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174001')]
    #[QueryParameter('role', description: 'Nom du rôle.', type: 'string', example: 'admin')]
    #[QueryParameter('date_from', description: 'Date de création minimale (format YYYY-MM-DD).', type: 'date', example: '2023-01-01')]
    #[QueryParameter('date_to', description: 'Date de création maximale (format YYYY-MM-DD).', type: 'date', example: '2023-12-31')]
    #[QueryParameter('country', description: 'Pays de l\'utilisateur.', type: 'string', example: 'France')]
    #[QueryParameter('currency', description: 'Devise de l\'utilisateur.', type: 'string', example: 'EUR')]
    #[QueryParameter('language', description: 'Langue de l\'utilisateur.', type: 'string', example: 'fr')]
    #[QueryParameter('timezone', description: 'Fuseau horaire de l\'utilisateur.', type: 'string', default: 'Europe/Paris', example: 'Europe/Paris')]
    #[QueryParameter('phone', description: 'Numéro de téléphone de l\'utilisateur (recherche partielle).', type: 'string', example: '+33612345678')]
    #[QueryParameter('status', description: 'Filtrer par statut: "active" (utilisateurs actifs), "inactive" (utilisateurs inactifs), "invited" (utilisateurs avec invitation_status=pending)', type: 'string', example: 'active')]
    #[QueryParameter('order-by', description: 'Champ de tri ascendant (doit être dans User::$sortable).', type: 'string', example: 'first_name')]
    #[QueryParameter('order-by-desc', description: 'Champ de tri descendant (doit être dans User::$sortable).', type: 'string', example: 'created_at')]
    #[QueryParameter('pagination', description: 'Type de pagination: "cursor" (défaut, meilleure performance) ou "page" (avec totaux).', type: 'string', example: 'cursor')]
    #[QueryParameter('cursor', description: 'Token de curseur pour la pagination cursor (obtenu depuis next_cursor/prev_cursor).', type: 'string', example: 'eyJpZCI6MTAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0')]
    #[QueryParameter('per_page', description: 'Nombre d\'éléments par page (min 1, max 100, défaut 25).', type: 'integer', example: '15')]
    #[QueryParameter('page', description: 'Numéro de page (uniquement pour pagination=page).', type: 'integer', example: '1')]
    public function __invoke(UserIndexRequest $request): AnonymousResourceCollection
    {
        // Normalize pagination parameters
        $perPageParam = $request->input('per_page');
        $perPage = is_numeric($perPageParam) && (int) $perPageParam > 0
            ? min((int) $perPageParam, 100)
            : 25;

        $page = $request->input('page', 1);
        $page = is_numeric($page) && (int) $page > 0 ? (int) $page : 1;

        // Build base query with eager loading
        /** @var Builder<User> $query */
        $query = User::query()
            ->with([
                /** @phpstan-ignore method.nonObject */
                'media' => fn ($q) => $q->where('collection_name', 'profile_image'),
                'roles',
                'roles.permissions',
                'permissions',
                'financers',
            ]);

        /** @phpstan-ignore method.notFound */
        $query = $query->pipeFiltered();

        /** @var Builder<User> $query Reassert type after macro */
        $orders = collect($query->getQuery()->orders ?? []);
        if (! $orders->contains('column', 'id')) {
            $query = $query->orderBy('id', 'asc');
        }

        // Offset pagination with totals
        $users = $query->paginate($perPage, ['*'], 'page', $page);

        $meta = [
            'current_page' => $users->currentPage(),
            'per_page' => $users->perPage(),
            'last_page' => $users->lastPage(),
            'total' => $users->total(),
            'assignable_roles' => array_values($this->getAssignableRoles()),
        ];

        return UserIndexResource::collection($users->items())->additional(['meta' => $meta]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAssignableRoles(): array
    {
        $authUser = auth()->user();

        $assignableRoles = [];

        if ($authUser) {
            $rolesCanAssign = $this->roleManagementService->getRolesUserCanAssign($authUser);
            foreach ($rolesCanAssign as $role) {
                if (! array_key_exists($role, $assignableRoles)) {
                    $assignableRoles[$role] = [
                        'value' => $role,
                        'label' => $this->getRoleLabel($role),
                    ];
                }
            }
        }

        return $assignableRoles;
    }

    private function getRoleLabel(string $role): string
    {
        $labels = [
            RoleDefaults::HEXEKO_SUPER_ADMIN => 'Hexeko Super Admin',
            RoleDefaults::HEXEKO_ADMIN => 'Hexeko Admin',
            RoleDefaults::DIVISION_SUPER_ADMIN => 'Division Super Admin',
            RoleDefaults::DIVISION_ADMIN => 'Division Admin',
            RoleDefaults::FINANCER_SUPER_ADMIN => 'Financer Super Admin',
            RoleDefaults::FINANCER_ADMIN => 'Financer Admin',
            RoleDefaults::BENEFICIARY => 'Beneficiary',
        ];

        return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
