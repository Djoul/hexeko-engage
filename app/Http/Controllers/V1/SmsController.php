<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    /**
     * Sms Controller constructor.
     */
    #[ExcludeRouteFromDocs]
    public function send(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'SMS sent',
            'credits_used' => $request->get('credit_amount_required'),
        ]);
    }
}
