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
        // Au-delà de ce montant, un retrait ou un virement attend la validation d'une seconde personne.
        Schema::create('seuils_validation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->char('devise', 3);
            $table->bigInteger('montant');
            $table->timestamps();

            $table->unique(['organisation_id', 'devise']);
        });

        Schema::create('demandes_decaissement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->string('type', 20);                        // retrait | virement
            $table->unsignedBigInteger('compte_epargne_id');
            $table->unsignedBigInteger('destination_compte_epargne_id')->nullable();
            $table->bigInteger('montant');
            $table->char('devise', 3);
            $table->unsignedBigInteger('demandeur_id');
            $table->string('statut', 20);
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->timestamp('decide_le')->nullable();
            $table->unsignedBigInteger('operation_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['organisation_id', 'statut']);
            // Noms courts : MySQL limite les identifiants à 64 caractères.
            $table->foreign(['compte_epargne_id', 'organisation_id'], 'demandes_dec_compte_fk')->references(['id', 'organisation_id'])->on('comptes_epargne');
            $table->foreign(['destination_compte_epargne_id', 'organisation_id'], 'demandes_dec_destination_fk')->references(['id', 'organisation_id'])->on('comptes_epargne');
            $table->foreign(['demandeur_id', 'organisation_id'], 'demandes_dec_demandeur_fk')->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['decide_par_id', 'organisation_id'], 'demandes_dec_decideur_fk')->references(['id', 'organisation_id'])->on('users');
        });

        // Ligne métier de chaque opération d'épargne, liée à l'opération du moteur (base du reçu).
        Schema::create('mouvements_epargne', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('compte_epargne_id');
            $table->unsignedBigInteger('operation_id');
            $table->string('type', 20);                        // depot | retrait | virement_sortant | virement_entrant
            $table->bigInteger('montant');
            $table->bigInteger('frais')->default(0);
            $table->char('devise', 3);
            $table->unsignedBigInteger('session_caisse_id')->nullable();
            $table->unsignedBigInteger('auteur_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['operation_id', 'compte_epargne_id']);
            $table->index(['compte_epargne_id', 'id']);
            $table->foreign(['compte_epargne_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('comptes_epargne');
            $table->foreign(['operation_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('operations');
            $table->foreign(['session_caisse_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('sessions_caisse');
        });

        InsertionSeule::proteger('mouvements_epargne');
    }

    public function down(): void
    {
        InsertionSeule::retirer('mouvements_epargne');
        Schema::dropIfExists('mouvements_epargne');
        Schema::dropIfExists('demandes_decaissement');
        Schema::dropIfExists('seuils_validation');
    }
};
