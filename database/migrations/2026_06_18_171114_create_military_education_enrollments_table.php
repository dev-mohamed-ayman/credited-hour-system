<?php

use App\Enums\MilitaryEducationEnrollmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('military_education_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('military_education_courses')->cascadeOnDelete();
            $table->foreignId('year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('semester')->nullable();
            $table->string('status')->default(MilitaryEducationEnrollmentStatus::REGISTERED->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('military_education_enrollments');
    }
};
