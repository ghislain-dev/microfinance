<?php

declare(strict_types=1);

namespace App\Http\Navigation;

final readonly class EntreeMenu
{
    /**
     * @param  string|null  $route  null tant que la page n'existe pas (entrée affichée « bientôt »)
     */
    public function __construct(
        public string $libelle,
        public ?string $route,
    ) {}

    public function estActive(): bool
    {
        return $this->route !== null && request()->routeIs(explode('.', $this->route)[0].'*');
    }
}
