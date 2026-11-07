<?php

namespace App\Models;

use App\Enums\ExamSessionStatus;
use App\Enums\Semester;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamCommitteeStudent extends Model
{
    /** @use HasFactory<\Database\Factories\ExamCommitteeStudentFactory> */
    use HasFactory;

    protected $fillable = [
        'exam_committee_id',
        'student_id',
        'seat_number',
    ];

    protected function casts(): array
    {
        return [
            'seat_number' => 'integer',
        ];
    }

    /**
     * Seats in a given term's committees — the single source of a student's seat number.
     */
    public function scopeForTerm(Builder $query, ?Year $year, ?Semester $semester, bool $publishedOnly = false): Builder
    {
        if ($year === null || $semester === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('committee', fn (Builder $committee) => $committee
            ->where('year_id', $year->id)
            ->where('semester', $semester->value)
            ->when($publishedOnly, fn (Builder $published) => $published->where('status', ExamSessionStatus::PUBLISHED->value)));
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(ExamCommittee::class, 'exam_committee_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
