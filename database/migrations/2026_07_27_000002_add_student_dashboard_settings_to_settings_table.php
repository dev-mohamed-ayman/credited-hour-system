<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedInteger('graduation_required_hours')->default(132)->after('allow_cross_level_registration');
            $table->decimal('warning_gpa_threshold', 3, 2)->default(2.00)->after('graduation_required_hours');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['graduation_required_hours', 'warning_gpa_threshold']);
        });
    }
};
