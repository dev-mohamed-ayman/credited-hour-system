<?php

namespace App\Models;

use App\Enums\VenueType;
use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use HasDeletionGuards, HasFactory;

    protected $fillable = [
        'name',
        'type',
        'capacity',
        'is_active',
        'notes',
    ];

    protected $blockingRelations = ['lectureSchedules', 'examCommittees'];

    protected function casts(): array
    {
        return [
            'type' => VenueType::class,
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function lectureSchedules(): HasMany
    {
        return $this->hasMany(LectureSchedule::class);
    }

    public function examCommittees(): HasMany
    {
        return $this->hasMany(ExamCommittee::class);
    }
}
