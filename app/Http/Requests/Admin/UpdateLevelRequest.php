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
            'promotion_hours' => 'nullable|integer|min:1|max:500',
            'section_ids' => 'nullable|array',
            'section_ids.*' => 'exists:sections,id',
            'military_required_for_males' => [
                'nullable',
                'boolean',
                function ($attribute, $value, $fail) {
                    if ($value && \App\Models\Level::where('military_required_for_males', true)->where('id', '!=', $this->route('level')->id)->exists()) {
                        $fail('التربية العسكرية للذكور محددة بالفعل لفرقة أخرى');
                    }
                },
            ],
            'military_required_for_females' => [
                'nullable',
                'boolean',
                function ($attribute, $value, $fail) {
                    if ($value && \App\Models\Level::where('military_required_for_females', true)->where('id', '!=', $this->route('level')->id)->exists()) {
                        $fail('التربية العسكرية للإناث محددة بالفعل لفرقة أخرى');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'military_required_for_males' => $this->boolean('military_required_for_males'),
            'military_required_for_females' => $this->boolean('military_required_for_females'),
        ]);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفرقة الدراسية مطلوب',
            'promotion_hours.integer' => 'ساعات الترقية لازم تكون رقم صحيح',
            'promotion_hours.min' => 'ساعات الترقية لازم تكون ساعة واحدة على الأقل',
            'promotion_hours.max' => 'ساعات الترقية لا يمكن أن تزيد عن 500',
            'section_ids.array' => 'يجب أن تكون الشعب مصفوفة',
            'section_ids.*.exists' => 'إحدى الشعب المختارة غير موجودة',
        ];
    }
}
