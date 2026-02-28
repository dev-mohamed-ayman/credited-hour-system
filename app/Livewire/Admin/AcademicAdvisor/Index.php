<?php

namespace App\Livewire\Admin\AcademicAdvisor;

use Livewire\Component;

use App\Models\AcademicAdvisor;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public $search = '';

    #[Url(except: 10)]
    public $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete($id): void
    {
        AcademicAdvisor::findOrFail($id)->delete();
        $this->dispatch('toast', ['message' => 'تم حذف المرشد الأكاديمي بنجاح', 'type' => 'success']);
    }

    public function toggleStatus($id): void
    {
        $advisor = AcademicAdvisor::findOrFail($id);
        $advisor->update(['is_active' => ! $advisor->is_active]);
        $this->dispatch('toast', ['message' => 'تم تغيير حالة المرشد الأكاديمي بنجاح', 'type' => 'success']);
    }

    public function render()
    {
        $advisors = AcademicAdvisor::query()
            ->with(['assignments.department', 'assignments.section', 'assignments.level'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('username', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.admin.academic-advisor.index', [
            'advisors' => $advisors,
        ]);
    }
}
