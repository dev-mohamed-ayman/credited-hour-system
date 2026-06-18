<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasDeletionGuards;

    protected $fillable = ['department_id', 'name', 'cgpa'];
    protected $blockingRelations = ['students', 'certificateTypes', 'levels'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'level_section');
    }

    public function certificateTypes(): BelongsToMany
    {
        return $this->belongsToMany(CertificateType::class, 'certificate_type_section');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
