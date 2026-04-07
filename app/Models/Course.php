<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'course_section');
    }
}
