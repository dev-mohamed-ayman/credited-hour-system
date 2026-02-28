<?php

namespace App\Livewire\Admin\AcademicAdvisor;

use Livewire\Component;

use App\Models\AcademicAdvisor;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class Form extends Component
{
    public ?AcademicAdvisor $advisor = null;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|string|max:255|unique:academic_advisors,username')]
    public $username = '';

    #[Validate('required|integer|min:0')]
    public $max_students = 50;

    public $assignments = [];
    public $selectedDepartments = [];
    public $selectedSections = [];
    public $selectedLevels = [];

    public function mount(?AcademicAdvisor $advisor = null)
    {
        if ($advisor && $advisor->exists) {
            $this->advisor = $advisor;
            $this->name = $advisor->name;
            $this->username = $advisor->username;
            $this->max_students = $advisor->max_students;

            $this->selectedDepartments = $advisor->assignments->pluck('department_id')->unique()->map(fn($id) => $id ?? 'all')->toArray();
            $this->selectedSections = $advisor->assignments->pluck('section_id')->unique()->map(fn($id) => $id ?? 'all')->toArray();
            $this->selectedLevels = $advisor->assignments->pluck('level_id')->unique()->map(fn($id) => $id ?? 'all')->toArray();
        } else {
            $this->selectedDepartments = [];
            $this->selectedSections = [];
            $this->selectedLevels = [];
        }
    }

    #[Computed]
    public function departments()
    {
        return Department::all();
    }

    #[Computed]
    public function availableSections()
    {
        if (in_array('all', $this->selectedDepartments)) {
            return Section::all();
        }
        return Section::whereIn('department_id', $this->selectedDepartments)->get();
    }

    #[Computed]
    public function availableLevels()
    {
        if (in_array('all', $this->selectedSections)) {
            return Level::all();
        }
        return Level::whereHas('sections', function($query) {
            $query->whereIn('sections.id', $this->selectedSections);
        })->get();
    }

    public function updatedSelectedDepartments()
    {
        if (end($this->selectedDepartments) === 'all') {
            $this->selectedDepartments = ['all'];
        } elseif (in_array('all', $this->selectedDepartments)) {
            $this->selectedDepartments = array_values(array_filter($this->selectedDepartments, fn($v) => $v !== 'all'));
        }
        $this->selectedSections = [];
        $this->selectedLevels = [];
    }

    public function updatedSelectedSections()
    {
        if (end($this->selectedSections) === 'all') {
            $this->selectedSections = ['all'];
        } elseif (in_array('all', $this->selectedSections)) {
            $this->selectedSections = array_values(array_filter($this->selectedSections, fn($v) => $v !== 'all'));
        }
        $this->selectedLevels = [];
    }

    public function updatedSelectedLevels()
    {
        if (end($this->selectedLevels) === 'all') {
            $this->selectedLevels = ['all'];
        } elseif (in_array('all', $this->selectedLevels)) {
            $this->selectedLevels = array_values(array_filter($this->selectedLevels, fn($v) => $v !== 'all'));
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:academic_advisors,username,' . ($this->advisor?->id ?? 'NULL'),
            'max_students' => 'required|integer|min:0',
            'selectedDepartments' => 'required|array|min:1',
            'selectedSections' => 'required|array|min:1',
            'selectedLevels' => 'required|array|min:1',
        ]);

        $advisor = $this->advisor ?? new AcademicAdvisor();
        $advisor->fill([
            'name' => $this->name,
            'username' => $this->username,
            'max_students' => $this->max_students,
        ]);
        $advisor->save();

        $advisor->assignments()->delete();

        $departments = in_array('all', $this->selectedDepartments) ? [null] : $this->selectedDepartments;
        $sections = in_array('all', $this->selectedSections) ? [null] : $this->selectedSections;
        $levels = in_array('all', $this->selectedLevels) ? [null] : $this->selectedLevels;

        foreach ($departments as $deptId) {
            foreach ($sections as $sectId) {
                foreach ($levels as $lvlId) {
                    $advisor->assignments()->create([
                        'department_id' => $deptId,
                        'section_id' => $sectId,
                        'level_id' => $lvlId,
                    ]);
                }
            }
        }

        session()->flash('success', 'تم حفظ المرشد الأكاديمي بنجاح');
        return redirect()->route('academic-advisors.index');
    }

    public function render()
    {
        return view('livewire.admin.academic-advisor.form');
    }
}
