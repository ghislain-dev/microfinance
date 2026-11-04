<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jetons Firebase des téléphones des membres (un jeton n'appartient qu'à un utilisateur à la fois).
        Schema::create('jetons_push', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('jeton', 255)->unique();
            $table->string('plateforme', 10);
            $table->timestamps();

            $table->foreign(['user_id', 'organisation_id'], 'jetons_push_user_fk')->references(['id', 'organisation_id'])->on('users')->cascadeOnDelete();
        });

        // Historique des notifications ; la clé empêche d'envoyer deux fois le même avis.
        Schema::create('notifications_push', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('cle', 120);
            $table->string('titre', 100);
            $table->string('corps', 500);
            $table->json('donnees');
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'cle']);
            $table->index(['user_id', 'created_at']);
            $table->foreign(['user_id', 'organisation_id'], 'notifications_push_user_fk')->references(['id', 'organisation_id'])->on('users');
        });

        // Rappel des échéances de prêt : nombre de jours avant (vide = pas de rappel), fixé par l'organisation.
        Schema::table('organisations', function (Blueprint $table) {
            $table->unsignedTinyInteger('rappel_echeance_jours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', fn (Blueprint $table) => $table->dropColumn('rappel_echeance_jours'));
        Schema::dropIfExists('notifications_push');
        Schema::dropIfExists('jetons_push');
    }
};
