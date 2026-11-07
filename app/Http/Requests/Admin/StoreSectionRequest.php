<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => 'required|exists:departments,id',
            'name' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'department_id.required' => 'يجب اختيار التخصص',
            'department_id.exists' => 'التخصص المختار غير موجود',
            'name.required' => 'اسم الشعبة مطلوب',
        ];
    }
}
