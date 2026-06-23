<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:grades,name',
            'is_pending_default' => 'boolean',
            'order' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التقييم مطلوب',
            'name.unique' => 'هذا التقييم موجود مسبقاً',
            'order.required' => 'ترتيب التقييم مطلوب',
        ];
    }
}
