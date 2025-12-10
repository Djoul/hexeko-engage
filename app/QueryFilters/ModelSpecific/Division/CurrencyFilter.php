<?php

namespace App\QueryFilters\ModelSpecific\Division;

use App\QueryFilters\Shared\CurrencyFilter as SharedCurrencyFilter;

class CurrencyFilter extends SharedCurrencyFilter
{
    // Automatically uses the 'currency' column thanks to CurrencyFilter logic
}
