<?php

declare(strict_types=1);

/*
 * Processus lancé en parallèle : envoie le même lot de synchronisation.
 * Usage : php synchronisation.php <depart_microtime> <collecteur_id> <appareil_id> <json_operations>
 */

use App\Domain\Collecte\Models\Appareil;
use App\Domain\Collecte\Services\Synchronisation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $depart, $collecteurId, $appareilId, $json] = $argv;
$collecteur = User::findOrFail((int) $collecteurId);
Auth::setUser($collecteur);
$appareil = Appareil::findOrFail((int) $appareilId);
$operations = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

$attente = (int) (((float) $depart - microtime(true)) * 1_000_000);
if ($attente > 0) {
    usleep($attente);
}

try {
    $resultats = $app->make(Synchronisation::class)->traiter($collecteur, $appareil, (string) Str::uuid(), $operations);
    echo $resultats[0]['statut'];
} catch (Throwable $e) {
    echo 'ERREUR:'.$e::class.' '.$e->getMessage();
}
