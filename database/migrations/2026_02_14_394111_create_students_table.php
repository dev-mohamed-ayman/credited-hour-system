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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('religion')->nullable(); // enum
            $table->date('birth_date')->nullable();
            $table->foreignId('certificate_type_id')->constrained()->cascadeOnDelete();
            $table->date('graduation_date')->nullable();
            $table->string('seat_number')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('application_category')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('address')->nullable();
            $table->foreignId('nationality_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_foreign')->default(false);
            $table->string('national_id')->unique();
            $table->string('national_id_place')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('landline_phone')->nullable();
            $table->string('guardian_job')->nullable();
            $table->string('guardian_phone_1')->nullable();
            $table->string('guardian_phone_2')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('status')->nullable();
            $table->text('status_notes')->nullable()->after('status');
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('study_status')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
