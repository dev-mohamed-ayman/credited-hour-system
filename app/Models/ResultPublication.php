<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A term whose results students may see (إعلان النتيجة).
 */
class ResultPublication extends Model
{
    protected $fillable = [
        'year_id',
        'semester',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
        ];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public static function isPublished(int $yearId, Semester|string|null $semester): bool
    {
        $semester = $semester instanceof Semester ? $semester->value : $semester;

        return in_array($yearId.'|'.$semester, static::publishedTermKeys(), true);
    }

    /**
     * "year_id|semester" of every published term.
     *
     * @return array<int, string>
     */
    public static function publishedTermKeys(): array
    {
        return static::query()
            ->get(['year_id', 'semester'])
            ->map(fn (ResultPublication $publication) => $publication->year_id.'|'.$publication->semester->value)
            ->all();
    }
}
