<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel;

use App\AI\Clients\OpenAIStreamerClient;
use App\Integrations\Vouchers\Amilon\Services\AmilonContractService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

class DashboardPage extends Component
{
    public ?array $amilonContract = null;

    public bool $isLoadingContract = true;

    public ?string $contractError = null;

    public ?string $amilonLastFetch = null;

    public ?array $openAiBalance = null;

    public bool $isLoadingOpenAi = true;

    public ?string $openAiError = null;

    public ?string $openAiLastFetch = null;

    private const CACHE_TTL = 3600; // 60 minutes

    public function mount(): void
    {
        $this->fetchAmilon();
        $this->fetchOpenAiBalance();
    }

    public function fetchAmilon(bool $forceRefresh = false): void
    {
        $this->isLoadingContract = true;
        $this->contractError = null;

        $cacheKey = 'dashboard.amilon.contract';

        // Try to get from cache if not forcing refresh
        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && array_key_exists('data', $cached) && array_key_exists('timestamp', $cached)) {
                $this->amilonContract = $cached['data'];
                $this->amilonLastFetch = $cached['timestamp'];
                $this->isLoadingContract = false;

                return;
            }
        }

        try {
            $contractId = config('services.amilon.contrat_id');
            if (! is_string($contractId)) {
                throw new Exception('Contract ID not configured properly');
            }

            $contractService = app(AmilonContractService::class);
            $contract = $contractService->getContract($contractId);

            $this->amilonContract = $contract->toArray();
            $this->amilonLastFetch = now()->toIso8601String();

            // Cache for 60 minutes
            Cache::put($cacheKey, [
                'data' => $this->amilonContract,
                'timestamp' => $this->amilonLastFetch,
            ], self::CACHE_TTL);
        } catch (Exception $e) {
            $this->contractError = 'Unable to load contract';
            Log::error('Amilon contract fetch error in dashboard', [
                'exception' => $e,
            ]);
        } finally {
            $this->isLoadingContract = false;
        }
    }

    public function fetchOpenAiBalance(bool $forceRefresh = false): void
    {
        $this->isLoadingOpenAi = true;
        $this->openAiError = null;

        $cacheKey = 'dashboard.openai.balance';

        // Try to get from cache if not forcing refresh
        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && array_key_exists('data', $cached) && array_key_exists('timestamp', $cached)) {
                $this->openAiBalance = $cached['data'];
                $this->openAiLastFetch = $cached['timestamp'];
                $this->isLoadingOpenAi = false;

                return;
            }
        }

        try {
            $openAiClient = app(OpenAIStreamerClient::class);
            $balance = $openAiClient->getBalance();

            if ($balance === null) {
                $this->openAiError = 'Unable to fetch balance';
            } else {
                $this->openAiBalance = $balance;
                $this->openAiLastFetch = now()->toIso8601String();

                // Cache for 60 minutes
                Cache::put($cacheKey, [
                    'data' => $this->openAiBalance,
                    'timestamp' => $this->openAiLastFetch,
                ], self::CACHE_TTL);
            }
        } catch (Exception $e) {
            $this->openAiError = 'OpenAI API error';
            Log::error('OpenAI balance fetch error in dashboard', [
                'message' => $e->getMessage(),
            ]);
        } finally {
            $this->isLoadingOpenAi = false;
        }
    }

    #[Layout('admin-panel.layouts.livewire')]
    public function render(): View
    {
        return view('livewire.admin-panel.dashboard.index');
    }
}
