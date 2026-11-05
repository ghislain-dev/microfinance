<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reçu d'un remboursement de prêt (ADR 021), même numérotation REC-… que les reçus d'épargne.
        // solde_apres y est le reste dû du prêt après le paiement.
        Schema::table('recus', function (Blueprint $table) {
            $table->unsignedBigInteger('mouvement_epargne_id')->nullable()->change();
            $table->unsignedBigInteger('remboursement_pret_id')->nullable()->unique();
            $table->foreign('remboursement_pret_id', 'recus_remboursement_fk')->references('id')->on('remboursements_pret');
        });
    }

    public function down(): void
    {
        Schema::table('recus', function (Blueprint $table) {
            $table->dropForeign('recus_remboursement_fk');
            $table->dropUnique(['remboursement_pret_id']);
            $table->dropColumn('remboursement_pret_id');
        });
    }
};
