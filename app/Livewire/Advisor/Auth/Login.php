<?php

namespace App\Livewire\Advisor\Auth;

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
            'username.required' => 'اسم المستخدم مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
        ];
    }

    public function login(): void
    {
        $this->validate();

        if (! Auth::guard('advisor')->attempt(['username' => $this->username, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            $this->dispatch('toast', message: 'بيانات الدخول غير صحيحة أو الحساب غير مفعل', type: 'error');

            return;
        }

        session()->regenerate();

        $this->redirect(route('advisor.dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.advisor.auth.login')
            ->extends('auth.layout')
            ->section('content');
    }
}
