<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accès mobile des membres (ADR 022) : pas d'e-mail, identifiant = numéro de membre.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unsignedBigInteger('membre_id')->nullable()->unique()->after('organisation_id');
            $table->boolean('mot_de_passe_provisoire')->default(false)->after('password');
            $table->foreign(['membre_id', 'organisation_id'], 'users_membre_fk')->references(['id', 'organisation_id'])->on('membres');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_membre_fk');
            $table->dropUnique(['membre_id']);
            $table->dropColumn(['membre_id', 'mot_de_passe_provisoire']);
        });
    }
};
