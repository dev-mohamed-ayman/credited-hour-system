<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'required|string|regex:/^\d{4}\/\d{4}$/|unique:years,year,' . $this->year->id,
        ];
    }

    public function messages(): array
    {
        return [
            'year.required' => 'السنة الدراسية مطلوبة',
            'year.regex' => 'تنسيق السنة الدراسية يجب أن يكون مثل 2026/2027',
            'year.unique' => 'هذه السنة الدراسية موجودة مسبقاً',
        ];
    }
}
