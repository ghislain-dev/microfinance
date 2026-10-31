<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->after('id')->constrained()->restrictOnDelete();
            $table->string('telephone', 30)->nullable()->after('email')->index();
            $table->boolean('actif')->default(true)->after('password');

            $table->unique(['id', 'organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['id', 'organisation_id']);
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropIndex(['telephone']);
            $table->dropColumn(['telephone', 'actif']);
        });
    }
};
