<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\MeResource;
use Auth;
use Dedoc\Scramble\Attributes\Group;

#[Group('Authentication')]
class MeApiController extends Controller
{
    public function __invoke(): MeResource
    {
        $user = Auth::user();
        if ($user === null) {
            abort(401, 'Unauthenticated');
        }

        $user->load([
            'roles.permissions',
            'financers',
            'financers.division',
            'financers.division.modules',
            'financers.division.integrations',
            'financers.integrations',
            'financers.modules',
            'credits', // Précharge les crédits pour éviter les requêtes N+1
            'pinnedHRToolsLinks.financer', // Précharge les liens HRTools épinglés avec leur financer,
            'departments',
            'sites',
            'managers',
            'contractTypes',
            'tags',
            'workMode',
            'jobTitle',
            'jobLevel',
        ]);

        return new MeResource($user);
    }
}
