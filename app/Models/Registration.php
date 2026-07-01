<?php

namespace App\Models;

use App\Enums\Semester;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    protected $fillable = [
        'student_id',
        'year_id',
        'semester',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'status' => RegistrationStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(RegistrationCourse::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdByAdvisor(): BelongsTo
    {
        return $this->belongsTo(AcademicAdvisor::class, 'created_by_advisor_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function approvedByAdvisor(): BelongsTo
    {
        return $this->belongsTo(AcademicAdvisor::class, 'approved_by_advisor_id');
    }
}
