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
            $table->foreignId('year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('semester')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fee_tickets', function (Blueprint $table) {
            $table->dropForeign(['year_id']);
            $table->dropColumn(['year_id', 'semester']);
        });
    }
};
