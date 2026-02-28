<?php

namespace App\Livewire\Admin\Student;

use App\Enums\Student\ApplicationCategory;
use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudyStatus;
use App\Models\CertificateType;
use App\Models\City;
use App\Models\Country;
use App\Models\Department;
use App\Models\Level;
use App\Models\Nationality;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentScore;
use App\Models\AcademicAdvisor;
use App\Models\AcademicAdvisorAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    // Student Basic Info
    public $name = '';

    public $religion = 'مسلم';

    public $gender = 'male';

    public $image;

    public $birth_date;

    public $country_id;

    public $city_id;

    public $nationality_id;

    public $address;

    public $is_foreign = false;

    public $national_id;

    public $national_id_place;

    public $email;

    public $phone;

    public $landline_phone;

    public $guardian_job;

    public $guardian_phone_1;

    public $guardian_phone_2;

    // Certificate Info
    public $certificate_type_id;

    public $graduation_date;

    public $seat_number;

    public $score;

    public $requirements = [];

    // Application Info
    public $application_category;

    public $status;

    public $status_notes;

    public $department_id;

    public $section_id;

    public $level_id;

    public $study_status;

    public $username;

    public $password;

    public $showFullForm = false;

    public function toggleFullForm()
    {
        $this->showFullForm = !$this->showFullForm;
    }

    public function mount()
    {
        $this->password = Str::random(8);
        $this->application_category = ApplicationCategory::DIRECT->value;
        $this->status = StudentStatus::REGISTERED->value;
        $this->study_status = StudyStatus::FRESHMAN->value;
    }

    #[Computed]
    public function nationalities()
    {
        return Nationality::all();
    }

    #[Computed]
    public function countries()
    {
        return Country::all();
    }

    #[Computed]
    public function cities()
    {
        return $this->country_id ? City::where('country_id', $this->country_id)->get() : collect();
    }

    public function updatedCountryId($value)
    {
        $this->city_id = null;
        $this->dispatch('country-updated');

        $cities = $value ? City::where('country_id', $value)->get(['id', 'name'])->toArray() : [];
        $this->dispatch('cities-loaded', cities: $cities);
    }

    #[Computed]
    public function certificateTypes()
    {
        return CertificateType::all();
    }

    #[Computed]
    public function departments()
    {
        return Department::latest()->get();
    }

    #[Computed]
    public function sections()
    {
        return $this->department_id ? Section::where('department_id', $this->department_id)->get() : collect();
    }

    #[Computed]
    public function levels()
    {
        return $this->section_id ? Level::whereHas('sections', fn($q) => $q->where('section_id', $this->section_id))->get() : collect();
    }

    #[Computed]
    public function selectedCertificateType()
    {
        return $this->certificate_type_id ? CertificateType::find($this->certificate_type_id) : null;
    }

    #[Computed]
    public function percentageScore()
    {
        if ($this->selectedCertificateType && $this->score) {
            return number_format(($this->score / $this->selectedCertificateType->total_score) * 100, 2) . '%';
        }

        return '0%';
    }

    #[Computed]
    public function departmentRequirements()
    {
        if (!$this->selectedCertificateType || !$this->department_id) {
            return collect();
        }

        return $this->selectedCertificateType->requirements()
            ->where('department_id', $this->department_id)
            ->get();
    }

    public function updatedCertificateTypeId($value)
    {
        $this->requirements = [];
    }

    public function updatedDepartmentId($value)
    {
        $this->section_id = null;
        $this->level_id = null;
        $this->dispatch('department-updated');

        if ($value) {
            $department = Department::find($value);
            if ($department) {
                $this->username = Student::generateStudentCode($department->code);
            }

            $sections = Section::where('department_id', $value)->get(['id', 'name'])->toArray();
            $this->dispatch('sections-loaded', sections: $sections);
        } else {
            $this->username = '';
            $this->dispatch('sections-loaded', sections: []);
        }
    }

    public function updatedSectionId($value)
    {
        $this->level_id = null;
        $this->dispatch('section-updated');

        if ($value) {
            $levels = Level::whereHas('sections', fn($q) => $q->where('section_id', $value))->get(['id', 'name'])->toArray();
            $this->dispatch('levels-loaded', levels: $levels);
        } else {
            $this->dispatch('levels-loaded', levels: []);
        }
    }

    public function save(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'national_id' => 'required|string|unique:students,national_id',
            'department_id' => 'required|exists:departments,id',
            'section_id' => 'required|exists:sections,id',
            'level_id' => 'required|exists:levels,id',
            'certificate_type_id' => 'required|exists:certificate_types,id',
            'score' => 'required|numeric|min:0',
            'username' => 'required|unique:students,username',
            'password' => 'required|min:8',
            'gender' => 'required|in:male,female',
        ];

        // Validate Subject Requirements
        foreach ($this->departmentRequirements as $requirement) {
            $rules["requirements.{$requirement->id}"] = [
                'required',
                'numeric',
                "min:{$requirement->min_score}",
            ];
        }

        if ($this->showFullForm) {
            $rules = array_merge($rules, [
                'religion' => 'required|string',
                'image' => 'nullable|image|max:1024',
                'birth_date' => 'required|date',
                'country_id' => 'required|exists:countries,id',
                'city_id' => 'required|exists:cities,id',
                'nationality_id' => 'required|exists:nationalities,id',
                'address' => 'required|string|max:500',
                'national_id_place' => 'required|string|max:255',
                'graduation_date' => 'required|date',
                'seat_number' => 'required|string',
            ]);
        } else {
            $rules = array_merge($rules, [
                'religion' => 'nullable|string',
                'image' => 'nullable|image|max:1024',
                'birth_date' => 'nullable|date',
                'country_id' => 'nullable|exists:countries,id',
                'city_id' => 'nullable|exists:cities,id',
                'nationality_id' => 'nullable|exists:nationalities,id',
                'address' => 'nullable|string|max:500',
                'national_id_place' => 'nullable|string|max:255',
                'graduation_date' => 'nullable|date',
                'seat_number' => 'nullable|string',
            ]);
        }

        // Common fields with default validation
        $rules = array_merge($rules, [
            'email' => 'nullable|email|unique:students,email',
            'phone' => 'nullable|string|max:20',
            'landline_phone' => 'nullable|string|max:20',
            'guardian_job' => 'nullable|string|max:255',
            'guardian_phone_1' => 'nullable|string|max:20',
            'guardian_phone_2' => 'nullable|string|max:20',
            'application_category' => 'required',
            'status' => 'required',
            'status_notes' => 'nullable|string|max:1000',
            'study_status' => 'required',
            'is_foreign' => 'boolean',
        ]);

        $validated = $this->validate($rules);

        // Convert empty strings to null
        $validated = array_map(fn($value) => $value === '' ? null : $value, $validated);

        if ($this->image) {
            $validated['image'] = $this->image->store('students', 'public');
        }

        // Store plain password and hash the other
        $validated['plain_password'] = $validated['password'];
        $validated['password'] = bcrypt($validated['password']);

        // Remove department_id as it's not in the students table
        unset($validated['department_id']);
        unset($validated['requirements']);

        // Find best academic advisor
        $advisor = AcademicAdvisor::query()
            ->where('is_active', true)
            ->whereHas('assignments', function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('department_id', $this->department_id)
                        ->orWhereNull('department_id');
                })->where(function ($q) use ($validated) {
                    $q->where('section_id', $validated['section_id'])
                        ->orWhereNull('section_id');
                })->where(function ($q) use ($validated) {
                    $q->where('level_id', $validated['level_id'])
                        ->orWhereNull('level_id');
                });
            })
            ->whereColumn('current_students', '<', 'max_students')
            ->orderBy('current_students')
            ->first();

        if ($advisor) {
            $validated['academic_advisor_id'] = $advisor->id;
            $advisor->increment('current_students');
        } else {
            session()->flash('error', 'لا يوجد مرشد أكاديمي متاح لهذا القسم والشعبة والمستوى. يرجى التواصل مع الإدارة.');
            $this->addError('department_id', 'لا يوجد مرشد أكاديمي متاح لهذا القسم والشعبة والمستوى. يرجى التواصل مع الإدارة.');
            return;
        }

        $student = Student::create($validated);

        // Handle requirements
        foreach ($this->requirements as $reqId => $score) {
            StudentScore::create([
                'student_id' => $student->id,
                'department_requirement_id' => $reqId,
                'score' => $score,
            ]);
        }

        session()->flash('success', 'تم إضافة الطالب بنجاح');

        return redirect()->route('students.index');
    }

    protected function validationAttributes(): array
    {
        $attributes = [];
        if ($this->department_id && $this->certificate_type_id) {
            foreach ($this->departmentRequirements as $requirement) {
                $attributes["requirements.{$requirement->id}"] = $requirement->subject_name;
            }
        }

        return $attributes;
    }

    protected function messages(): array
    {
        return [
            'requirements.*.min' => 'درجة :attribute يجب أن لا تقل عن :min للقبول في هذا التخصص.',
            'requirements.*.required' => 'درجة :attribute مطلوبة للقبول في هذا التخصص.',
        ];
    }

    public function render()
    {
        return view('livewire.admin.student.create');
    }
}
