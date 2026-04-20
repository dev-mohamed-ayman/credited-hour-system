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
            $table->boolean('seat_show_photo')->default(1);
            $table->boolean('seat_show_name')->default(1);
            $table->boolean('seat_show_code')->default(1);
            $table->boolean('seat_show_department')->default(1);
            $table->boolean('seat_show_section')->default(1);
            $table->boolean('seat_show_level')->default(1);
            $table->boolean('seat_show_seat_number')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'seat_show_photo',
                'seat_show_name',
                'seat_show_code',
                'seat_show_department',
                'seat_show_section',
                'seat_show_level',
                'seat_show_seat_number',
            ]);
        });
    }
};
