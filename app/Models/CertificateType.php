<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateType extends Model
{
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

    public function requirements()
    {
        return $this->belongsToMany(DepartmentRequirement::class, 'certificate_type_requirement', 'certificate_type_id', 'department_requirement_id');
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'certificate_type_section');
    }
}
