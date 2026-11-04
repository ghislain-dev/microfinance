<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rapports PDF / CSV générés en file d'attente (CLAUDE.md §6), téléchargés ensuite par leur auteur.
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 20);                       // balance | grand_livre | journal | releve | portefeuille | caisse
            $table->string('format', 5);                      // pdf | csv
            $table->json('parametres');
            $table->string('statut', 20);                     // en_attente | pret | echec
            $table->string('chemin')->nullable();             // disque local, hors public/
            $table->string('nom_fichier')->nullable();
            $table->string('erreur', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->foreign(['user_id', 'organisation_id'], 'exports_user_fk')->references(['id', 'organisation_id'])->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
