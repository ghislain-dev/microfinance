<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Affectation du résultat d'une année clôturée aux excédents à répartir (ADR 025).
        Schema::create('affectations_resultat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedSmallInteger('annee');
            $table->char('devise', 3);
            $table->bigInteger('resultat');                   // résultat de l'année au moment de la proposition
            $table->bigInteger('montant');                    // part affectée
            $table->string('statut', 20);                     // en_attente | validee | rejetee
            $table->unsignedBigInteger('propose_par_id');
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->unsignedBigInteger('operation_id')->nullable()->unique();
            // Une seule proposition en attente par année et devise.
            $table->boolean('attente_flag')->nullable()->storedAs("CASE WHEN statut = 'en_attente' THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['organisation_id', 'annee', 'devise', 'attente_flag'], 'affectations_attente_unique');
            $table->foreign(['propose_par_id', 'organisation_id'], 'affectations_auteur_fk')->references(['id', 'organisation_id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affectations_resultat');
    }
};
