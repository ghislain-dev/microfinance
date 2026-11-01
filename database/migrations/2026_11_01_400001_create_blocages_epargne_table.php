<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Montant rendu non retirable sur un compte d'épargne (ex. garantie d'un prêt), levé à la fin de l'engagement.
        Schema::create('blocages_epargne', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('compte_epargne_id');
            $table->char('devise', 3);
            $table->bigInteger('montant');
            $table->string('reference', 40);                 // « pret:12 »
            $table->string('motif', 255);
            $table->timestamp('leve_le')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'reference']);
            $table->index(['compte_epargne_id', 'leve_le']);
            $table->foreign(['compte_epargne_id', 'organisation_id'], 'blocages_compte_fk')->references(['id', 'organisation_id'])->on('comptes_epargne');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocages_epargne');
    }
};
