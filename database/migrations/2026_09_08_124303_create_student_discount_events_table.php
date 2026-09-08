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
        Schema::create('student_discount_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_discount_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['student_discount_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_discount_events', function (Blueprint $table) {
            $table->dropForeign(['student_discount_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['student_discount_id', 'action', 'meta', 'user_id']);
        });

        Schema::dropIfExists('student_discount_events');
    }
};
