<?php

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
        Schema::table('years', function (Blueprint $table) {
            $table->string('first_semester_status')->default(\App\Enums\SemesterStatus::DISABLED->value);
            $table->string('second_semester_status')->default(\App\Enums\SemesterStatus::DISABLED->value);
            $table->string('summer_semester_status')->default(\App\Enums\SemesterStatus::DISABLED->value);
            $table->string('academic_advising_status')->default(\App\Enums\AcademicAdvisingStatus::CLOSED->value);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('years', function (Blueprint $table) {
            $table->dropColumn(['first_semester_status', 'second_semester_status', 'summer_semester_status', 'academic_advising_status']);
        });
    }
};
