<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Téléphones des collecteurs, autorisés (puis révocables) par le gérant (ADR 022).
        Schema::create('appareils', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('identifiant', 64);                 // UUID généré par l'application à l'installation
            $table->string('modele', 100);
            $table->string('statut', 20);                      // en_attente | autorise | revoque
            $table->unsignedBigInteger('decide_par_id')->nullable();
            $table->timestamp('autorise_le')->nullable();
            $table->timestamp('revoque_le')->nullable();
            $table->timestamp('derniere_sync_le')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'identifiant']);
            $table->unique(['id', 'organisation_id']);
            $table->index(['organisation_id', 'statut']);
            $table->foreign(['user_id', 'organisation_id'], 'appareils_user_fk')->references(['id', 'organisation_id'])->on('users');
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('appareil_id')->nullable()->index();
        });

        // Tournée : membres affectés à un collecteur par le gérant ; un retrait est daté (pour la synchronisation).
        Schema::create('tournees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('collecteur_id');
            $table->unsignedBigInteger('membre_id');
            $table->unsignedBigInteger('affecte_par_id');
            $table->timestamp('retire_le')->nullable();
            $table->timestamps();

            $table->unique(['collecteur_id', 'membre_id']);
            $table->index(['collecteur_id', 'updated_at']);
            $table->foreign(['collecteur_id', 'organisation_id'], 'tournees_collecteur_fk')->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['membre_id', 'organisation_id'], 'tournees_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });

        // Caisse personnelle d'un collecteur (ses espèces jusqu'au versement au guichet).
        Schema::table('caisses', function (Blueprint $table) {
            $table->unsignedBigInteger('collecteur_id')->nullable()->unique();
        });

        // Paramètres fixés par le gérant ; sans eux, l'application ne permet aucune saisie.
        Schema::create('parametres_collecte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('delai_max_heures');   // sans synchronisation, plus de saisie au-delà
            $table->timestamps();
        });

        Schema::create('plafonds_collecte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->char('devise', 3);
            $table->bigInteger('plafond_retrait');              // par retrait hors-ligne
            $table->bigInteger('plafond_especes');              // espèces détenues par le collecteur
            $table->timestamps();

            $table->unique(['organisation_id', 'devise']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plafonds_collecte');
        Schema::dropIfExists('parametres_collecte');
        Schema::table('caisses', fn (Blueprint $table) => $table->dropColumn('collecteur_id'));
        Schema::dropIfExists('tournees');
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn('appareil_id'));
        Schema::dropIfExists('appareils');
    }
};
