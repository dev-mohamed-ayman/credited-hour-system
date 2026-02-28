<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'total_score' => 'required|numeric|min:0',
            'requirement_ids' => 'nullable|array',
            'requirement_ids.*' => 'exists:department_requirements,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الشهادة',
            'total_score' => 'المجموع الكلي',
            'requirement_ids' => 'متطلبات الأقسام',
        ];
    }
}
