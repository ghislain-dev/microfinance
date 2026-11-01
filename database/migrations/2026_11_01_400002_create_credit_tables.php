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
        // Produits de crédit paramétrables (2026-11-01) ; taux et pourcentages en points de base (300 = 3 %).
        Schema::create('produits_credit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->string('libelle', 100);
            $table->char('devise', 3);
            $table->unsignedInteger('taux_mensuel_pb');
            $table->string('periodicite', 20);
            $table->unsignedSmallInteger('duree_max');          // nombre d'échéances au plus
            $table->bigInteger('montant_minimum');
            $table->bigInteger('montant_maximum');
            $table->unsignedSmallInteger('anciennete_mois')->default(0);
            $table->unsignedSmallInteger('multiple_epargne');   // plafond = N × épargne
            $table->unsignedInteger('garantie_epargne_pb');     // part du prêt bloquée sur l'épargne
            $table->unsignedInteger('penalite_jour_pb');        // par jour, sur le capital impayé
            $table->unsignedSmallInteger('delai_grace_jours')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            $table->unique(['id', 'organisation_id']);
        });

        // Au-delà de ce montant, le gérant approuve après le comité.
        Schema::create('seuils_credit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->char('devise', 3);
            $table->bigInteger('montant');
            $table->timestamps();

            $table->unique(['organisation_id', 'devise']);
        });

        Schema::create('prets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('membre_id');
            $table->unsignedBigInteger('produit_credit_id');
            $table->unsignedBigInteger('compte_epargne_id');    // reçoit le décaissement, porte la garantie
            $table->string('numero', 60);                       // « BUK-000045-CR1 »
            $table->unsignedSmallInteger('rang');
            $table->char('devise', 3);
            $table->bigInteger('montant');
            // Conditions figées à la demande.
            $table->unsignedInteger('taux_mensuel_pb');
            $table->string('periodicite', 20);
            $table->unsignedSmallInteger('nombre_echeances');
            $table->string('methode', 20);
            $table->unsignedInteger('garantie_epargne_pb');
            $table->unsignedInteger('penalite_jour_pb');
            $table->unsignedSmallInteger('delai_grace_jours');
            $table->string('objet', 500);
            $table->text('analyse')->nullable();
            $table->string('statut', 20);
            $table->unsignedBigInteger('saisi_par_id');
            $table->unsignedBigInteger('comite_par_id')->nullable();
            $table->timestamp('comite_le')->nullable();
            $table->unsignedBigInteger('gerant_par_id')->nullable();
            $table->timestamp('gerant_le')->nullable();
            $table->unsignedBigInteger('rejete_par_id')->nullable();
            $table->string('motif_decision', 500)->nullable();
            $table->unsignedBigInteger('decaisse_par_id')->nullable();
            $table->date('date_decaissement')->nullable();
            $table->bigInteger('garantie_epargne')->nullable(); // montant bloqué au décaissement
            $table->unsignedBigInteger('compte_pret_id')->nullable()->unique();
            $table->unsignedBigInteger('compte_penalites_id')->nullable()->unique();
            $table->unsignedBigInteger('operation_decaissement_id')->nullable()->unique();
            $table->timestamp('solde_le')->nullable();
            // Un seul prêt à la fois par membre : demande, prêt approuvé ou en cours (2026-11-01).
            $table->boolean('actif_flag')->nullable()->storedAs("CASE WHEN statut IN ('rejete', 'solde') THEN NULL ELSE 1 END");
            $table->timestamps();

            $table->unique(['organisation_id', 'numero']);
            $table->unique(['membre_id', 'rang']);
            $table->unique(['membre_id', 'actif_flag']);
            $table->unique(['id', 'organisation_id']);
            $table->index(['organisation_id', 'statut']);
            $table->foreign(['membre_id', 'organisation_id'], 'prets_membre_fk')->references(['id', 'organisation_id'])->on('membres');
            $table->foreign(['produit_credit_id', 'organisation_id'], 'prets_produit_fk')->references(['id', 'organisation_id'])->on('produits_credit');
            $table->foreign(['compte_epargne_id', 'organisation_id'], 'prets_epargne_fk')->references(['id', 'organisation_id'])->on('comptes_epargne');
            $table->foreign(['compte_pret_id', 'organisation_id'], 'prets_compte_fk')->references(['id', 'organisation_id'])->on('comptes');
            $table->foreign(['compte_penalites_id', 'organisation_id'], 'prets_penalites_fk')->references(['id', 'organisation_id'])->on('comptes');
            $table->foreign(['operation_decaissement_id', 'organisation_id'], 'prets_operation_fk')->references(['id', 'organisation_id'])->on('operations');
            $table->foreign(['saisi_par_id', 'organisation_id'], 'prets_saisi_fk')->references(['id', 'organisation_id'])->on('users');
        });

        // Garanties : caution d'un autre membre (engagement seul) ou bien matériel (sans écriture).
        Schema::create('garanties_pret', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('pret_id');
            $table->string('type', 20);                         // caution | bien
            $table->unsignedBigInteger('membre_id')->nullable(); // caution
            $table->char('devise', 3);
            $table->bigInteger('montant')->nullable();          // montant cautionné ou valeur estimée du bien
            $table->string('description', 500)->nullable();
            $table->string('piece_chemin')->nullable();         // pièce jointe (disque local)
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pret_id', 'membre_id']);
            $table->foreign(['pret_id', 'organisation_id'], 'garanties_pret_fk')->references(['id', 'organisation_id'])->on('prets');
            $table->foreign(['membre_id', 'organisation_id'], 'garanties_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });

        // Échéancier calculé au décaissement ; les montants prévus ne changent jamais, les montants payés
        // suivent les remboursements (chacun tracé par une opération du moteur).
        Schema::create('echeances_pret', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('pret_id');
            $table->unsignedSmallInteger('rang');
            $table->date('date_echeance');
            $table->char('devise', 3);
            $table->bigInteger('capital');
            $table->bigInteger('interet');
            $table->bigInteger('capital_paye')->default(0);
            $table->bigInteger('interet_paye')->default(0);
            $table->bigInteger('penalite')->default(0);
            $table->bigInteger('penalite_payee')->default(0);
            $table->timestamp('payee_le')->nullable();
            $table->timestamps();

            $table->unique(['pret_id', 'rang']);
            $table->unique(['id', 'organisation_id']);
            $table->index(['organisation_id', 'date_echeance', 'payee_le']);
            $table->foreign(['pret_id', 'organisation_id'], 'echeances_pret_fk')->references(['id', 'organisation_id'])->on('prets');
        });

        InsertionSeule::proteger('garanties_pret');
    }

    public function down(): void
    {
        InsertionSeule::retirer('garanties_pret');
        Schema::dropIfExists('echeances_pret');
        Schema::dropIfExists('garanties_pret');
        Schema::dropIfExists('prets');
        Schema::dropIfExists('seuils_credit');
        Schema::dropIfExists('produits_credit');
    }
};
