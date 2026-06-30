<?php

namespace App\Livewire\Admin\Course;

use App\Models\Course;
use App\Models\Department;
use App\Models\Level;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search = '';
    public $department_id = '';
    public $level_id = '';
    public $semester = '';
    public $is_active = '';
    public $is_selected = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'department_id' => ['except' => ''],
        'level_id' => ['except' => ''],
        'semester' => ['except' => ''],
        'is_active' => ['except' => ''],
        'is_selected' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingDepartmentId()
    {
        $this->resetPage();
    }

    public function updatingLevelId()
    {
        $this->resetPage();
    }

    public function updatingSemester()
    {
        $this->resetPage();
    }

    public function updatingIsActive()
    {
        $this->resetPage();
    }

    public function updatingIsSelected()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'department_id', 'level_id', 'semester', 'is_active', 'is_selected']);
        $this->resetPage();
    }

    public function toggleActive($courseId)
    {
        abort_unless(auth()->user()->can('courses.edit'), 403);
        $course = Course::findOrFail($courseId);
        $course->update(['is_active' => !$course->is_active]);
        $this->dispatch('toast', ['message' => 'تم تغيير حالة التفعيل بنجاح', 'type' => 'success']);
    }

    public function toggleSelected($courseId)
    {
        abort_unless(auth()->user()->can('courses.edit'), 403);
        $course = Course::findOrFail($courseId);
        $course->update(['is_selected' => !$course->is_selected]);
        $this->dispatch('toast', ['message' => 'تم تغيير حالة الاختياري بنجاح', 'type' => 'success']);
    }

    public function render()
    {
        $courses = Course::with(['department', 'level', 'sections'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->department_id, function ($query) {
                $query->where('department_id', $this->department_id);
            })
            ->when($this->level_id, function ($query) {
                $query->where('level_id', $this->level_id);
            })
            ->when($this->semester, function ($query) {
                $query->where('semester', $this->semester);
            })
            ->when($this->is_active !== '', function ($query) {
                $query->where('is_active', $this->is_active);
            })
            ->when($this->is_selected !== '', function ($query) {
                $query->where('is_selected', $this->is_selected);
            })
            ->latest()
            ->paginate(15);

        return view('livewire.admin.course.index', [
            'courses' => $courses,
            'departments' => Department::all(),
            'levels' => Level::orderBy('id')->get(),
        ]);
    }
}
