<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Credit\Actions\ConstaterPenalites as Constater;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Temps\Horloge;
use Illuminate\Console\Command;

class ConstaterPenalites extends Command
{
    protected $signature = 'credit:constater-penalites';

    protected $description = 'Constate les pénalités de retard des prêts (une par échéance et par jour, nuits manquées rattrapées)';

    public function handle(Constater $constater): int
    {

        // Pas d'utilisateur connecté : chaque organisation, explicitement.
        Organisation::query()->where('actif', true)->orderBy('id')->each(function (Organisation $organisation) use ($constater) {
            $nombre = $constater->executer($organisation, Horloge::aujourdhui($organisation->id));
            $this->info("{$organisation->code} : {$nombre} pénalité(s) constatée(s).");
        });

        return self::SUCCESS;
    }
}
