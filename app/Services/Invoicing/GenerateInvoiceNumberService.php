<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class GenerateInvoiceNumberService
{
    private const DEFAULT_PATTERN = '{type}-{year}-{sequence}';

    private const DEFAULT_PADDING = 6;

    public function generate(string $invoiceType, Carbon $date): string
    {
        $pattern = Config::get('invoicing.invoice_number.pattern', self::DEFAULT_PATTERN);
        $padding = (int) Config::get('invoicing.invoice_number.sequence_padding', self::DEFAULT_PADDING);
        $typeToken = $this->resolveTypeToken($invoiceType);
        $year = $date->format('Y');

        return DB::transaction(function () use ($invoiceType, $year, $pattern, $padding, $typeToken): string {
            do {
                $sequence = $this->reserveSequence($invoiceType, $year);

                $invoiceNumber = str_replace(
                    ['{type}', '{year}', '{sequence}'],
                    [$typeToken, $year, str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT)],
                    $pattern,
                );
            } while ($this->invoiceNumberExists($invoiceNumber));

            return $invoiceNumber;
        });
    }

    private function reserveSequence(string $invoiceType, string $year): int
    {
        $record = DB::table('invoice_sequences')
            ->where('invoice_type', $invoiceType)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            DB::table('invoice_sequences')->insert([
                'invoice_type' => $invoiceType,
                'year' => $year,
                'sequence' => 1,
                'created_at' => Date::now(),
                'updated_at' => Date::now(),
            ]);

            return 1;
        }

        $nextSequence = (int) $record->sequence + 1;

        DB::table('invoice_sequences')
            ->where('id', $record->id)
            ->update([
                'sequence' => $nextSequence,
                'updated_at' => Date::now(),
            ]);

        return $nextSequence;
    }

    private function invoiceNumberExists(string $invoiceNumber): bool
    {
        return Invoice::query()->where('invoice_number', $invoiceNumber)->exists();
    }

    private function resolveTypeToken(string $invoiceType): string
    {
        $mapping = Config::get('invoicing.invoice_number.type_mapping', []);

        if (isset($mapping[$invoiceType])) {
            return strtoupper((string) $mapping[$invoiceType]);
        }

        return strtoupper(str_replace('-', '_', $invoiceType));
    }
}
