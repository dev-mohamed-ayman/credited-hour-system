<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasDeletionGuards;

    protected $fillable = [
        'name',
        'is_pending_default',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_pending_default' => 'boolean',
            'order' => 'integer',
        ];
    }

    protected $blockingRelations = ['registrationCourses'];

    public function registrationCourses(): HasMany
    {
        return $this->hasMany(RegistrationCourse::class);
    }

    public static function pendingDefault(): ?self
    {
        return static::query()->where('is_pending_default', true)->first();
    }
}
