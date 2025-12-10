<?php

namespace App\Integrations\Survey\Http\Requests\Survey;

use App\Integrations\Survey\Enums\SurveyStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|uuid',
            'status' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'order-by' => 'sometimes|string',
            'order-by-desc' => 'sometimes|string',
            'created_at' => 'sometimes|date',
            'updated_at' => 'sometimes|date',
            'deleted_at' => 'sometimes|date',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('status');

            if ($status === null) {
                return;
            }

            if (! is_string($status)) {
                $validator->errors()->add('status', 'The status must be a string.');

                return;
            }

            // Split by comma and validate each status
            $statuses = array_map('trim', explode(',', $status));
            $statuses = array_filter($statuses, fn (string $s): bool => $s !== '');

            if ($statuses === []) {
                $validator->errors()->add('status', 'The status field must contain at least one valid status.');

                return;
            }

            $validStatuses = SurveyStatusEnum::getAllValues();

            foreach ($statuses as $statusValue) {
                if (! in_array($statusValue, $validStatuses, true)) {
                    $validator->errors()->add('status', "The status '{$statusValue}' is not valid. Valid statuses are: ".implode(', ', $validStatuses));

                    return;
                }
            }
        });
    }
}
