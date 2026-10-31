<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comptes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('agence_id')->nullable();
            $table->string('numero', 40);
            $table->string('libelle');
            $table->string('nature', 30);
            $table->char('devise', 3);
            $table->string('syscohada', 20)->nullable();
            // Cache du solde (sens normal de la nature), recalculé chaque nuit depuis les écritures.
            $table->bigInteger('solde_cache')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'numero']);
            $table->unique(['id', 'organisation_id']);
            $table->index(['organisation_id', 'nature', 'devise']);
            $table->foreign(['agence_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('agences')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes');
    }
};
