<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The control's note on a student for one term, printed on the grade sheet (chs: students_notes).
 */
class StudentTermNote extends Model
{
    protected $fillable = [
        'student_id',
        'year_id',
        'semester',
        'note',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
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
}
