<?php

namespace App\Livewire\Admin\StudentWarning;

use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudentWarningType;
use App\Models\Student;
use App\Models\StudentWarning;
use Livewire\Component;

class Create extends Component
{
    public $student_codes = '';
    public $type = 'warning';
    public $reason = '';

    protected $rules = [
        'student_codes' => 'required|string',
        'type' => 'required|string',
        'reason' => 'required|string',
    ];

    public function mount()
    {
        $this->type = StudentWarningType::WARNING->value;
    }

    public function save()
    {
        $this->validate();

        // Parse student codes (split by comma, space, or newline)
        $codes = preg_split('/[\s,]+/', $this->student_codes, -1, PREG_SPLIT_NO_EMPTY);
        $codes = array_unique($codes);

        $studentsFound = 0;
        $studentsNotFound = [];

        foreach ($codes as $code) {
            $student = Student::where('username', $code)->first();

            if ($student) {
                StudentWarning::create([
                    'student_id' => $student->id,
                    'type' => $this->type,
                    'reason' => $this->reason,
                    'is_active' => true,
                ]);

                if ($this->type === StudentWarningType::DANGER->value) {
                    $student->update([
                        'status' => StudentStatus::SUSPENDED->value,
                    ]);
                }

                $studentsFound++;
            } else {
                $studentsNotFound[] = $code;
            }
        }

        if ($studentsFound > 0) {
            $message = "تم إضافة التنبيه لـ {$studentsFound} طالب بنجاح.";
            if (count($studentsNotFound) > 0) {
                $message .= " لم يتم العثور على الأكواد التالية: " . implode(', ', $studentsNotFound);
                $this->dispatch('success', $message);
            } else {
                $this->dispatch('success', 'تم إضافة التنبيهات بنجاح.');
            }
            return redirect()->route('student-warnings.index');
        } else {
            $this->dispatch('error', 'لم يتم العثور على أي من أكواد الطلاب المدخلة.');
        }
    }

    public function render()
    {
        return view('livewire.admin.student-warning.create')->extends('admin.layouts.app')->section('content');
    }
}
