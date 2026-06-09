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
        Schema::table('additional_fees', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        Schema::create('additional_fee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('additional_fee_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_fee_items');

        Schema::table('additional_fees', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('additional_fees')->nullOnDelete();
        });
    }
};
