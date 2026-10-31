<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Epargne\Remuneration\Actions\CalculerInterets as Calculer;
use App\Domain\Epargne\Remuneration\RemunerationRefusee;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Montant\Devise;
use Illuminate\Console\Command;

class CalculerInterets extends Command
{
    protected $signature = 'epargne:calculer-interets {periode? : Mois AAAA-MM (par défaut : le mois écoulé)}';

    protected $description = 'Calcule les intérêts d’épargne du mois (lots à valider par le gérant ou le comptable)';

    public function handle(Calculer $calculer): int
    {
        $periode = (string) ($this->argument('periode') ?? today()->subMonthNoOverflow()->format('Y-m'));

        // Pas d'utilisateur connecté : chaque organisation, chaque devise, explicitement.
        Organisation::query()->where('actif', true)->orderBy('id')->each(function (Organisation $organisation) use ($calculer, $periode) {
            foreach (Devise::cases() as $devise) {
                try {
                    $lot = $calculer->executer($organisation, $periode, $devise);
                    $this->info("{$organisation->code} {$devise->value} {$periode} : {$lot->montant_total->formate()} à valider.");
                } catch (RemunerationRefusee $e) {
                    $this->warn("{$organisation->code} : {$e->getMessage()}");
                }
            }
        });

        return self::SUCCESS;
    }
}
