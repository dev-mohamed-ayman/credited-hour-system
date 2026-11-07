<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Overall Classification (التقدير العام)
    |--------------------------------------------------------------------------
    |
    | Cumulative GPA bands, highest first. A student gets the first band whose
    | minimum their CGPA reaches. Mirrors chs (DataTrait::gradeToCgpa).
    |
    */

    'classifications' => [
        ['min' => 3.7, 'label' => 'ممتاز'],
        ['min' => 3.0, 'label' => 'جيد جداً'],
        ['min' => 2.4, 'label' => 'جيد'],
        ['min' => 2.0, 'label' => 'مقبول'],
        ['min' => 0.0, 'label' => 'ضعيف'],
    ],

    /*
    |--------------------------------------------------------------------------
    | End-of-Term Procedures
    |--------------------------------------------------------------------------
    |
    | Rules applied when a term is closed (chs AdminController::changeStudentLevel).
    | The GPA threshold itself is the "warning_gpa_threshold" setting, and the
    | hours to leave each level are set on the level (promotion_hours).
    |
    */

    // Students who earned at least this many hours are never warned.
    'warning_exempt_earned_hours' => 105,

    // Active academic warnings that dismiss a student.
    'dismissal_warnings' => 3,

    // Years an "external" (من الخارج) student may spend in one level.
    'max_years_in_level' => 5,

];
