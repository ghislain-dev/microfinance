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
        Schema::table('types_comptes_epargne', function (Blueprint $table) {
            $table->unsignedInteger('taux_interet_pb')->default(0)->after('duree_blocage_mois'); // 325 = 3,25 %/an
        });

        // Lot d'intérêts (mensuel) ou de ristourne, calculé puis validé avant tout versement.
        Schema::create('remunerations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->string('type', 20);                  // interets | ristourne
            $table->char('devise', 3);
            $table->string('periode', 10);               // « 2026-02 » (intérêts) ou date de référence (ristourne)
            $table->date('date_reference');              // date du solde servant de base
            $table->bigInteger('montant_total');
            $table->string('statut', 20);
            $table->unsignedBigInteger('cree_par_id')->nullable(); // null : commande planifiée
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->timestamp('decide_le')->nullable();
            // 1 sauf si rejeté : un seul lot vivant par période (un rejet permet de recalculer).
            $table->boolean('actif_flag')->nullable()->storedAs("CASE WHEN statut <> 'rejetee' THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['organisation_id', 'type', 'devise', 'periode', 'actif_flag'], 'remunerations_lot_unique');
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['organisation_id'])->references(['id'])->on('organisations');
            $table->foreign(['cree_par_id', 'organisation_id'], 'remunerations_cree_par_fk')->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['decide_par_id', 'organisation_id'], 'remunerations_decide_par_fk')->references(['id', 'organisation_id'])->on('users');
        });

        Schema::create('remuneration_lignes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('remuneration_id');
            $table->unsignedBigInteger('compte_epargne_id');
            $table->char('devise', 3);
            $table->bigInteger('base');                  // solde à la date de référence
            $table->bigInteger('montant');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['remuneration_id', 'compte_epargne_id']);
            $table->foreign(['remuneration_id', 'organisation_id'], 'remun_lignes_lot_fk')->references(['id', 'organisation_id'])->on('remunerations');
            $table->foreign(['compte_epargne_id', 'organisation_id'], 'remun_lignes_compte_fk')->references(['id', 'organisation_id'])->on('comptes_epargne');
        });

        InsertionSeule::proteger('remuneration_lignes');
    }

    public function down(): void
    {
        InsertionSeule::retirer('remuneration_lignes');
        Schema::dropIfExists('remuneration_lignes');
        Schema::dropIfExists('remunerations');
        Schema::table('types_comptes_epargne', function (Blueprint $table) {
            $table->dropColumn('taux_interet_pb');
        });
    }
};
