<?php

namespace App\Livewire\Student\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'username' => 'required',
            'password' => 'required',
        ];
    }

    protected function messages(): array
    {
        return [
            'username.required' => 'الكود الجامعي مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
        ];
    }

    public function login(): void
    {
        $this->validate();

        if (! Auth::guard('student')->attempt(['username' => $this->username, 'password' => $this->password], $this->remember)) {
            $this->dispatch('toast', message: 'بيانات الدخول غير صحيحة', type: 'error');

            return;
        }

        session()->regenerate();

        $this->redirect(route('student.dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.student.auth.login')
            ->extends('auth.layout')
            ->section('content');
    }
}
