<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    /**
     * @unauthenticated
     * AiController constructor.
     */
    #[ExcludeRouteFromDocs]
    public function generate(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'AI request accepted',
            'tokens_used' => $request->get('credit_amount_required'),
        ]);
    }
}
