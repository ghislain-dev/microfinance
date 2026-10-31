<?php

declare(strict_types=1);

/*
 * Processus lancé en parallèle par ConcurrenceTest : attend l'instant de départ commun,
 * puis confie un retrait au MoteurFinancier. Affiche OK, REFUS:<classe> ou ERREUR:<message>.
 * Usage : php operation.php <depart_microtime> <cle> <compte_debite> <compte_credite> <centimes>
 */

use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $depart, $cle, $debite, $credite, $centimes] = $argv;

$attente = (int) (((float) $depart - microtime(true)) * 1_000_000);
if ($attente > 0) {
    usleep($attente);
}

try {
    $montant = new Montant((int) $centimes, Devise::USD);
    $app->make(MoteurFinancier::class)->executer($cle, 'retrait', [
        LigneEcriture::debit((int) $debite, $montant),
        LigneEcriture::credit((int) $credite, $montant),
    ]);
    echo 'OK';
} catch (RefusFinancier $e) {
    echo 'REFUS:'.class_basename($e);
} catch (Throwable $e) {
    echo 'ERREUR:'.$e::class.' '.$e->getMessage();
}
