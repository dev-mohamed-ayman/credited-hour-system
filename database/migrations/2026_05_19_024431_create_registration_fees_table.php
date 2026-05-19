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
        Schema::create('registration_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('level_id')->constrained()->onDelete('cascade');
            $table->decimal('hour_payment', 10, 2)->default(0);
            $table->decimal('ministerial_payment', 10, 2)->default(0);
            $table->decimal('hour_payment_remaining', 10, 2)->default(0);
            $table->decimal('ministerial_payment_remaining', 10, 2)->default(0);
            $table->decimal('total_student_payment', 10, 2)->default(0);
            $table->decimal('student_registration_hour', 10, 2)->default(0);
            $table->integer('number_of_students_per_section')->default(0);
            $table->unique(['department_id', 'level_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_fees');
    }
};
