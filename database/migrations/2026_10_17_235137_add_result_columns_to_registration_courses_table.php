<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_courses', function (Blueprint $table) {
            $table->decimal('written_score', 5, 2)->nullable()->after('grade_id');
            $table->decimal('coursework_score', 5, 2)->nullable()->after('written_score');
            $table->decimal('total_score', 5, 2)->nullable()->after('coursework_score');
            $table->string('exam_status')->nullable()->after('total_score');
            $table->string('result_note')->nullable()->after('exam_status');
            $table->timestamp('result_uploaded_at')->nullable()->after('result_note');
            $table->foreignId('result_uploaded_by')->nullable()->after('result_uploaded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_uploaded_by');
            $table->dropColumn(['written_score', 'coursework_score', 'total_score', 'exam_status', 'result_note', 'result_uploaded_at']);
        });
    }
};
