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
        Schema::create('additional_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('additional_fees')->nullOnDelete();
            $table->string('name');
            $table->enum('gender', ['male', 'female', 'both'])->default('both');
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('is_one_time')->default(true);
            $table->timestamps();
        });

        Schema::create('additional_fee_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('additional_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('additional_fee_level', function (Blueprint $table) {
            $table->id();
            $table->foreignId('additional_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('additional_fee_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('additional_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_fee_section');
        Schema::dropIfExists('additional_fee_level');
        Schema::dropIfExists('additional_fee_department');
        Schema::dropIfExists('additional_fees');
    }
};
