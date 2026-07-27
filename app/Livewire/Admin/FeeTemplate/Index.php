<?php

namespace App\Livewire\Admin\FeeTemplate;

use App\Models\FeeTemplate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public $name = '';

    public $amount = '';

    public $is_active = true;

    public $editingId = null;

    public $showForm = false;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filterStatus = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:fee_templates,name,'.$this->editingId,
            'amount' => 'required|numeric|min:0.01',
            'is_active' => 'required|boolean',
        ];
    }

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->amount = '';
        $this->is_active = true;
        $this->editingId = null;
        $this->showForm = false;
        $this->resetErrorBag();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
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

    public function resetSearchFilters(): void
    {
        $this->reset(['search', 'filterStatus']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $template = FeeTemplate::findOrFail($id);

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->amount = (string) $template->amount;
        $this->is_active = $template->is_active;

        $this->showForm = true;
    }

    public function save(): void
    {
        if ($this->editingId) {
            abort_unless(auth()->user()->can('additional_fees.edit') || auth()->user()->can('finance.edit'), 403);
        } else {
            abort_unless(auth()->user()->can('additional_fees.create') || auth()->user()->can('finance.create'), 403);
        }

        $this->validate();

        $data = [
            'name' => trim($this->name),
            'amount' => round((float) $this->amount, 2),
            'is_active' => (bool) $this->is_active,
        ];

        if ($this->editingId) {
            $template = FeeTemplate::findOrFail($this->editingId);
            $template->update($data);
            $this->dispatch('success', 'تم تحديث قالب المصروف بنجاح.');
        } else {
            $data['created_by_user_id'] = auth()->id();
            FeeTemplate::create($data);
            $this->dispatch('success', 'تم إضافة قالب المصروف بنجاح.');
        }

        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()->can('additional_fees.edit') || auth()->user()->can('finance.edit'), 403);

        $template = FeeTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);

        $this->dispatch('success', 'تم تحديث الحالة بنجاح.');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('additional_fees.delete') || auth()->user()->can('finance.delete'), 403);

        $template = FeeTemplate::findOrFail($id);
        $template->delete();

        $this->dispatch('success', 'تم حذف قالب المصروف بنجاح.');
    }

    public function render()
    {
        $query = FeeTemplate::query()
            ->when($this->search, function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%');
            })
            ->when($this->filterStatus !== '', function ($q) {
                $q->where('is_active', $this->filterStatus === 'active');
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return view('livewire.admin.fee-template.index', [
            'feeTemplates' => $query->paginate($this->perPage),
        ])->extends('admin.layouts.app')->section('content');
    }
}
