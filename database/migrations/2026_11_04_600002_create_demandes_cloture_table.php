<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clôture mensuelle (ADR 023) : demandée par le comptable, validée ou rejetée par le gérant.
        Schema::create('demandes_cloture', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->date('date_fin');                         // dernier jour du mois
            $table->string('statut', 20);                     // en_attente | validee | rejetee
            $table->unsignedBigInteger('demande_par_id');
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->timestamp('decide_le')->nullable();
            // Une seule demande en attente par organisation (colonne générée + UNIQUE, MySQL et SQLite).
            $table->boolean('attente_flag')->nullable()->storedAs("CASE WHEN statut = 'en_attente' THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['organisation_id', 'attente_flag']);
            $table->index(['organisation_id', 'date_fin']);
            $table->foreign(['demande_par_id', 'organisation_id'], 'demandes_cloture_demandeur_fk')->references(['id', 'organisation_id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_cloture');
    }
};
