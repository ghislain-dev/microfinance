<?php

declare(strict_types=1);

use App\Support\BaseDeDonnees\InsertionSeule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aides du fonds social : saisies par le gérant, validées par le comité.
        Schema::create('aides_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedBigInteger('membre_id');
            $table->bigInteger('montant');
            $table->string('motif', 500);
            $table->string('statut', 20);                  // en_attente | validee | rejetee
            $table->unsignedBigInteger('demande_par_id');
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->unsignedBigInteger('operation_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['cycle_id', 'statut']);
            $table->foreign(['cycle_id', 'organisation_id'], 'aides_cycle_fk')->references(['id', 'organisation_id'])->on('cycles_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'aides_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });

        // Partage de fin de cycle : calculé, puis validé par le comité (ou rejeté pour recalcul).
        Schema::create('partages_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('cycle_id');
            $table->char('devise', 3);
            $table->bigInteger('montant_total');
            $table->string('statut', 20);                  // brouillon | valide | rejete
            $table->unsignedBigInteger('calcule_par_id');
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->timestamp('decide_le')->nullable();
            $table->boolean('actif_flag')->nullable()->storedAs("CASE WHEN statut <> 'rejete' THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['cycle_id', 'actif_flag']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['cycle_id', 'organisation_id'], 'partages_cycle_fk')->references(['id', 'organisation_id'])->on('cycles_tontine');
        });

        Schema::create('lignes_partage_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('partage_id');
            $table->unsignedBigInteger('membre_id');
            $table->unsignedInteger('parts')->nullable();   // AVEC
            $table->char('devise', 3);
            $table->bigInteger('part');
            $table->bigInteger('compensation');             // dette réglée sur la part
            $table->bigInteger('verse');                    // versé sur l'épargne
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['partage_id', 'membre_id']);
            $table->foreign(['partage_id', 'organisation_id'], 'lignes_partage_fk')->references(['id', 'organisation_id'])->on('partages_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'lignes_partage_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });

        InsertionSeule::proteger('lignes_partage_tontine');
    }

    public function down(): void
    {
        InsertionSeule::retirer('lignes_partage_tontine');
        Schema::dropIfExists('lignes_partage_tontine');
        Schema::dropIfExists('partages_tontine');
        Schema::dropIfExists('aides_tontine');
    }
};
