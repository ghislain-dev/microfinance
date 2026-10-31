<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groupes_tontine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('agence_id');
            $table->string('code', 20);
            $table->string('nom', 100);
            $table->string('type', 20);                       // rotative | avec
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['agence_id', 'organisation_id'], 'groupes_tontine_agence_fk')->references(['id', 'organisation_id'])->on('agences');
        });

        Schema::create('groupe_membres', function (Blueprint $table) {
            $table->unsignedBigInteger('groupe_id');
            $table->unsignedBigInteger('membre_id');
            $table->unsignedBigInteger('organisation_id');
            $table->timestamps();

            $table->primary(['groupe_id', 'membre_id']);
            $table->foreign(['groupe_id', 'organisation_id'], 'groupe_membres_groupe_fk')->references(['id', 'organisation_id'])->on('groupes_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'groupe_membres_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });

        // Paramètres figés au démarrage ; montants en centimes de la devise du cycle.
        Schema::create('cycles_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('groupe_id');
            $table->unsignedInteger('numero');
            $table->char('devise', 3);
            $table->string('periodicite', 20);
            $table->date('date_debut');
            $table->unsignedSmallInteger('nombre_seances')->nullable(); // rotative : fixé au démarrage
            $table->bigInteger('montant_cotisation');                  // rotative : cotisation ; AVEC : prix de la part
            $table->unsignedTinyInteger('parts_min')->default(1);
            $table->unsignedTinyInteger('parts_max')->default(1);
            $table->bigInteger('cotisation_fonds_social')->default(0);
            $table->bigInteger('penalite_absence')->default(0);
            $table->bigInteger('penalite_retard')->default(0);
            $table->bigInteger('penalite_impaye')->default(0);
            $table->string('mode_tours', 20)->nullable();             // rotative seulement
            $table->string('statut', 20);
            $table->unsignedBigInteger('compte_fonds_id')->nullable();
            $table->unsignedBigInteger('compte_fonds_social_id')->nullable();
            $table->unsignedBigInteger('compte_creances_id')->nullable();
            $table->boolean('en_cours_flag')->nullable()->storedAs("CASE WHEN statut = 'en_cours' THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['groupe_id', 'numero']);
            $table->unique(['groupe_id', 'en_cours_flag']);          // un seul cycle en cours par groupe
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['groupe_id', 'organisation_id'], 'cycles_groupe_fk')->references(['id', 'organisation_id'])->on('groupes_tontine');
            foreach (['compte_fonds_id', 'compte_fonds_social_id', 'compte_creances_id'] as $colonne) {
                $table->foreign([$colonne, 'organisation_id'], "cycles_{$colonne}_fk")->references(['id', 'organisation_id'])->on('comptes');
            }
        });

        Schema::create('cycle_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedBigInteger('membre_id');
            $table->unsignedBigInteger('organisation_id');
            $table->timestamps();

            $table->primary(['cycle_id', 'membre_id']);
            $table->foreign(['cycle_id', 'organisation_id'], 'participants_cycle_fk')->references(['id', 'organisation_id'])->on('cycles_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'participants_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_participants');
        Schema::dropIfExists('cycles_tontine');
        Schema::dropIfExists('groupe_membres');
        Schema::dropIfExists('groupes_tontine');
    }
};
