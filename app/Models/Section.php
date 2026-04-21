<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['department_id', 'name', 'cgpa'];

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
}
