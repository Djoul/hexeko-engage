<?php

namespace App\Integrations\Vouchers\Amilon\Jobs;

use App\Integrations\Vouchers\Amilon\Services\AmilonMerchantService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshMerchantList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AmilonMerchantService $amilonService): void
    {
        try {

            // Call the service to fetch products and update database
            $products = $amilonService->getMerchants();

            Log::info('Amilon merchant list refreshed successfully', [
                'count' => count($products),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to refresh Amilon merchant list', [
                'exception' => $e->getMessage(),
            ]);

            // Re-throw the exception to trigger job retry
            throw $e;
        }
    }
}
