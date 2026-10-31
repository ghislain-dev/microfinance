<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('agence_id');
            $table->string('numero', 40);                    // « BUK-000045 »
            $table->unsignedInteger('numero_sequence');
            $table->string('nom', 100);
            $table->string('postnom', 100)->nullable();
            $table->string('prenom', 100)->nullable();
            $table->char('sexe', 1);
            $table->date('date_naissance');
            $table->string('telephone', 20);                 // normalisé : +243…
            $table->string('piece_type', 50);
            $table->text('piece_numero');                    // chiffré (cast encrypted)
            $table->char('piece_empreinte', 64);             // HMAC type + numéro : détection des doublons
            $table->string('adresse')->nullable();
            $table->string('statut', 20)->default('actif');
            $table->timestamps();

            $table->unique(['organisation_id', 'numero']);
            $table->unique(['agence_id', 'numero_sequence']);
            $table->unique(['organisation_id', 'piece_empreinte']); // même pièce : refusée par la base
            $table->unique(['id', 'organisation_id']);
            $table->index(['organisation_id', 'telephone']);
            $table->index(['organisation_id', 'nom']);
            $table->index(['organisation_id', 'statut']);
            $table->foreign(['agence_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('agences')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membres');
    }
};
