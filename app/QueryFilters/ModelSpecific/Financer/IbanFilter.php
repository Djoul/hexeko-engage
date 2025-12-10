<?php

namespace App\QueryFilters\ModelSpecific\Financer;

use App\QueryFilters\Shared\TextFilter;

class IbanFilter extends TextFilter
{
    // Automatically uses the 'iban' column thanks to TextFilter logic
}
