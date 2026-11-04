<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Actions\EnvoyerRappels as Envoyer;
use App\Domain\Organisation\Models\Organisation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class EnvoyerRappels extends Command
{
    protected $signature = 'notifications:rappels';

    protected $description = 'Rappels push aux membres : échéances de prêt et veille de leur tour de tontine';

    public function handle(Envoyer $envoyer): int
    {
        $aujourdhui = CarbonImmutable::today();
        // Pas d'utilisateur connecté : chaque organisation, explicitement.
        Organisation::query()->where('actif', true)->orderBy('id')->each(fn (Organisation $o) => $envoyer->executer($o, $aujourdhui));

        return self::SUCCESS;
    }
}
