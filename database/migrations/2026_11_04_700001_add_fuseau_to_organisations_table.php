<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fuseau de l'organisation (ADR 024) ; Kinshasa par défaut, modifiable par l'administrateur.
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('fuseau', 64)->default('Africa/Kinshasa');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', fn (Blueprint $table) => $table->dropColumn('fuseau'));
    }
};
