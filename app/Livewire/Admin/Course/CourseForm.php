<?php

namespace App\Livewire\Admin\Course;

use App\Models\Course;
use App\Models\Department;
use App\Models\Section;
use Livewire\Component;
use Illuminate\Support\Collection;

class CourseForm extends Component
{
    public ?Course $course = null;
    
    public $name = '';
    public $hours = '';
    public $department_id = '';
    public $semester = '';
    public $section_ids = [];
    public $is_active = true;
    public $is_selected = false;

    public Collection $departments;
    public Collection $sections;

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'hours' => 'required|integer|min:1|max:10',
            'department_id' => 'required|exists:departments,id',
            'semester' => 'required|string|in:الأول,الثاني,الصيفي',
            'section_ids' => 'required|array|min:1',
            'section_ids.*' => 'exists:sections,id',
            'is_active' => 'boolean',
            'is_selected' => 'boolean',
        ];
    }

    protected $messages = [
        'name.required' => 'اسم المادة مطلوب',
        'hours.required' => 'عدد الساعات مطلوب',
        'department_id.required' => 'يجب اختيار التخصص',
        'department_id.exists' => 'التخصص المختار غير موجود',
        'semester.required' => 'يجب اختيار الفصل الدراسي',
        'section_ids.required' => 'يجب اختيار شعبة واحدة على الأقل',
        'section_ids.min' => 'يجب اختيار شعبة واحدة على الأقل',
    ];

    public function mount(?Course $course = null)
    {
        $this->departments = Department::all();
        $this->sections = collect();

        if ($course && $course->exists) {
            $this->course = $course;
            $this->name = $course->name;
            $this->hours = $course->hours;
            $this->department_id = $course->department_id;
            $this->semester = $course->semester;
            $this->is_active = $course->is_active;
            $this->is_selected = $course->is_selected;
            $this->section_ids = $course->sections->pluck('id')->toArray();
            
            $this->updatedDepartmentId($this->department_id);
        }
    }

    public function updatedDepartmentId($value)
    {
        if ($value) {
            $this->sections = Section::where('department_id', $value)->get();
        } else {
            $this->sections = collect();
        }
        $this->section_ids = [];
    }

    public function save()
    {
        $validatedData = $this->validate();

        if ($this->course) {
            // Update existing course
            if ($this->course->department_id != $this->department_id) {
                $department = Department::findOrFail($this->department_id);
                $prefix = strtoupper(substr($department->code, 0, 1));
                do {
                    $code = $prefix . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                } while (Course::where('code', $code)->exists());
                $validatedData['code'] = $code;
            }
            
            $this->course->update($validatedData);
            $this->course->sections()->sync($this->section_ids);
            
            session()->flash('success', 'تم تحديث المادة بنجاح');
        } else {
            // Create new course
            $department = Department::findOrFail($this->department_id);
            $prefix = strtoupper(substr($department->code, 0, 1));
            do {
                $code = $prefix . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            } while (Course::where('code', $code)->exists());
            
            $validatedData['code'] = $code;
            
            $course = Course::create($validatedData);
            $course->sections()->sync($this->section_ids);
            
            session()->flash('success', 'تم إضافة المادة بنجاح بكود: ' . $code);
        }

        return redirect()->route('courses.index');
    }

    public function render()
    {
        return view('livewire.admin.course.course-form');
    }
}
