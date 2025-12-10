<?php

namespace App\Http\Requests\Segment;

use App\Services\SegmentService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|uuid|exists:financers,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'filters' => 'sometimes|array',
            'filters.*.type' => 'required|string',
            'filters.*.operator' => 'required|string',
            'filters.*.value' => 'nullable',
            'filters.*.condition' => 'nullable|string|in:AND,OR',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $filters = $this->input('filters', []);

        $validator->after(function (Validator $validator) use ($filters): void {
            if ($validator->failed() || empty($filters)) {
                return;
            }

            $segmentService = app(SegmentService::class);
            $errors = $segmentService->validateFilters($filters);

            foreach ($errors as $error) {
                $validator->errors()->add('filters', $error);
            }
        });
    }
}
