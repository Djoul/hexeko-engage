<?php

namespace App\Actions\Financer;

use App\Models\Financer;
use App\Services\Models\FinancerService;
use Illuminate\Support\Facades\DB;

class CreateFinancerAction
{
    public function __construct(
        protected FinancerService $financerService
    ) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(array $validatedData): Financer
    {
        return DB::transaction(function () use ($validatedData): Financer {
            // Create the financer
            $financer = $this->financerService->create($validatedData);

            return $financer;
        });
    }
}
