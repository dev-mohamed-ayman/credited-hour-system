<?php

use App\Enums\RegistrationStatus;
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
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('status')->default(RegistrationStatus::APPROVED->value)->after('semester');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('rejection_reason');
            $table->foreignId('approved_by_advisor_id')->nullable()->constrained('academic_advisors')->nullOnDelete()->after('approved_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropForeign(['approved_by_advisor_id']);
            $table->dropColumn(['status', 'rejection_reason', 'approved_by_user_id', 'approved_by_advisor_id']);
        });
    }
};
