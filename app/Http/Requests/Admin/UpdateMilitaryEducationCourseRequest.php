<?php

namespace App\Http\Requests\Admin;

use App\Models\MilitaryEducationCourse;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMilitaryEducationCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female',
            'capacity' => 'nullable|integer|min:1',
            'fee_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,closed',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $course = $this->route('military_education_course');
            $gender = $this->gender ?? $course->gender;
            $status = $this->status ?? $course->status;

            if ($gender && $status === 'active') {
                $existingActive = MilitaryEducationCourse::where('gender', $gender)
                    ->where('status', 'active')
                    ->where('id', '!=', $course->id)
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
            'gender.in' => 'النوع يجب أن يكون ذكر أو أنثى',
            'capacity.integer' => 'السعة يجب أن تكون رقماً صحيحاً',
            'capacity.min' => 'السعة يجب أن تكون على الأقل 1',
            'fee_amount.numeric' => 'قيمة المصاريف يجب أن تكون رقماً',
            'fee_amount.min' => 'قيمة المصاريف يجب أن تكون على الأقل 0',
        ];
    }
}
