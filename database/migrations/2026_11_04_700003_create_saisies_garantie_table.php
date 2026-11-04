<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Délai de retard à partir duquel la garantie peut être prélevée (vide : jamais), par produit puis figé sur le prêt.
        Schema::table('produits_credit', fn (Blueprint $table) => $table->unsignedSmallInteger('delai_saisie_garantie_jours')->nullable());
        Schema::table('prets', fn (Blueprint $table) => $table->unsignedSmallInteger('delai_saisie_garantie_jours')->nullable());

        // Prélèvement de la garantie en cas de défaut (ADR 021) : demandé par le gérant, validé par le comptable.
        Schema::create('saisies_garantie', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('pret_id');
            $table->char('devise', 3);
            $table->bigInteger('montant');
            $table->string('motif', 500);
            $table->string('statut', 20);                     // en_attente | validee | rejetee
            $table->unsignedBigInteger('demande_par_id');
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->unsignedBigInteger('remboursement_id')->nullable()->unique();
            $table->boolean('attente_flag')->nullable()->storedAs("CASE WHEN statut = 'en_attente' THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['pret_id', 'attente_flag']);
            $table->foreign(['pret_id', 'organisation_id'], 'saisies_garantie_pret_fk')->references(['id', 'organisation_id'])->on('prets');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saisies_garantie');
        Schema::table('prets', fn (Blueprint $table) => $table->dropColumn('delai_saisie_garantie_jours'));
        Schema::table('produits_credit', fn (Blueprint $table) => $table->dropColumn('delai_saisie_garantie_jours'));
    }
};
