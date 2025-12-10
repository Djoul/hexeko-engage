<?php

namespace App\Http\Controllers\V1\Apideck;

use App\Http\Controllers\Controller;
use App\Models\Financer;
use App\Services\Apideck\ApideckService;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

#[Group('Apideck')]
class WebhookController extends Controller
{
    /**
     * Apideck Webhook.
     *
     * Handle incoming webhook request
     *
     * @unauthenticated
     */
    public function handle(Request $request): JsonResponse
    {
        /** @var array<string,string> $payload */
        $payload = $request->input('payload');

        Log::debug('Apideck webhook received', [
            'event_type' => $payload['event_type'] ?? 'unknown',
            'entity_type' => $payload['entity_type'] ?? 'unknown',
            'entity_id' => $payload['entity_id'] ?? 'unknown',
            'consumer_id' => $payload['consumer_id'] ?? 'unknown',
            'service_id' => $payload['service_id'] ?? 'unknown',
        ]);

        // Find financer by consumer_id
        $financer = Financer::where('external_id->sirh->consumer_id', $payload['consumer_id'])->first();
        // If financer is not found, return error response
        if (! $financer) {
            Log::warning('Apideck webhook: Financer not found', [
                'consumer_id' => $payload['consumer_id'] ?? 'unknown',
            ]);

            return response()->json(['message' => 'Financer not found'], 404);
        }

        Log::debug('Apideck webhook: Financer found', [
            'financer_id' => $financer->id,
            'financer_name' => $financer->name,
        ]);

        // Fetch employee from ApideckService
        $apideckService = app(ApideckService::class);
        // Initialize the consumer ID context for the webhook request
        $apideckService->initializeConsumerId($financer->id);

        Log::debug('Apideck webhook: Fetching employee', [
            'entity_id' => $payload['entity_id'],
            'financer_id' => $financer->id,
        ]);

        try {
            $employeeResponse = $apideckService->getEmployee($payload['entity_id']);
            // Dispatch job to create user if employee data is available
            $employee = $employeeResponse['data'] ?? null;

            if (! empty($employee) && is_array($employee)) {
                Log::debug('Apideck webhook: Employee data received, syncing', [
                    'employee_id' => $employee['id'] ?? 'unknown',
                    'employee_email' => $employee['email'] ?? 'unknown',
                    'financer_id' => $financer->id,
                ]);

                $apideckService->syncEmployee($employee, $financer->id);

                Log::debug('Apideck webhook: Employee synchronized successfully', [
                    'employee_id' => $employee['id'] ?? 'unknown',
                ]);
            } else {
                Log::debug('Apideck webhook: No employee data in response', [
                    'entity_id' => $payload['entity_id'],
                    'response' => $employeeResponse,
                ]);
            }

            return response()->json(['message' => 'Webhook received']);
        } catch (Exception $e) {
            Log::error('Apideck webhook: Failed to process webhook', [
                'entity_id' => $payload['entity_id'] ?? 'unknown',
                'financer_id' => $financer->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to process webhook',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
