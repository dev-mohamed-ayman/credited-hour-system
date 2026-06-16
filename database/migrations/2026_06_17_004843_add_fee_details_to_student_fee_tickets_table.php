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
        Schema::table('student_fee_tickets', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gender')->nullable(); // For additional fees: 'male', 'female', 'both'
            $table->json('fee_details')->nullable(); // To store all details in case original fee is deleted/modified
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fee_tickets', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['level_id']);
            $table->dropForeign(['section_id']);
            $table->dropColumn(['department_id', 'level_id', 'section_id', 'gender', 'fee_details']);
        });
    }
};
