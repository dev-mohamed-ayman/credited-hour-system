<?php

namespace App\Models;

use App\Enums\AcademicAdvisingStatus;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Year extends Model
{
    use HasDeletionGuards;

    protected $fillable = [
        'year',
        'first_semester_status',
        'second_semester_status',
        'summer_semester_status',
        'academic_advising_status',
        'first_semester_exam_from',
        'first_semester_exam_to',
        'second_semester_exam_from',
        'second_semester_exam_to',
        'summer_exam_from',
        'summer_exam_to',
    ];

    protected $blockingRelations = ['students', 'additionalFees', 'examSessions'];

    protected function casts(): array
    {
        return [
            'first_semester_status' => SemesterStatus::class,
            'second_semester_status' => SemesterStatus::class,
            'summer_semester_status' => SemesterStatus::class,
            'first_semester_exam_from' => 'date:Y-m-d',
            'first_semester_exam_to' => 'date:Y-m-d',
            'second_semester_exam_from' => 'date:Y-m-d',
            'second_semester_exam_to' => 'date:Y-m-d',
            'summer_exam_from' => 'date:Y-m-d',
            'summer_exam_to' => 'date:Y-m-d',
            'academic_advising_status' => AcademicAdvisingStatus::class,
        ];
    }

    public function getCurrentSemester(): ?Semester
    {
        if ($this->first_semester_status !== SemesterStatus::DISABLED) {
            return Semester::FIRST;
        }

        if ($this->second_semester_status !== SemesterStatus::DISABLED) {
            return Semester::SECOND;
        }

        if ($this->summer_semester_status !== SemesterStatus::DISABLED) {
            return Semester::SUMMER;
        }

        return null;
    }

    public function getSemesterStatus(Semester $semester): SemesterStatus
    {
        return match ($semester) {
            Semester::FIRST => $this->first_semester_status,
            Semester::SECOND => $this->second_semester_status,
            Semester::SUMMER => $this->summer_semester_status,
        };
    }

    public function setSemesterStatus(Semester $semester, SemesterStatus $status): self
    {
        $this->update([
            'first_semester_status' => $semester === Semester::FIRST ? $status : SemesterStatus::DISABLED,
            'second_semester_status' => $semester === Semester::SECOND ? $status : SemesterStatus::DISABLED,
            'summer_semester_status' => $semester === Semester::SUMMER ? $status : SemesterStatus::DISABLED,
        ]);

        return $this;
    }

    public static function current(): ?self
    {
        return static::query()
            ->where(function ($query) {
                $query->where('first_semester_status', '!=', SemesterStatus::DISABLED->value)
                    ->orWhere('second_semester_status', '!=', SemesterStatus::DISABLED->value)
                    ->orWhere('summer_semester_status', '!=', SemesterStatus::DISABLED->value);
            })
            ->latest('id')
            ->first();
    }

    public static function currentSemester(): ?Semester
    {
        $year = static::current();

        return $year?->getCurrentSemester();
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function additionalFees(): HasMany
    {
        return $this->hasMany(AdditionalFee::class);
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    /**
     * Configured exam window (from/to) for a semester, or null when the
     * semester has no complete window — the guard is optional (R6).
     *
     * @return array{from: string, to: string}|null
     */
    public function semesterExamWindow(Semester $semester): ?array
    {
        [$from, $to] = match ($semester) {
            Semester::FIRST => [$this->first_semester_exam_from, $this->first_semester_exam_to],
            Semester::SECOND => [$this->second_semester_exam_from, $this->second_semester_exam_to],
            Semester::SUMMER => [$this->summer_exam_from, $this->summer_exam_to],
        };

        if ($from === null || $to === null) {
            return null;
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }
}
