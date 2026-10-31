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
        Schema::create('caisses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('agence_id');
            $table->string('code', 30);
            $table->string('libelle');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['agence_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('agences')->restrictOnDelete();
        });

        // Un compte de caisse (grand livre) par devise.
        Schema::create('caisse_comptes', function (Blueprint $table) {
            $table->unsignedBigInteger('caisse_id');
            $table->unsignedBigInteger('organisation_id');
            $table->char('devise', 3);
            $table->unsignedBigInteger('compte_id')->unique();

            $table->primary(['caisse_id', 'devise']);
            $table->foreign(['caisse_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('caisses');
            $table->foreign(['compte_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('comptes');
        });

        Schema::create('sessions_caisse', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('caisse_id');
            $table->unsignedBigInteger('caissier_id');
            $table->string('statut', 30);
            $table->timestamp('ouverte_le');
            $table->timestamp('fermee_le')->nullable();
            $table->unsignedBigInteger('valide_par_id')->nullable();
            $table->string('motif_validation', 500)->nullable();
            // 1 tant que la session n'est pas fermée, NULL ensuite : l'UNIQUE n'agit que sur les sessions actives
            // (fonctionne sur MySQL et SQLite).
            $table->boolean('active_flag')->nullable()->storedAs("CASE WHEN statut <> 'fermee' THEN 1 ELSE NULL END");
            $table->timestamps();

            $table->unique(['caisse_id', 'active_flag']);   // une session active par caisse
            $table->unique(['caissier_id', 'active_flag']); // une session active par caissier
            $table->index(['organisation_id', 'statut']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['caisse_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('caisses');
            $table->foreign(['caissier_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['valide_par_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('users');
        });

        Schema::create('billetages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('session_caisse_id');
            $table->string('moment', 10);
            $table->char('devise', 3);
            $table->unsignedInteger('coupure');
            $table->unsignedInteger('nombre');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['session_caisse_id', 'moment', 'devise', 'coupure']);
            $table->foreign(['session_caisse_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('sessions_caisse');
        });

        // Écart constaté (compté - théorique), en centimes ; la validation le comptabilise par le moteur.
        Schema::create('ecarts_caisse', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('session_caisse_id');
            $table->string('moment', 10);
            $table->char('devise', 3);
            $table->bigInteger('theorique');
            $table->bigInteger('compte');
            $table->bigInteger('ecart');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['session_caisse_id', 'moment', 'devise']);
            $table->foreign(['session_caisse_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('sessions_caisse');
        });

        InsertionSeule::proteger('billetages');
        InsertionSeule::proteger('ecarts_caisse');
    }

    public function down(): void
    {
        InsertionSeule::retirer('ecarts_caisse');
        InsertionSeule::retirer('billetages');
        Schema::dropIfExists('ecarts_caisse');
        Schema::dropIfExists('billetages');
        Schema::dropIfExists('sessions_caisse');
        Schema::dropIfExists('caisse_comptes');
        Schema::dropIfExists('caisses');
    }
};
