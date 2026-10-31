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
        // Taux du jour (CDF pour 1 USD, en millionièmes) ; une correction = une nouvelle ligne.
        Schema::create('taux_change', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->date('date_application');
            $table->unsignedBigInteger('achat');
            $table->unsignedBigInteger('vente');
            $table->unsignedBigInteger('saisi_par_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organisation_id', 'date_application', 'id']);
            $table->unique(['id', 'organisation_id']);
            $table->foreign(['saisi_par_id', 'organisation_id'], 'taux_change_saisi_par_fk')->references(['id', 'organisation_id'])->on('users');
        });

        // Lien entre les deux opérations (une par devise) d'une conversion, avec le taux appliqué.
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('membre_id');
            $table->unsignedBigInteger('compte_source_id');
            $table->unsignedBigInteger('compte_destination_id');
            $table->unsignedBigInteger('taux_change_id');
            $table->unsignedBigInteger('taux_applique');          // millionièmes
            $table->char('devise_source', 3);
            $table->bigInteger('montant_source');
            $table->char('devise_destination', 3);
            $table->bigInteger('montant_destination');
            $table->bigInteger('marge');                          // dans la devise de destination
            $table->unsignedBigInteger('operation_source_id')->unique();
            $table->unsignedBigInteger('operation_destination_id')->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organisation_id', 'created_at']);
            $table->foreign(['membre_id', 'organisation_id'], 'conversions_membre_fk')->references(['id', 'organisation_id'])->on('membres');
            $table->foreign(['compte_source_id', 'organisation_id'], 'conversions_source_fk')->references(['id', 'organisation_id'])->on('comptes_epargne');
            $table->foreign(['compte_destination_id', 'organisation_id'], 'conversions_destination_fk')->references(['id', 'organisation_id'])->on('comptes_epargne');
            $table->foreign(['taux_change_id', 'organisation_id'], 'conversions_taux_fk')->references(['id', 'organisation_id'])->on('taux_change');
            $table->foreign(['operation_source_id', 'organisation_id'], 'conversions_op_source_fk')->references(['id', 'organisation_id'])->on('operations');
            $table->foreign(['operation_destination_id', 'organisation_id'], 'conversions_op_destination_fk')->references(['id', 'organisation_id'])->on('operations');
        });

        InsertionSeule::proteger('taux_change');
        InsertionSeule::proteger('conversions');
    }

    public function down(): void
    {
        InsertionSeule::retirer('conversions');
        InsertionSeule::retirer('taux_change');
        Schema::dropIfExists('conversions');
        Schema::dropIfExists('taux_change');
    }
};
