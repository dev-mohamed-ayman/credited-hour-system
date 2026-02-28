<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicAdvisorAssignment extends Model
{
    protected $fillable = [
        'academic_advisor_id',
        'department_id',
        'section_id',
        'level_id',
    ];

    public function academicAdvisor(): BelongsTo
    {
        return $this->belongsTo(AcademicAdvisor::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
}
