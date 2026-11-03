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
        // Lots reçus des téléphones et résultat de chaque opération (skill §8) : trace de tout ce qui a été
        // saisi sur le terrain, accepté ou refusé. Insertion seule.
        Schema::create('lots_sync', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->uuid('lot_uuid');
            $table->unsignedBigInteger('collecteur_id');
            $table->unsignedBigInteger('appareil_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organisation_id', 'lot_uuid']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['collecteur_id', 'organisation_id'], 'lots_sync_collecteur_fk')->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['appareil_id', 'organisation_id'], 'lots_sync_appareil_fk')->references(['id', 'organisation_id'])->on('appareils');
        });

        Schema::create('operations_sync', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('lot_id');
            $table->uuid('cle');                               // générée au téléphone : une opération une seule fois
            $table->string('type', 20);
            $table->unsignedBigInteger('membre_id')->nullable();
            $table->string('statut', 20);                      // cree | rejete
            $table->string('raison', 500)->nullable();
            $table->unsignedBigInteger('operation_id')->nullable();
            $table->json('donnees');
            $table->timestamp('saisi_le')->nullable();         // heure du téléphone, pour information
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organisation_id', 'cle']);
            $table->index(['organisation_id', 'statut']);
            $table->foreign(['lot_id', 'organisation_id'], 'operations_sync_lot_fk')->references(['id', 'organisation_id'])->on('lots_sync');
        });

        // Versement des espèces d'un collecteur au guichet (ADR 022), traité comme une clôture de caisse.
        Schema::create('versements_collecte', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('collecteur_id');
            $table->unsignedBigInteger('caissier_id');
            $table->unsignedBigInteger('session_caisse_id');
            $table->char('devise', 3);
            $table->bigInteger('theorique');                   // solde de la caisse du collecteur
            $table->bigInteger('compte');                      // billetage du caissier
            $table->bigInteger('ecart');                       // compté − théorique
            $table->json('billetage');
            $table->string('statut', 20);                      // valide | ecart_a_justifier
            $table->unsignedBigInteger('operation_id')->nullable()->unique();
            $table->unsignedBigInteger('valide_par_id')->nullable();
            $table->string('motif_validation', 500)->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'statut']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['collecteur_id', 'organisation_id'], 'versements_collecteur_fk')->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['session_caisse_id', 'organisation_id'], 'versements_session_fk')->references(['id', 'organisation_id'])->on('sessions_caisse');
        });

        InsertionSeule::proteger('lots_sync');
        InsertionSeule::proteger('operations_sync');
    }

    public function down(): void
    {
        InsertionSeule::retirer('operations_sync');
        InsertionSeule::retirer('lots_sync');
        Schema::dropIfExists('versements_collecte');
        Schema::dropIfExists('operations_sync');
        Schema::dropIfExists('lots_sync');
    }
};
