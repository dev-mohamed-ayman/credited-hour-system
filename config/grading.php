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

];
