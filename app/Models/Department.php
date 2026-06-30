<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasDeletionGuards;

    protected $fillable = ['name', 'code', 'course_code'];
    protected $blockingRelations = ['sections', 'requirements'];

    public function requirements()
    {
        return $this->hasMany(DepartmentRequirement::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }
}
