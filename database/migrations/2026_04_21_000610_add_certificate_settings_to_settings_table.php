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
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('cert_show_photo')->default(1);
            $table->boolean('cert_show_birth_info')->default(1);
            $table->boolean('cert_show_national_id')->default(1);
            $table->boolean('cert_show_seat_number')->default(1);
            $table->boolean('cert_show_specialization')->default(1);
            $table->boolean('cert_show_grade')->default(1);
            $table->boolean('cert_show_cgpa')->default(1);
            $table->boolean('cert_show_semester')->default(1);
            $table->boolean('cert_show_extra')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'cert_show_photo',
                'cert_show_birth_info',
                'cert_show_national_id',
                'cert_show_seat_number',
                'cert_show_specialization',
                'cert_show_grade',
                'cert_show_cgpa',
                'cert_show_semester',
                'cert_show_extra',
            ]);
        });
    }
};
