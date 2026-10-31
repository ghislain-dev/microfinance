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
