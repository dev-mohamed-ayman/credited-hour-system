<?php

namespace App\Models;

use App\Enums\ResultChangeSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a course result (grade, marks or note): who made it, how, why,
 * and the values before and after.
 */
class ResultChange extends Model
{
    protected $fillable = [
        'registration_course_id',
        'user_id',
        'source',
        'reason',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'source' => ResultChangeSource::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * Logs the change from $before to the enrollment's current result; nothing when they match.
     *
     * @param  array<string, mixed>  $before  RegistrationCourse::resultSnapshot() taken before the change
     */
    public static function record(RegistrationCourse $enrollment, array $before, ResultChangeSource $source, ?User $user = null, ?string $reason = null): ?self
    {
        $after = $enrollment->refresh()->resultSnapshot();

        if ($after === $before) {
            return null;
        }

        return static::create([
            'registration_course_id' => $enrollment->id,
            'user_id' => $user?->id,
            'source' => $source->value,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ]);
    }

    public function registrationCourse(): BelongsTo
    {
        return $this->belongsTo(RegistrationCourse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
