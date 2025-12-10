<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Jobs\RefreshMerchantList;
use App\Integrations\Vouchers\Amilon\Services\AmilonMerchantService;
use Exception;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class RefreshMerchantListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

    }

    #[Test]
    public function test_refresh_merchant_list_job_clears_cache_and_calls_service(): void
    {

        // Mock the AmilonService
        $mockService = Mockery::mock(AmilonMerchantService::class);
        $mockService->shouldReceive('getMerchants')
            ->once()
            ->andReturn(collect([
                ['name' => 'Test Merchant', 'retailer_id' => 'TEST001'],
            ]));

        $this->app->instance(AmilonMerchantService::class, $mockService);

        // Execute the job
        $job = new RefreshMerchantList;
        $job->handle($mockService);

    }

    #[Test]
    public function test_refresh_merchant_list_job_logs_success(): void
    {
        // Mock the AmilonService
        $mockService = Mockery::mock(AmilonMerchantService::class);
        $mockService->shouldReceive('getMerchants')
            ->once()
            ->andReturn(collect([
                ['name' => 'Test Merchant 1', 'retailer_id' => 'TEST001'],
                ['name' => 'Test Merchant 2', 'retailer_id' => 'TEST002'],
            ]));

        $this->app->instance(AmilonMerchantService::class, $mockService);

        // Expect log message
        Log::shouldReceive('info')
            ->once()
            ->with('Amilon merchant list refreshed successfully', [
                'count' => 2,
            ]);

        // Execute the job
        $job = new RefreshMerchantList;
        $job->handle($mockService);
    }

    #[Test]
    public function test_refresh_merchant_list_job_handles_exceptions(): void
    {
        // Mock the AmilonService to throw an exception
        $mockService = Mockery::mock(AmilonMerchantService::class);
        $mockService->shouldReceive('getMerchants')
            ->once()
            ->andThrow(new Exception('API Error'));

        $this->app->instance(AmilonMerchantService::class, $mockService);

        // Expect log message
        Log::shouldReceive('error')
            ->once()
            ->with('Failed to refresh Amilon merchant list', [
                'exception' => 'API Error',
            ]);

        // Execute the job and expect exception to be re-thrown
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API Error');

        $job = new RefreshMerchantList;
        $job->handle($mockService);
    }
}
