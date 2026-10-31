<?php

declare(strict_types=1);

// CLAUDE.md §5 et references/securite.md : fonctions interdites (liste du preset « security »
// de Pest, écrite ici pour que PHPStan puisse analyser le test).
arch('aucune fonction dangereuse', function () {
    expect([
        'eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen',
        'create_function', 'unserialize', 'extract', 'mb_parse_str', 'dl', 'assert',
        'md5', 'sha1', 'uniqid', 'rand', 'mt_rand', 'tempnam', 'str_shuffle', 'shuffle', 'array_rand',
    ])->not->toBeUsed();
});

arch('aucun reste de débogage', function () {
    expect(['dd', 'dump', 'ddd', 'var_dump', 'print_r', 'ray', 'die', 'phpinfo'])->not->toBeUsed();
});

arch('types stricts partout dans app/', function () {
    expect('App')->toUseStrictTypes();
});

it('n’utilise jamais $guarded = [] (CLAUDE.md §5)', function () {
    $fichiers = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app')),
        '/\.php$/'
    );

    $fautifs = [];
    foreach ($fichiers as $fichier) {
        if (preg_match('/\$guarded\s*=\s*\[\s*\]/', (string) file_get_contents($fichier->getPathname()))) {
            $fautifs[] = $fichier->getPathname();
        }
    }

    expect($fautifs)->toBe([]);
});
