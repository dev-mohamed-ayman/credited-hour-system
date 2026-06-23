<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['registration_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_courses');
    }
};
