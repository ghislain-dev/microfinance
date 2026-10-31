<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Insertion seule : pas de clé étrangère (une suppression ou un ON DELETE SET NULL
        // modifierait l'historique), pas de updated_at.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 60);
            $table->string('cible_type', 60)->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('appareil')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organisation_id', 'created_at']);
            $table->index(['cible_type', 'cible_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });

        foreach (['UPDATE', 'DELETE'] as $ordre) {
            $nom = 'audit_logs_pas_de_'.strtolower($ordre);
            match (DB::getDriverName()) {
                'mysql' => DB::unprepared("CREATE TRIGGER {$nom} BEFORE {$ordre} ON audit_logs FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs : insertion seule'"),
                'sqlite' => DB::unprepared("CREATE TRIGGER {$nom} BEFORE {$ordre} ON audit_logs
                    BEGIN SELECT RAISE(ABORT, 'audit_logs : insertion seule'); END"),
                default => throw new RuntimeException('Base non prise en charge : '.DB::getDriverName()),
            };
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_pas_de_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_pas_de_delete');
        Schema::dropIfExists('audit_logs');
    }
};
