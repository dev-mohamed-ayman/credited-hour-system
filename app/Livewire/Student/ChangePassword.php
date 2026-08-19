<?php

namespace App\Livewire\Student;

use Closure;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class ChangePassword extends Component
{
    /**
     * Special characters accepted inside a password.
     */
    public const SPECIAL_CHARACTERS = '@_#$%/*-+?.';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
                $this->passwordComplexityRule(),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة',
            'password.required' => 'كلمة المرور الجديدة مطلوبة',
            'password.min' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق',
            'password.different' => 'يجب أن تختلف كلمة المرور الجديدة عن كلمة المرور الحالية',
        ];
    }

    public function updatePassword(): void
    {
        $this->validate();

        $student = auth('student')->user();

        if (! Hash::check($this->current_password, $student->password)) {
            $this->addError('current_password', 'كلمة المرور الحالية غير صحيحة');

            return;
        }

        $student->update([
            'password' => $this->password,
            'plain_password' => $this->password,
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        $this->dispatch('toast', message: 'تم تغيير كلمة المرور بنجاح', type: 'success');
    }

    public function render()
    {
        return view('livewire.student.change-password')
            ->extends('student.layouts.app')
            ->section('content');
    }

    /**
     * Ensure the password mixes upper case, lower case, digits and one allowed special character.
     */
    protected function passwordComplexityRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $specials = preg_quote(self::SPECIAL_CHARACTERS, '/');

            if (! preg_match('/[A-Z]/', (string) $value)) {
                $fail('يجب أن تحتوي كلمة المرور على حرف كبير واحد على الأقل (A - Z)');
            }

            if (! preg_match('/[a-z]/', (string) $value)) {
                $fail('يجب أن تحتوي كلمة المرور على حرف صغير واحد على الأقل (a - z)');
            }

            if (! preg_match('/[0-9]/', (string) $value)) {
                $fail('يجب أن تحتوي كلمة المرور على رقم واحد على الأقل (0 - 9)');
            }

            if (! preg_match('/['.$specials.']/', (string) $value)) {
                $fail('يجب أن تحتوي كلمة المرور على حرف خاص واحد على الأقل من ('.self::SPECIAL_CHARACTERS.')');
            }

            if (preg_match('/[^A-Za-z0-9'.$specials.']/', (string) $value)) {
                $fail('كلمة المرور تحتوي على رموز غير مسموح بها، المسموح فقط: حروف إنجليزية وأرقام والرموز ('.self::SPECIAL_CHARACTERS.')');
            }
        };
    }
}
