<?php

namespace App\Estimators\Contracts;

interface CreditEstimatorInterface
{
    public function estimate(string $prompt): int;
}
