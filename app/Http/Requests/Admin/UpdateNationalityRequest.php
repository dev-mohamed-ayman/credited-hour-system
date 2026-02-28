<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNationalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:nationalities,name,'.$this->nationality->id,
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الجنسية مطلوب',
            'name.unique' => 'هذه الجنسية موجودة مسبقاً',
        ];
    }
}
