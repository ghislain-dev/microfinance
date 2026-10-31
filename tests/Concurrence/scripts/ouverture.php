<?php

declare(strict_types=1);

/*
 * Processus lancé en parallèle : tente d'ouvrir une session sur une caisse.
 * Usage : php ouverture.php <depart_microtime> <caissier_id> <caisse_id>
 */

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Exceptions\SessionDejaOuverte;
use App\Domain\Caisse\Models\Caisse;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $depart, $caissierId, $caisseId] = $argv;
$caissier = User::findOrFail((int) $caissierId);
$caisse = Caisse::findOrFail((int) $caisseId);

$attente = (int) (((float) $depart - microtime(true)) * 1_000_000);
if ($attente > 0) {
    usleep($attente);
}

try {
    $app->make(OuvrirSession::class)->executer($caissier, $caisse, Billetage::vide());
    echo 'OK';
} catch (SessionDejaOuverte) {
    echo 'REFUS';
} catch (Throwable $e) {
    echo 'ERREUR:'.$e::class.' '.$e->getMessage();
}
