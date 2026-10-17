<?php

namespace App\Http\Requests\Admin;

use App\Models\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grades', 'name')->ignore($this->route('grade')),
            ],
            'is_pending_default' => 'boolean',
            'order' => 'required|integer|min:0',
            'points' => 'nullable|required_if_accepted:counts_in_gpa|numeric|min:0|max:4',
            'min_score' => 'nullable|required_with:max_score|numeric|min:0|max:100',
            'max_score' => 'nullable|required_with:min_score|numeric|min:0|max:100|gte:min_score',
            'counts_in_gpa' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التقييم مطلوب',
            'name.unique' => 'هذا التقييم موجود مسبقاً',
            'order.required' => 'ترتيب التقييم مطلوب',
            'points.required_if_accepted' => 'النقاط مطلوبة للتقييم الذي يدخل في المعدل',
            'points.max' => 'النقاط يجب ألا تزيد عن 4',
            'min_score.required_with' => 'حدد بداية نطاق الدرجات',
            'max_score.required_with' => 'حدد نهاية نطاق الدرجات',
            'max_score.gte' => 'نهاية النطاق يجب أن تكون أكبر من أو تساوي بدايته',
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('min_score') === null || $this->input('max_score') === null) {
                    return;
                }

                $overlapping = Grade::overlappingRange((float) $this->input('min_score'), (float) $this->input('max_score'), $this->route('grade')?->id);

                if ($overlapping) {
                    $validator->errors()->add('min_score', "نطاق الدرجات يتداخل مع نطاق التقييم {$overlapping->name} ({$overlapping->min_score} - {$overlapping->max_score})");
                }
            },
        ];
    }
}
