<?php

namespace App\Integrations\Vouchers\Amilon\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\Vouchers\Amilon\Services\AmilonContractService;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Log;

#[Group('Modules/Vouchers/Amilon')]
class ContractController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AmilonContractService $contractService
    ) {}

    /**
     * Get contract information.
     *
     * @return JsonResponse The contract information
     */
    //    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function show(): JsonResponse
    {
        try {
            $contractId = config('services.amilon.contrat_id');
            if (! is_string($contractId)) {
                throw new Exception('Contract ID not configured properly');
            }
            $contract = $this->contractService->getContract($contractId);

            return response()->json($contract->toArray());
        } catch (Exception $e) {
            Log::error('Error fetching contract', [
                'exception' => $e->getMessage(),
                'contract_id' => $contractId,
            ]);

            return response()->json([
                'error' => 'Failed to fetch contract',
                'message' => 'An error occurred while fetching the contract information',
            ], 500);
        }
    }
}
