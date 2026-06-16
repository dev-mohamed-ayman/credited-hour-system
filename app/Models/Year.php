<?php

namespace App\Models;

use App\Enums\AcademicAdvisingStatus;
use App\Enums\Semester;
use App\Enums\SemesterStatus;
use Illuminate\Database\Eloquent\Model;

class Year extends Model
{
    protected $fillable = [
        'year',
        'first_semester_status',
        'second_semester_status',
        'summer_semester_status',
        'academic_advising_status',
    ];

    protected function casts(): array
    {
        return [
            'first_semester_status' => SemesterStatus::class,
            'second_semester_status' => SemesterStatus::class,
            'summer_semester_status' => SemesterStatus::class,
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
}
