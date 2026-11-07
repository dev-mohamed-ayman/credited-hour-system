<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentWarning extends Model
{
    protected $fillable = [
        'student_id',
        'term_closure_id',
        'type',
        'reason',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => \App\Enums\Student\StudentWarningType::class,
            'is_active' => 'boolean',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The term closure that issued this warning; null for warnings added by hand.
     */
    public function termClosure(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TermClosure::class);
    }

    /**
     * Academic warnings issued by closing a term — the ones counted towards dismissal.
     */
    public function scopeAcademic(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('term_closure_id');
    }
}
