<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Produits d'épargne paramétrables (2026-10-31) ; montants en centimes de la devise du type.
        Schema::create('types_comptes_epargne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->string('libelle', 100);
            $table->char('devise', 3);
            $table->bigInteger('solde_minimum')->default(0);
            $table->bigInteger('depot_minimum')->default(0);
            $table->bigInteger('frais_retrait')->default(0);
            $table->unsignedSmallInteger('duree_blocage_mois')->nullable(); // NULL : retraits libres
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            $table->unique(['id', 'organisation_id']);
        });

        Schema::create('comptes_epargne', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('membre_id');
            $table->unsignedBigInteger('type_compte_epargne_id');
            $table->unsignedBigInteger('compte_id')->unique(); // compte du grand livre
            $table->string('numero', 60);                      // « BUK-000045-DAT-2 »
            $table->unsignedSmallInteger('rang');
            $table->date('date_ouverture');
            $table->date('bloque_jusqu_au')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'numero']);
            $table->unique(['membre_id', 'type_compte_epargne_id', 'rang']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['membre_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('membres');
            $table->foreign(['type_compte_epargne_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('types_comptes_epargne');
            $table->foreign(['compte_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('comptes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comptes_epargne');
        Schema::dropIfExists('types_comptes_epargne');
    }
};
