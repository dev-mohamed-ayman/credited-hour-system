<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The grade sheet signatures (chs: control/signature): كتبه، أملاه، راجعه، رئيس الكنترول، عميد المعهد.
     *
     * @var array<int, string>
     */
    private array $roles = ['written', 'dictated', 'reviewed', 'head', 'dean'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach ($this->roles as $role) {
                $table->string("signature_{$role}_name")->nullable();
                $table->string("signature_{$role}_image")->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach ($this->roles as $role) {
                $table->dropColumn(["signature_{$role}_name", "signature_{$role}_image"]);
            }
        });
    }
};
