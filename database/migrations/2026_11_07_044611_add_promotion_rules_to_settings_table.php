<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The end-of-term rules that were fixed numbers in chs (and in config/grading.php).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('warning_exempt_earned_hours')->default(105)->after('warning_gpa_threshold');
            $table->unsignedTinyInteger('dismissal_warnings')->default(3)->after('warning_exempt_earned_hours');
            $table->unsignedTinyInteger('max_years_in_level')->default(5)->after('dismissal_warnings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['warning_exempt_earned_hours', 'dismissal_warnings', 'max_years_in_level']);
        });
    }
};
