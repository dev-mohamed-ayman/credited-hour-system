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
        Schema::create('student_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->unsignedBigInteger('fee_id')->nullable();
            $table->foreignId('year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('semester')->nullable();
            $table->string('mode');
            $table->decimal('value', 10, 2);
            $table->decimal('remaining_amount', 10, 2)->nullable();
            $table->string('status')->default('active');
            $table->string('reason');
            $table->string('decision_number')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_discounts', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['year_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['revoked_by']);
            $table->dropColumn([
                'student_id',
                'scope',
                'fee_id',
                'year_id',
                'semester',
                'mode',
                'value',
                'remaining_amount',
                'status',
                'reason',
                'decision_number',
                'created_by',
                'revoked_by',
                'revoked_at',
                'revoked_reason',
            ]);
        });

        Schema::dropIfExists('student_discounts');
    }
};
