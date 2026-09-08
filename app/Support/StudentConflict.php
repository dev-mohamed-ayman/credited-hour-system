<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\Student;

final readonly class StudentConflict
{
    public function __construct(
        public Student $student,
        public ExamSession $other,
    ) {}
}
