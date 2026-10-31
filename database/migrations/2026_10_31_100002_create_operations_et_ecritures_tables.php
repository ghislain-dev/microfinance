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
        // En-tête d'opération : une opération = une devise, une clé d'idempotence unique.
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->string('type', 50);
            $table->char('devise', 3);
            $table->unsignedBigInteger('auteur_id')->nullable(); // null : commande planifiée
            $table->unsignedBigInteger('valide_par_id')->nullable();
            $table->unsignedBigInteger('extourne_de_id')->nullable();
            $table->date('date_valeur');
            $table->string('motif', 500)->nullable();
            $table->timestamp('created_at')->useCurrent(); // date de saisie

            $table->unique(['id', 'organisation_id']);
            $table->unique('extourne_de_id'); // une opération n'est extournée qu'une fois
            $table->index(['organisation_id', 'date_valeur']);
            $table->index(['organisation_id', 'type']);
            $table->foreign(['auteur_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['valide_par_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('users');
            $table->foreign(['extourne_de_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('operations');
        });

        Schema::create('ecritures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('compte_id');
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->char('devise', 3);
            $table->timestamp('created_at')->useCurrent();

            $table->index('operation_id');
            $table->index(['compte_id', 'id']);
            $table->foreign(['operation_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('operations');
            $table->foreign(['compte_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('comptes');
        });

        InsertionSeule::proteger('operations');
        InsertionSeule::proteger('ecritures');
        // Chaque ligne porte soit un débit, soit un crédit, strictement positif.
        InsertionSeule::trigger('ecritures_debit_ou_credit', 'BEFORE INSERT', 'ecritures',
            'ecritures : un débit ou un crédit strictement positif, pas les deux',
            'NEW.debit < 0 OR NEW.credit < 0 OR (NEW.debit > 0 AND NEW.credit > 0) OR (NEW.debit = 0 AND NEW.credit = 0)');
    }

    public function down(): void
    {
        InsertionSeule::retirer('ecritures');
        InsertionSeule::retirer('operations');
        Schema::dropIfExists('ecritures');
        Schema::dropIfExists('operations');
    }
};
