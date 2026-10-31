<?php

declare(strict_types=1);

/*
 * Processus lancé en parallèle : inscrit un membre dans une agence. Affiche le numéro attribué
 * ou ERREUR:<message>. Usage : php inscription.php <depart_microtime> <acteur_id> <agence_id> <n>
 */

use App\Domain\Membres\Actions\InscrireMembre;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $depart, $acteurId, $agenceId, $n] = $argv;

$acteur = User::findOrFail((int) $acteurId);
$agence = Agence::findOrFail((int) $agenceId);

$attente = (int) (((float) $depart - microtime(true)) * 1_000_000);
if ($attente > 0) {
    usleep($attente);
}

try {
    $membre = $app->make(InscrireMembre::class)->executer($acteur, $agence, [
        'nom' => "Membre {$n}", 'sexe' => 'F', 'date_naissance' => '1995-01-01',
        'telephone' => '0970'.str_pad($n, 6, '0', STR_PAD_LEFT),
        'piece_type' => 'Carte d’électeur', 'piece_numero' => "PAR-{$n}",
    ]);
    echo $membre->numero;
} catch (Throwable $e) {
    echo 'ERREUR:'.$e::class.' '.$e->getMessage();
}
