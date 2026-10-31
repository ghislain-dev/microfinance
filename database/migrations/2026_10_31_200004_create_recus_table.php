<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('logo_chemin')->nullable()->after('code'); // disque privé « local »
        });

        // Un reçu par mouvement d'épargne ; solde photographié au moment de l'opération.
        Schema::create('recus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('mouvement_epargne_id')->unique();
            $table->unsignedInteger('sequence');
            $table->string('numero', 20);                 // « REC-000001 »
            $table->bigInteger('solde_apres');
            $table->char('devise', 3);
            $table->string('chemin')->nullable();         // PDF généré en file d'attente
            $table->timestamps();

            $table->unique(['organisation_id', 'sequence']);
            $table->unique(['organisation_id', 'numero']);
            $table->foreign(['organisation_id'])->references(['id'])->on('organisations');
            $table->foreign(['mouvement_epargne_id'])->references(['id'])->on('mouvements_epargne');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recus');
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('logo_chemin');
        });
    }
};
