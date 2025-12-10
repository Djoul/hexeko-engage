<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\MetricPeriod;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class FinancerMetricsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view_financer_metrics') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', new EnumValue(MetricPeriod::class)],
            'refresh' => ['sometimes', 'boolean'],
            // TODO: Sera ajouté après MVP
            // 'from' => ['required_if:period,custom', 'date_format:Y-m-d'],
            // 'to' => ['required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $validPeriods = implode(', ', MetricPeriod::getValidPeriods());

        return [
            'period' => "The period must be one of: {$validPeriods}.",
            'from.required_if' => 'The from date is required when period is custom.',
            'to.required_if' => 'The to date is required when period is custom.',
            'to.after_or_equal' => 'The to date must be after or equal to the from date.',
        ];
    }

    /**
     * Get the period with default value.
     */
    public function getPeriod(): string
    {
        $period = $this->input('period', MetricPeriod::getDefault());

        return is_string($period) ? $period : MetricPeriod::getDefault();
    }

    /**
     * Check if force refresh is requested.
     */
    public function shouldForceRefresh(): bool
    {
        return (bool) $this->input('refresh', false);
    }
}
