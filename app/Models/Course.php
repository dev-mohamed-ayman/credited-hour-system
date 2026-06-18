<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    use HasDeletionGuards;

    protected $fillable = [
        'code',
        'name',
        'hours',
        'is_selected',
        'is_active',
        'department_id',
        'semester',
    ];

    protected $casts = [
        'is_selected' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $blockingRelations = ['sections'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'course_section');
    }
}
