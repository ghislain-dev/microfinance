<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\Actions\CloturerPeriode as Cloturer;
use App\Domain\Organisation\Models\Organisation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CloturerPeriode extends Command
{
    protected $signature = 'finance:cloturer {organisation : Code de l’organisation} {date : Dernier jour clôturé (AAAA-MM-JJ)} {--force : Ne pas demander confirmation}';

    protected $description = 'Clôture définitivement la période jusqu’à la date donnée (aucune écriture ne sera plus possible)';

    public function handle(Cloturer $cloturer): int
    {
        $organisation = Organisation::where('code', (string) $this->argument('organisation'))->first();
        if ($organisation === null) {
            $this->error('Organisation inconnue.');

            return self::FAILURE;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->argument('date'));
        if ($date === null || $date->format('Y-m-d') !== $this->argument('date')) {
            $this->error('Date invalide : format AAAA-MM-JJ attendu.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Clôturer « {$organisation->nom} » jusqu'au {$date->toDateString()} inclus ? C'est définitif.")) {
            return self::FAILURE;
        }

        try {
            $cloturer->executer($organisation, $date, 'console:'.(get_current_user() ?: 'inconnu'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Période clôturée jusqu'au {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
