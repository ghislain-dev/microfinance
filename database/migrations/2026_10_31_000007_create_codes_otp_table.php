<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_otp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->char('code_hache', 64); // HMAC-SHA256, jamais le code en clair
            $table->string('canal', 10);
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->timestamp('expire_le');
            $table->timestamp('utilise_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'utilise_le', 'expire_le']);
            $table->foreign(['user_id', 'organisation_id'])->references(['id', 'organisation_id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_otp');
    }
};
