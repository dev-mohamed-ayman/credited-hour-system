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
            $table->date('first_semester_exam_from')->nullable();
            $table->date('first_semester_exam_to')->nullable();
            $table->date('second_semester_exam_from')->nullable();
            $table->date('second_semester_exam_to')->nullable();
            $table->date('summer_exam_from')->nullable();
            $table->date('summer_exam_to')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('years', function (Blueprint $table) {
            $table->dropColumn([
                'first_semester_exam_from',
                'first_semester_exam_to',
                'second_semester_exam_from',
                'second_semester_exam_to',
                'summer_exam_from',
                'summer_exam_to',
            ]);
        });
    }
};
