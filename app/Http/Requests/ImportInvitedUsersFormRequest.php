<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportInvitedUsersFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization will be handled by the RequiresPermission attribute
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required_without:csv_file|file|mimes:csv,txt,xls,xlsx|max:10240', // Max 10MB
            'csv_file' => 'required_without:file|file|mimes:csv,txt,xls,xlsx|max:10240', // Max 10MB
            'financer_id' => 'required|string|exists:financers,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required',
            'file.file' => 'The uploaded file must be a valid file',
            'file.mimes' => 'The file must be a CSV, TXT, XLS, or XLSX file',
            'file.max' => 'The file size must not exceed 10MB',
            'financer_id.required' => 'A financer ID is required',
            'financer_id.exists' => 'The selected financer does not exist',
        ];
    }
}
