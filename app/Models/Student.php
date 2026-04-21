<?php

namespace App\Models;

use App\Enums\Student\ApplicationCategory;
use App\Enums\Student\StudentStatus;
use App\Enums\Student\StudyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'application_category' => ApplicationCategory::class,
        'status' => StudentStatus::class,
        'study_status' => StudyStatus::class,
    ];

    public function scores()
    {
        return $this->hasMany(StudentScore::class);
    }

    public static function generateStudentCode(string $prefix): string
    {
        $prefix = strtoupper($prefix);
        $year = date('y');

        $lastStudent = self::where('username', 'like', $prefix . $year . '%')->orderByDesc('username')->first();

        if ($lastStudent) {
            $lastNumber = (int)substr($lastStudent->username, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $year . $newNumber;
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function academicAdvisor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AcademicAdvisor::class);
    }

    public function warnings()
    {
        return $this->hasMany(StudentWarning::class);
    }
}
