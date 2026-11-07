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
        Schema::table('student_warnings', function (Blueprint $table) {
            $table->foreignId('term_closure_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_warnings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_closure_id');
        });
    }
};
