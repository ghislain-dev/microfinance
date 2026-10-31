<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('nom');
            $table->string('code', 30);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
            // Cible des clés étrangères composées : interdit les liens entre organisations.
            $table->unique(['id', 'organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
