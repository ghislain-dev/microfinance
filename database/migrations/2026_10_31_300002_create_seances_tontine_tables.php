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
        Schema::create('seances_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedSmallInteger('rang');
            $table->date('date_prevue');
            $table->string('statut', 20);                         // prevue | tenue
            $table->timestamp('tenue_le')->nullable();
            $table->unsignedBigInteger('saisie_par_id')->nullable();
            $table->unsignedBigInteger('session_caisse_id')->nullable();
            $table->timestamps();

            $table->unique(['cycle_id', 'rang']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['cycle_id', 'organisation_id'], 'seances_cycle_fk')->references(['id', 'organisation_id'])->on('cycles_tontine');
            $table->foreign(['session_caisse_id', 'organisation_id'], 'seances_session_fk')->references(['id', 'organisation_id'])->on('sessions_caisse');
        });

        // Ordre de passage : fixé au démarrage (tirage, ordre fixé) ou séance par séance (enchère). Jamais recalculé.
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedSmallInteger('rang');
            $table->unsignedBigInteger('membre_id');
            $table->bigInteger('remise')->default(0);             // enchère : remise cédée à l'organisation
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cycle_id', 'rang']);
            $table->unique(['cycle_id', 'membre_id']);            // un membre reçoit une fois par cycle
            $table->foreign(['cycle_id', 'organisation_id'], 'tours_cycle_fk')->references(['id', 'organisation_id'])->on('cycles_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'tours_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });

        Schema::create('presences_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('seance_id');
            $table->unsignedBigInteger('membre_id');
            $table->string('presence', 10);                       // present | retard | absent
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['seance_id', 'membre_id']);
            $table->foreign(['seance_id', 'organisation_id'], 'presences_seance_fk')->references(['id', 'organisation_id'])->on('seances_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'presences_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });

        // Encaissements de la séance : une ligne par membre, par séance et par type (skill §5).
        Schema::create('cotisations_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('seance_id');
            $table->unsignedBigInteger('membre_id');
            $table->string('type', 20);                           // cotisation | parts | fonds_social | reglement
            $table->bigInteger('montant');
            $table->unsignedTinyInteger('parts')->nullable();
            $table->unsignedBigInteger('operation_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['seance_id', 'membre_id', 'type']);
            $table->foreign(['seance_id', 'organisation_id'], 'cotisations_seance_fk')->references(['id', 'organisation_id'])->on('seances_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'cotisations_membre_fk')->references(['id', 'organisation_id'])->on('membres');
            $table->foreign(['operation_id', 'organisation_id'], 'cotisations_operation_fk')->references(['id', 'organisation_id'])->on('operations');
        });

        // Dettes constatées envers le cycle : cotisation ou fonds social non payés, pénalités.
        Schema::create('dettes_tontine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedBigInteger('seance_id');
            $table->unsignedBigInteger('membre_id');
            $table->string('nature', 30);
            $table->bigInteger('montant');
            $table->unsignedBigInteger('operation_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['seance_id', 'membre_id', 'nature']);
            $table->index(['cycle_id', 'membre_id']);
            $table->foreign(['seance_id', 'organisation_id'], 'dettes_seance_fk')->references(['id', 'organisation_id'])->on('seances_tontine');
            $table->foreign(['membre_id', 'organisation_id'], 'dettes_membre_fk')->references(['id', 'organisation_id'])->on('membres');
            $table->foreign(['operation_id', 'organisation_id'], 'dettes_operation_fk')->references(['id', 'organisation_id'])->on('operations');
        });

        foreach (['tours', 'presences_tontine', 'cotisations_tontine', 'dettes_tontine'] as $table) {
            InsertionSeule::proteger($table);
        }
    }

    public function down(): void
    {
        foreach (['dettes_tontine', 'cotisations_tontine', 'presences_tontine', 'tours'] as $table) {
            InsertionSeule::retirer($table);
            Schema::dropIfExists($table);
        }
        Schema::dropIfExists('seances_tontine');
    }
};
