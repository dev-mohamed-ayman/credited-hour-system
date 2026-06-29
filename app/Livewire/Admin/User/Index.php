<?php

namespace App\Livewire\Admin\User;

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 10)]
    public int $perPage = 10;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete($id): void
    {
        abort_unless(auth()->user()->can('users.delete'), 403);

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->dispatch('toast', ['message' => 'لا يمكنك حذف حسابك الشخصي.', 'type' => 'error']);

            return;
        }

        if ($user->is_super_admin && User::where('is_super_admin', true)->count() <= 1) {
            $this->dispatch('toast', ['message' => 'لا يمكن حذف آخر مدير نظام (Super Admin).', 'type' => 'error']);

            return;
        }

        if ($user->hasBlockingRelations()) {
            $this->dispatch('toast', ['message' => $user->getBlockingRelationsMessage(), 'type' => 'error']);

            return;
        }

        $user->delete();
        $this->dispatch('toast', ['message' => 'تم حذف المستخدم بنجاح.', 'type' => 'success']);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('users.view'), 403);

        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.user.index', [
            'users' => $users,
        ])->extends('admin.layouts.app')->section('content');
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
}
