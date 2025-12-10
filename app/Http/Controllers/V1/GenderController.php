<?php

namespace App\Http\Controllers\V1;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Resources\Gender\GenderResource;
use Dedoc\Scramble\Attributes\QueryParameter;

/**
 * Class GenderController
 */
class GenderController extends Controller
{
    /**
     * List genders
     *
     * Return a list of available gender options.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function index()
    {
        $genders = collect(Gender::asSelectObject())->map(function ($item) {
            return (object) $item;
        });

        return GenderResource::collection(array_values($genders->toArray()));
    }
}
