<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Push\EnvoyeurPush;
use App\Support\Push\ResultatPush;

/**
 * Envoyeur push des tests : garde les messages, déclare « invalides » les jetons listés.
 */
final class PushFactice implements EnvoyeurPush
{
    /** @var list<array{jeton: string, titre: string, corps: string, donnees: array<string, string>}> */
    public array $envois = [];

    /** @var list<string> */
    public array $invalides = [];

    public function envoyer(string $jeton, string $titre, string $corps, array $donnees = []): ResultatPush
    {
        if (in_array($jeton, $this->invalides, true)) {
            return ResultatPush::JetonInvalide;
        }
        $this->envois[] = ['jeton' => $jeton, 'titre' => $titre, 'corps' => $corps, 'donnees' => $donnees];

        return ResultatPush::Envoye;
    }

    /**
     * @return list<string>
     */
    public function titres(): array
    {
        return array_map(fn (array $e) => $e['titre'], $this->envois);
    }

    public function vider(): void
    {
        $this->envois = [];
    }

    public static function installer(): self
    {
        $faux = new self;
        app()->instance(EnvoyeurPush::class, $faux);

        return $faux;
    }
}
