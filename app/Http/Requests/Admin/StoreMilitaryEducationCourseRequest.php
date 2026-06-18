<?php

namespace App\Http\Requests\Admin;

use App\Models\MilitaryEducationCourse;
use Illuminate\Foundation\Http\FormRequest;

class StoreMilitaryEducationCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'capacity' => 'required|integer|min:1',
            'fee_amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:active,closed',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->gender && $this->status !== 'closed') {
                $existingActive = MilitaryEducationCourse::where('gender', $this->gender)
                    ->where('status', 'active')
                    ->exists();

                if ($existingActive) {
                    $validator->errors()->add('gender', 'يوجد دورة تربية عسكرية مفتوحة بالفعل لهذا النوع');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الدورة مطلوب',
            'gender.required' => 'النوع مطلوب',
            'gender.in' => 'النوع يجب أن يكون ذكر أو أنثى',
            'capacity.required' => 'السعة مطلوبة',
            'capacity.integer' => 'السعة يجب أن تكون رقماً صحيحاً',
            'capacity.min' => 'السعة يجب أن تكون على الأقل 1',
            'fee_amount.required' => 'قيمة المصاريف مطلوبة',
            'fee_amount.numeric' => 'قيمة المصاريف يجب أن تكون رقماً',
            'fee_amount.min' => 'قيمة المصاريف يجب أن تكون على الأقل 0',
        ];
    }
}
