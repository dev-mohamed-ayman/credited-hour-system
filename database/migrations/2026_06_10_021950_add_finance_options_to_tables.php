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
            $table->string('ministerial_receipt_number')->nullable()->unique()->after('status');
            $table->string('payment_method')->nullable()->after('ministerial_receipt_number'); // 'cash', 'credit', 'both'
            $table->string('visa_last_four', 4)->nullable()->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('visa_last_four');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->integer('ministerial_receipt_start')->default(1000)->after('favicon');
            $table->integer('ministerial_receipt_end')->default(1500)->after('ministerial_receipt_start');
            $table->integer('ministerial_receipt_current')->default(999)->after('ministerial_receipt_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fee_tickets', function (Blueprint $table) {
            $table->dropColumn(['ministerial_receipt_number', 'payment_method', 'visa_last_four', 'paid_at']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['ministerial_receipt_start', 'ministerial_receipt_end', 'ministerial_receipt_current']);
        });
    }
};
