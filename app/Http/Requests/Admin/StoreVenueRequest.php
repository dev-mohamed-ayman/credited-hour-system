<?php

namespace App\Http\Requests\Admin;

use App\Enums\VenueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:venues,name',
            'type' => ['required', Rule::enum(VenueType::class)],
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المكان مطلوب',
            'name.unique' => 'هذا الاسم مستخدم بالفعل',
            'type.required' => 'يجب اختيار نوع المكان',
            'type.enum' => 'نوع المكان غير صالح',
            'capacity.integer' => 'السعة يجب أن تكون رقماً',
            'capacity.min' => 'السعة يجب أن تكون رقماً موجباً',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
