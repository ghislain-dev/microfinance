<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Choix du comptable ; en l'absence de ligne, la proposition par défaut (PlanSyscohada) s'applique.
        Schema::create('correspondances_syscohada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('rubrique', 40);
            $table->string('numero', 8);
            $table->string('libelle', 150);
            $table->timestamps();

            $table->unique(['organisation_id', 'rubrique']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondances_syscohada');
    }
};
