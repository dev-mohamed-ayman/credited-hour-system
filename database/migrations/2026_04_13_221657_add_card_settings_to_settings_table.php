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
            $table->boolean('card_show_photo')->default(1);
            $table->boolean('card_show_name')->default(1);
            $table->boolean('card_show_code')->default(1);
            $table->boolean('card_show_barcode')->default(1);
            $table->boolean('card_show_department')->default(1);
            $table->boolean('card_show_section')->default(1);
            $table->boolean('card_show_level')->default(1);
            $table->boolean('card_show_national_id')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'card_show_photo',
                'card_show_name',
                'card_show_code',
                'card_show_barcode',
                'card_show_department',
                'card_show_section',
                'card_show_level',
                'card_show_national_id'
            ]);
        });
    }
};
