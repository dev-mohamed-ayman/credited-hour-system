<?php

namespace App\Models;

use App\Traits\HasDeletionGuards;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateType extends Model
{
    use HasDeletionGuards, HasFactory;

    public const NAMES = [
        'الثانوية العامة - علمي علوم',
        'الثانوية العامة - علمي رياضة',
        'الثانوية العامة - أدبي',
        'الثانوية الأزهرية - علمي',
        'الثانوية الأزهرية - أدبي',
        'الدبلوم الفني الصناعي',
        'الدبلوم الفني التجاري',
        'الدبلوم الفني الزراعي',
        'الشهادات المعادلة العربية',
        'الشهادات المعادلة الأجنبية (IGCSE)',
        'الشهادات المعادلة الأجنبية (American Diploma)',
        'STEM',
    ];

    protected $fillable = ['name', 'total_score'];

    protected $blockingRelations = ['sections', 'students'];

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(DepartmentRequirement::class, 'certificate_type_requirement', 'certificate_type_id', 'department_requirement_id');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'certificate_type_section');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
