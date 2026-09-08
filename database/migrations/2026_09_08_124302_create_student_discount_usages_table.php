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
        Schema::create('student_discount_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_fee_ticket_id')->constrained()->cascadeOnDelete();
            $table->decimal('applied_amount', 10, 2);
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_discount_id', 'student_fee_ticket_id'], 'discount_usages_discount_ticket_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_discount_usages', function (Blueprint $table) {
            $table->dropUnique('discount_usages_discount_ticket_unique');
            $table->dropForeign(['student_discount_id']);
            $table->dropForeign(['student_fee_ticket_id']);
            $table->dropForeign(['applied_by']);
            $table->dropColumn([
                'student_discount_id',
                'student_fee_ticket_id',
                'applied_amount',
                'applied_by',
            ]);
        });

        Schema::dropIfExists('student_discount_usages');
    }
};
