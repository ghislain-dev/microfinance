<?php

declare(strict_types=1);

/*
 * Processus lancé en parallèle : tente de rembourser un prêt en espèces.
 * Usage : php remboursement.php <depart_microtime> <caissier_id> <pret_id> <montant> <cle>
 */

use App\Domain\Credit\Actions\RembourserPret;
use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\ModeRemboursement;
use App\Models\User;
use App\Support\Montant\Montant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $depart, $caissierId, $pretId, $montant, $cle] = $argv;
$caissier = User::findOrFail((int) $caissierId);
Auth::setUser($caissier);
$pret = Pret::findOrFail((int) $pretId);

$attente = (int) (((float) $depart - microtime(true)) * 1_000_000);
if ($attente > 0) {
    usleep($attente);
}

try {
    $app->make(RembourserPret::class)->executer($caissier, $pret, Montant::depuisSaisie($montant, $pret->devise), ModeRemboursement::Especes, $cle);
    echo 'OK';
} catch (CreditRefuse) {
    echo 'REFUS';
} catch (Throwable $e) {
    echo 'ERREUR:'.$e::class.' '.$e->getMessage();
}
