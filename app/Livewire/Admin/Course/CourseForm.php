<?php

namespace App\Livewire\Admin\Course;

use App\Models\Course;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use App\Services\CoursePrerequisiteValidator;
use Illuminate\Support\Collection;
use Livewire\Component;

class CourseForm extends Component
{
    public ?Course $course = null;

    public $name = '';

    public $hours = '';

    public $department_id = '';

    public $level_id = '';

    public $semester = '';

    public $section_ids = [];

    public $prerequisite_ids = [];

    public $is_active = true;

    public $is_selected = false;

    public Collection $departments;

    public Collection $sections;

    public Collection $levels;

    public Collection $availablePrerequisites;

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'hours' => 'required|integer|min:1|max:10',
            'department_id' => 'required|exists:departments,id',
            'level_id' => 'required|exists:levels,id',
            'semester' => 'required|string|in:الأول,الثاني,الصيفي',
            'section_ids' => 'required|array|min:1',
            'section_ids.*' => 'exists:sections,id',
            'prerequisite_ids' => 'array',
            'prerequisite_ids.*' => 'exists:courses,id',
            'is_active' => 'boolean',
            'is_selected' => 'boolean',
        ];
    }

    protected $messages = [
        'name.required' => 'اسم المادة مطلوب',
        'hours.required' => 'عدد الساعات مطلوب',
        'department_id.required' => 'يجب اختيار التخصص',
        'department_id.exists' => 'التخصص المختار غير موجود',
        'level_id.required' => 'يجب اختيار الفرقة الدراسية',
        'semester.required' => 'يجب اختيار الفصل الدراسي',
        'section_ids.required' => 'يجب اختيار شعبة واحدة على الأقل',
        'section_ids.min' => 'يجب اختيار شعبة واحدة على الأقل',
    ];

    public function mount(?Course $course = null)
    {
        $this->departments = Department::all();
        $this->levels = Level::orderBy('id')->get();
        $this->sections = collect();
        $this->availablePrerequisites = collect();

        if ($course && $course->exists) {
            $this->course = $course;
            $this->name = $course->name;
            $this->hours = $course->hours;
            $this->department_id = $course->department_id;
            $this->level_id = $course->level_id;
            $this->semester = $course->semester;
            $this->is_active = $course->is_active;
            $this->is_selected = $course->is_selected;
            $this->section_ids = $course->sections->pluck('id')->toArray();
            $this->prerequisite_ids = $course->prerequisites->pluck('id')->toArray();

            $this->updatedDepartmentId($this->department_id);
        }

        $this->loadAvailablePrerequisites();
    }

    public function updatedDepartmentId($value)
    {
        if ($value) {
            $this->sections = Section::where('department_id', $value)->get();
        } else {
            $this->sections = collect();
        }
        $this->section_ids = [];
        $this->loadAvailablePrerequisites();
    }

    public function updatedPrerequisiteIds(): void
    {
        $this->loadAvailablePrerequisites();
    }

    public function loadAvailablePrerequisites(): void
    {
        $allCourses = Course::query()
            ->when($this->department_id, fn ($query) => $query->where('department_id', $this->department_id))
            ->orderBy('name')
            ->get();

        $validator = app(CoursePrerequisiteValidator::class);
        $courseId = $this->course?->id;

        $this->availablePrerequisites = $validator->availablePrerequisites(
            $allCourses,
            $this->prerequisite_ids,
            $courseId
        );
    }

    public function save()
    {
        if ($this->course) {
            abort_unless(auth()->user()->can('courses.edit'), 403);
        } else {
            abort_unless(auth()->user()->can('courses.create'), 403);
        }

        $validatedData = $this->validate();

        $courseId = $this->course?->id ?? 0;
        $validator = app(CoursePrerequisiteValidator::class);
        $prerequisiteError = $validator->validate($courseId, $this->prerequisite_ids);

        if ($prerequisiteError) {
            $this->addError('prerequisite_ids', $prerequisiteError);

            return;
        }

        if ($this->course) {
            if ($this->course->department_id != $this->department_id) {
                $department = Department::findOrFail($this->department_id);
                $validatedData['code'] = $this->generateUniqueCourseCode($department);
            }

            $this->course->update($validatedData);
            $this->course->sections()->sync($this->section_ids);
            $this->course->prerequisites()->sync($this->prerequisite_ids);

            session()->flash('success', 'تم تحديث المادة بنجاح');
        } else {
            $department = Department::findOrFail($this->department_id);
            $code = $this->generateUniqueCourseCode($department);
            $validatedData['code'] = $code;

            $course = Course::create($validatedData);
            $course->sections()->sync($this->section_ids);
            $course->prerequisites()->sync($this->prerequisite_ids);

            session()->flash('success', 'تم إضافة المادة بنجاح بكود: '.$code);
        }

        return redirect()->route('courses.index');
    }

    private function generateUniqueCourseCode(Department $department): string
    {
        $prefix = $this->extractCodePrefix($department->code);

        do {
            $code = $prefix.str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Course::where('code', $code)->exists());

        return $code;
    }

    private function extractCodePrefix(string $departmentCode): string
    {
        if (preg_match('/[A-Za-z]/', $departmentCode, $matches)) {
            return strtoupper($matches[0]);
        }

        $firstCharacter = mb_substr($departmentCode, 0, 1, 'UTF-8');

        return $firstCharacter !== '' ? $firstCharacter : 'C';
    }

    public function render()
    {
        return view('livewire.admin.course.course-form');
    }
}
