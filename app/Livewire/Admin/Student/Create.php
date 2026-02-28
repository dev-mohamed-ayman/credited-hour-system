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

    public $address = '';

    public $is_foreign = false;

    public $national_id = '';

    public $national_id_place = '';

    public $email = '';

    public $phone = '';

    public $landline_phone = '';

    public $guardian_job = '';

    public $guardian_phone_1 = '';

    public $guardian_phone_2 = '';

    // Certificate Info
    public $certificate_type_id;

    public $graduation_date;

    public $seat_number = '';

    public $score;

    public $requirements = [];

    // Application Info
    public $application_category;

    public $status;

    public $status_notes = '';

    public $department_id;

    public $section_id;

    public $level_id;

    public $study_status;

    public $username = '';

    public $password = '';

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
        return $this->section_id ? Level::whereHas('sections', fn ($q) => $q->where('section_id', $this->section_id))->get() : collect();
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
            return number_format(($this->score / $this->selectedCertificateType->total_score) * 100, 2).'%';
        }

        return '0%';
    }

    #[Computed]
    public function departmentRequirements()
    {
        return $this->selectedCertificateType ? $this->selectedCertificateType->requirements : collect();
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
            $levels = Level::whereHas('sections', fn ($q) => $q->where('section_id', $value))->get(['id', 'name'])->toArray();
            $this->dispatch('levels-loaded', levels: $levels);
        } else {
            $this->dispatch('levels-loaded', levels: []);
        }
    }

    public function save(Request $request)
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'religion' => 'required|string',
            'gender' => 'required|in:male,female',
            'image' => 'nullable|image|max:1024',
            'birth_date' => 'required|date',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'nationality_id' => 'required|exists:nationalities,id',
            'address' => 'required|string|max:500',
            'is_foreign' => 'boolean',
            'national_id' => 'required|string|unique:students,national_id',
            'national_id_place' => 'required|string|max:255',
            'email' => 'nullable|email|unique:students,email',
            'phone' => 'nullable|string|max:20',
            'landline_phone' => 'nullable|string|max:20',
            'guardian_job' => 'nullable|string|max:255',
            'guardian_phone_1' => 'nullable|string|max:20',
            'guardian_phone_2' => 'nullable|string|max:20',
            'certificate_type_id' => 'required|exists:certificate_types,id',
            'graduation_date' => 'required|date',
            'seat_number' => 'required|string',
            'score' => 'required|numeric|min:0',
            'application_category' => 'required',
            'status' => 'required',
            'status_notes' => 'nullable|string|max:1000',
            'section_id' => 'required|exists:sections,id',
            'level_id' => 'required|exists:levels,id',
            'study_status' => 'required',
            'username' => 'required|unique:students,username',
            'password' => 'required|min:8',
        ]);

        if ($this->image) {
            $validated['image'] = $this->image->store('students', 'public');
        }

        // We don't store department_id in the student table, but it's used for validation and logic.
        // We'll remove it from the validated data to avoid any issues if the table doesn't have it.
        // Actually, $validated already contains only what we validated.
        // If department_id was in $this->validate(), it would be in $validated.
        // Since I removed it from the validate array above (replaced it with section_id and level_id), it won't be in $validated.

        $student = Student::create($validated);

        // Handle requirements if needed (assuming a relation exists)
        // foreach ($this->requirements as $reqId => $score) { ... }

        session()->flash('success', 'تم إضافة الطالب بنجاح');

        return redirect()->route('students.index');
    }

    public function render()
    {
        return view('livewire.admin.student.create');
    }
}
