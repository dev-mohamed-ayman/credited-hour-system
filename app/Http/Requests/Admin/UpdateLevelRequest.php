<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'exists:sections,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفرقة الدراسية مطلوب',
            'section_ids.array' => 'يجب أن تكون الشعب مصفوفة',
            'section_ids.*.exists' => 'إحدى الشعب المختارة غير موجودة',
        ];
    }
}
