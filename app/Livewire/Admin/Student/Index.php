<?php

namespace App\Livewire\Admin\Student;

use App\Models\CertificateType;
use App\Models\Department;
use App\Models\Level;
use App\Models\Nationality;
use App\Models\Section;
use App\Models\Student;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public $search = '';

    #[Url(except: 10)]
    public $perPage = 10;

    #[Url(except: '')]
    public $department_id = '';

    #[Url(except: '')]
    public $section_id = '';

    #[Url(except: '')]
    public $level_id = '';

    #[Url(except: '')]
    public $gender = '';

    #[Url(except: '')]
    public $nationality_id = '';

    #[Url(except: '')]
    public $certificate_type_id = '';

    #[Url(except: '')]
    public $application_category = '';

    #[Url(except: '')]
    public $status = '';

    #[Url(except: '')]
    public $study_status = '';

    #[Url(except: '')]
    public $academic_advisor_id = '';

    public array $selectedColumns = ['name', 'username', 'national_id', 'score', 'status'];

    public array $availableColumns = [
        ['key' => 'name', 'label' => 'الطالب'],
        ['key' => 'username', 'label' => 'كود المستخدم'],
        ['key' => 'national_id', 'label' => 'الرقم القومي'],
        ['key' => 'score', 'label' => 'المجموع'],
        ['key' => 'status', 'label' => 'الحالة'],
        ['key' => 'email', 'label' => 'البريد الإلكتروني'],
        ['key' => 'phone', 'label' => 'رقم الهاتف'],
        ['key' => 'gender', 'label' => 'الجنس'],
        ['key' => 'level', 'label' => 'الفرقة'],
        ['key' => 'section', 'label' => 'الشعبة'],
        ['key' => 'academic_advisor', 'label' => 'المرشد الأكاديمي'],
        ['key' => 'plain_password', 'label' => 'كلمة السر'],
        ['key' => 'national_id_place', 'label' => 'جهة اصدار الرقم القومي'],
        ['key' => 'nationality', 'label' => 'الجنسية'],
        ['key' => 'country', 'label' => 'دولة الميلاد'],
        ['key' => 'city', 'label' => 'محافظة الميلاد'],
        ['key' => 'birth_date', 'label' => 'تاريخ الميلاد'],
        ['key' => 'certificate_type', 'label' => 'الشهادة الحاصل عليها'],
        ['key' => 'seat_number', 'label' => 'رقم جلوس الشهادة'],
        ['key' => 'student_scores', 'label' => 'درجات الطالب'],
        ['key' => 'total_score', 'label' => 'اجمالي المجموع الدرجات'],
        ['key' => 'english_score', 'label' => 'درجة اللغة الانجليزية'],
        ['key' => 'graduation_date', 'label' => 'تاريخ الحصول علي الشهادة'],
        ['key' => 'enrollment_date', 'label' => 'تاريخ قيد الطالب بالمعهد'],
        ['key' => 'application_category', 'label' => 'تصنيف التقديم'],
        ['key' => 'religion', 'label' => 'الديانة'],
        ['key' => 'address', 'label' => 'العنوان'],
        ['key' => 'landline_phone', 'label' => 'التليفون الارضي'],
        ['key' => 'guardian_job', 'label' => 'مهنة ولي الامر'],
        ['key' => 'guardian_phone_1', 'label' => 'تليفون ولي الامر'],
        ['key' => 'guardian_phone_2', 'label' => 'تليفون ولي الامر الثاني'],
        ['key' => 'study_status', 'label' => 'تصنيف الطالب'],
        ['key' => 'department', 'label' => 'التخصص'],
        ['key' => 'is_foreign', 'label' => 'طالب وافد'],
        ['key' => 'status_notes', 'label' => 'بيانات اخري'],
        ['key' => 'military_education_passed', 'label' => 'التربية العسكرية'],
        ['key' => 'year', 'label' => 'السنة الدراسية'],
        ['key' => 'semester', 'label' => 'الترم'],
    ];

    public $showFilters = false;

    public function mount()
    {
        if (session()->has('student_columns')) {
            $this->selectedColumns = session('student_columns');
        }
    }

    public function updatedSelectedColumns()
    {
        session(['student_columns' => $this->selectedColumns]);
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'department_id',
            'section_id',
            'level_id',
            'gender',
            'nationality_id',
            'certificate_type_id',
            'application_category',
            'status',
            'study_status',
            'academic_advisor_id',
        ]);
        $this->resetPage();
    }

    public function updated($property): void
    {
        if (in_array($property, [
            'search', 'perPage', 'department_id', 'section_id', 'level_id',
            'gender', 'nationality_id', 'certificate_type_id',
            'application_category', 'status', 'study_status', 'academic_advisor_id',
        ])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function departments()
    {
        return Department::all();
    }

    #[Computed]
    public function sections()
    {
        return $this->department_id ? Section::where('department_id', $this->department_id)->get() : Section::all();
    }

    #[Computed]
    public function levels()
    {
        return Level::all();
    }

    #[Computed]
    public function nationalities()
    {
        return Nationality::all();
    }

    #[Computed]
    public function certificateTypes()
    {
        return CertificateType::all();
    }

    #[Computed]
    public function academicAdvisors()
    {
        return \App\Models\AcademicAdvisor::all();
    }

    public function render()
    {
        $students = Student::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('username', 'like', '%'.$this->search.'%')
                        ->orWhere('national_id', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('phone', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->department_id, function ($query) {
                $query->whereHas('section', function ($q) {
                    $q->where('department_id', $this->department_id);
                });
            })
            ->when($this->section_id, function ($query) {
                $query->where('section_id', $this->section_id);
            })
            ->when($this->level_id, function ($query) {
                $query->where('level_id', $this->level_id);
            })
            ->when($this->gender, function ($query) {
                $query->where('gender', $this->gender);
            })
            ->when($this->nationality_id, function ($query) {
                $query->where('nationality_id', $this->nationality_id);
            })
            ->when($this->certificate_type_id, function ($query) {
                $query->where('certificate_type_id', $this->certificate_type_id);
            })
            ->when($this->application_category, function ($query) {
                $query->where('application_category', $this->application_category);
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->study_status, function ($query) {
                $query->where('study_status', $this->study_status);
            })
            ->when($this->academic_advisor_id, function ($query) {
                $query->where('academic_advisor_id', $this->academic_advisor_id);
            })
            ->with(['level', 'section.department', 'academicAdvisor', 'country', 'city', 'nationality', 'certificateType', 'scores.requirement', 'year'])
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.admin.student.index', [
            'students' => $students,
        ]);
    }

    public function delete($id): void
    {
        abort_unless(auth()->user()->can('students.delete'), 403);

        $student = Student::findOrFail($id);
        if ($student->academicAdvisor) {
            $student->academicAdvisor->decrement('current_students');
        }
        $student->delete();
        $this->dispatch('toast', ['message' => 'تم حذف الطالب بنجاح', 'type' => 'success']);
    }
}
