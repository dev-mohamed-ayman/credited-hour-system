<?php

use App\Enums\TransferRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('from_level_id')->nullable()->constrained('levels')->nullOnDelete();

            $table->foreignId('to_department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('to_section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('to_level_id')->constrained('levels')->cascadeOnDelete();

            $table->foreignId('year_id')->nullable()->constrained('years')->nullOnDelete();
            $table->string('semester')->nullable();

            $table->string('status')->default(TransferRequestStatus::PENDING->value);
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->json('reversal_snapshot')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transfer_requests');
    }
};
