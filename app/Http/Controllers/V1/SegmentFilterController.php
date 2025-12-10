<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class SegmentFilterController extends Controller
{
    /**
     * Get all available filters for segments.
     */
    public function index(): Response
    {
        return response()->json([
            'data' => config('segments.filters'),
            'meta' => [
                'operators' => config('segments.operators'),
            ],
        ]);
    }
}
