<?php

namespace App\Http\Controllers;

use App\Events\ApideckSyncEvent;
use App\Events\PublicMessageEvent;
use App\Events\StatsUpdatedEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReverbTestController extends Controller
{
    public function testPublicMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'message' => 'required|string',
            'type' => 'required|string|in:info,warning,error,success',
        ]);

        Log::info('Test Reverb Public Message', $validated);

        broadcast(new PublicMessageEvent(
            $validated['title'],
            $validated['message'],
            $validated['type']
        ));

        return response()->json(['status' => 'success', 'message' => 'Public message sent']);
    }

    public function testStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stats' => 'required|array',
            'stats.users_online' => 'required|integer',
            'stats.revenue' => 'required|numeric',
        ]);

        Log::info('Test Reverb Stats', $validated);

        broadcast(new StatsUpdatedEvent($validated['stats']));

        return response()->json(['status' => 'success', 'message' => 'Stats broadcasted']);
    }

    public function testApideck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financerId' => 'required|string',
            'syncData' => 'required|array',
            'syncData.created' => 'required|integer',
            'syncData.updated' => 'required|integer',
        ]);

        Log::info('Test Reverb Apideck', $validated);

        broadcast(new ApideckSyncEvent(
            $validated['financerId'],
            $validated['syncData']
        ));

        return response()->json(['status' => 'success', 'message' => 'Apideck sync event sent']);
    }
}
