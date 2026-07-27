<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cross_level_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('visible_level_id')->constrained('levels')->cascadeOnDelete();
            $table->unique(['source_level_id', 'visible_level_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_level_visibility');
    }
};
