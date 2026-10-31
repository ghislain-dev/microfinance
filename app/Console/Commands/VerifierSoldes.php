<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\Services\VerificateurSoldes;
use App\Domain\Organisation\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifierSoldes extends Command
{
    protected $signature = 'finance:verifier-soldes {--corriger : Réaligner les caches faux sur le grand livre (l’écart reste signalé)}';

    protected $description = 'Recalcule les soldes depuis le grand livre et alerte en cas d’écart';

    public function handle(VerificateurSoldes $verificateur): int
    {
        $anomalies = 0;

        // Pas d'utilisateur connecté : on parcourt explicitement chaque organisation.
        Organisation::query()->orderBy('id')->each(function (Organisation $organisation) use ($verificateur, &$anomalies) {
            foreach ($verificateur->devisesDesequilibrees($organisation) as $devise) {
                $anomalies++;
                $this->error("{$organisation->code} : grand livre déséquilibré en {$devise->value}");
                Log::critical('Grand livre déséquilibré', ['organisation_id' => $organisation->id, 'devise' => $devise->value]);
            }

            foreach ($verificateur->ecartsDeCache($organisation, (bool) $this->option('corriger')) as $ecart) {
                $anomalies++;
                $this->error("{$organisation->code} / {$ecart->compte->numero} : cache {$ecart->cache->formate()}, grand livre {$ecart->reel->formate()}");
                Log::critical('Écart de solde entre cache et grand livre', [
                    'organisation_id' => $organisation->id,
                    'compte_id' => $ecart->compte->id,
                    'cache' => $ecart->cache->centimes,
                    'reel' => $ecart->reel->centimes,
                ]);
            }
        });

        if ($anomalies === 0) {
            $this->info('Aucun écart : tous les soldes correspondent au grand livre.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
