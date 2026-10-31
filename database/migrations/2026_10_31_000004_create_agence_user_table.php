<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agence_user', function (Blueprint $table) {
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('agence_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->primary(['agence_id', 'user_id']);
            $table->index(['user_id', 'organisation_id']);
            // Agence et utilisateur doivent appartenir à la même organisation : garanti par la base.
            $table->foreign(['agence_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('agences')->restrictOnDelete();
            $table->foreign(['user_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agence_user');
    }
};
