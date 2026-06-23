<?php

namespace App\Livewire\Admin\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?User $user = null;

    public bool $isEdit = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public bool $is_super_admin = false;

    public array $selectedPermissions = [];

    // For copy permissions dropdown
    public string $selectedUserIdToCopyFrom = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->user?->id),
            ],
            'password' => $this->isEdit ? 'nullable|min:6' : 'required|min:6',
            'is_super_admin' => 'boolean',
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'string|exists:permissions,name',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'is_super_admin' => 'مدير النظام',
            'selectedPermissions' => 'الصلاحيات',
        ];
    }

    public function mount(?User $user = null): void
    {
        if ($user && $user->exists) {
            $this->user = $user;
            $this->isEdit = true;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->is_super_admin = (bool) $user->is_super_admin;
            $this->selectedPermissions = $user->permissions->pluck('name')->toArray();
        }
    }

    public function updatedSelectedUserIdToCopyFrom($value): void
    {
        if ($value) {
            $sourceUser = User::find($value);
            if ($sourceUser) {
                if ($sourceUser->is_super_admin) {
                    // Collect all permissions from config
                    $allPermissions = [];
                    foreach (config('permissions') as $moduleKey => $module) {
                        foreach ($module['actions'] as $actionKey => $actionLabel) {
                            $allPermissions[] = "{$moduleKey}.{$actionKey}";
                        }
                    }
                    $this->selectedPermissions = $allPermissions;
                } else {
                    $this->selectedPermissions = $sourceUser->permissions->pluck('name')->toArray();
                }
                $this->dispatch('toast', ['message' => 'تم نسخ الصلاحيات من المستخدم بنجاح.', 'type' => 'success']);
            }
            // Reset selection dropdown
            $this->selectedUserIdToCopyFrom = '';
        }
    }

    public function toggleAllPermissions(bool $checked): void
    {
        if ($this->is_super_admin) {
            return;
        }

        if ($checked) {
            $allPermissions = [];
            foreach (config('permissions') as $moduleKey => $module) {
                foreach ($module['actions'] as $actionKey => $actionLabel) {
                    $allPermissions[] = "{$moduleKey}.{$actionKey}";
                }
            }
            $this->selectedPermissions = $allPermissions;
        } else {
            $this->selectedPermissions = [];
        }
    }

    public function toggleModulePermissions(string $moduleKey, bool $checked): void
    {
        if ($this->is_super_admin) {
            return;
        }

        $module = config("permissions.{$moduleKey}");
        if (! $module) {
            return;
        }

        $modulePermissions = [];
        foreach ($module['actions'] as $actionKey => $actionLabel) {
            $modulePermissions[] = "{$moduleKey}.{$actionKey}";
        }

        if ($checked) {
            $this->selectedPermissions = array_unique(array_merge($this->selectedPermissions, $modulePermissions));
        } else {
            $this->selectedPermissions = array_diff($this->selectedPermissions, $modulePermissions);
        }
    }

    public function save()
    {
        if ($this->isEdit) {
            abort_unless(auth()->user()->can('users.edit'), 403);
        } else {
            abort_unless(auth()->user()->can('users.create'), 403);
        }

        $this->validate();

        // Security check: only super admins can manage/set super admin status
        if (! auth()->user()->is_super_admin) {
            $this->is_super_admin = false;
        }

        // Additional safeguard for last super admin
        if ($this->isEdit && ! $this->is_super_admin && $this->user->is_super_admin) {
            if (User::where('is_super_admin', true)->count() <= 1) {
                $this->addError('is_super_admin', 'يجب أن يبقى مدير نظام (Super Admin) واحد على الأقل.');

                return;
            }
        }

        $userData = [
            'name' => $this->name,
            'email' => $this->email,
            'is_super_admin' => $this->is_super_admin,
        ];

        if ($this->password) {
            $userData['password'] = Hash::make($this->password);
        }

        if ($this->isEdit) {
            $this->user->update($userData);
            $user = $this->user;
            $this->dispatch('toast', ['message' => 'تم تحديث بيانات المستخدم بنجاح.', 'type' => 'success']);
        } else {
            $user = User::create($userData);
            $this->dispatch('toast', ['message' => 'تم إنشاء المستخدم بنجاح.', 'type' => 'success']);
        }

        // Sync Spatie Direct Permissions (not Roles, as we use direct permissions as requested)
        if ($user->is_super_admin) {
            $user->syncPermissions([]); // Super Admin bypasses with Gate::before
        } else {
            $user->syncPermissions($this->selectedPermissions);
        }

        return redirect()->route('users.index');
    }

    public function render()
    {
        if ($this->isEdit) {
            abort_unless(auth()->user()->can('users.edit'), 403);
        } else {
            abort_unless(auth()->user()->can('users.create'), 403);
        }

        $otherUsers = User::query()
            ->when($this->isEdit, fn ($q) => $q->where('id', '!=', $this->user->id))
            ->get();

        return view('livewire.admin.user.form', [
            'otherUsers' => $otherUsers,
            'modules' => config('permissions'),
        ])->extends('admin.layouts.app')->section('content');
    }
}
