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
        // Une clôture « jusqu'au date_fin inclus » est définitive : insertion seule.
        Schema::create('clotures_comptables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->date('date_fin');
            $table->string('par', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organisation_id', 'date_fin']);
        });

        InsertionSeule::proteger('clotures_comptables');
    }

    public function down(): void
    {
        InsertionSeule::retirer('clotures_comptables');
        Schema::dropIfExists('clotures_comptables');
    }
};
