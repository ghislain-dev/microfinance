<?php

declare(strict_types=1);

// CLAUDE.md §4.1 : jamais de float, double ou round() sur un montant.
arch('aucune fonction d’arrondi ou de conversion flottante dans le code métier', function () {
    expect(['round', 'floor', 'ceil', 'floatval', 'fmod', 'number_format'])
        ->not->toBeUsedIn(['App\Domain', 'App\Support']);
});

it('n’utilise aucun type ou cast float dans le code métier', function () {
    $fautifs = [];
    foreach (['Domain', 'Support'] as $dossier) {
        $fichiers = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/'.$dossier)),
            '/\.php$/'
        );
        foreach ($fichiers as $fichier) {
            if (preg_match('/\(float\)|\(double\)|\bfloat\s+\$|:\s*\??float\b|\'(float|double|decimal)(:\d+)?\'/', (string) file_get_contents($fichier->getPathname()))) {
                $fautifs[] = $fichier->getPathname();
            }
        }
    }

    expect($fautifs)->toBe([]);
});

// CLAUDE.md §3 : seul Domain/Finance écrit dans operations et ecritures.
arch('Operation et Ecriture ne sont utilisés que dans Domain/Finance', function () {
    expect(['App\Domain\Finance\Models\Operation', 'App\Domain\Finance\Models\Ecriture'])
        ->toOnlyBeUsedIn('App\Domain\Finance');
});

it('n’écrit jamais dans operations ou ecritures par DB::table hors de Domain/Finance', function () {
    $fautifs = [];
    $fichiers = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app')), '/\.php$/');
    foreach ($fichiers as $fichier) {
        if (str_contains($fichier->getPathname(), '/app/Domain/Finance/')) {
            continue;
        }
        // Toute écriture visant ces tables : DB::table('operations'…) ou SQL brut (INSERT / UPDATE / DELETE).
        // (Le mot seul est permis : le champ « operations » d'un lot de synchronisation n'écrit rien.)
        $code = (string) file_get_contents($fichier->getPathname());
        if (preg_match('/DB::table\(\s*[\'"](operations|ecritures)[\'"]|(insert\s+into|update|delete\s+from)\s+`?(operations|ecritures)\b/i', $code)) {
            $fautifs[] = $fichier->getPathname();
        }
    }

    expect($fautifs)->toBe([]);
});
