<?php

namespace App\Models;

use App\Enums\ExamSessionStatus;
use App\Enums\Semester;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A term-wide exam committee: a room, a hand-picked set of students
 * (by code) and its own exam timetable.
 */
class ExamCommittee extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_id',
        'semester',
        'venue_id',
        'name',
        'capacity',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'status' => ExamSessionStatus::class,
            'capacity' => 'integer',
        ];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ExamCommitteeStudent::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'exam_committee_students')
            ->withPivot('seat_number')
            ->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    public function isPublished(): bool
    {
        return $this->status === ExamSessionStatus::PUBLISHED;
    }
}
