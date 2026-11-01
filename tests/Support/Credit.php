<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Credit\Actions\AnalyserPret;
use App\Domain\Credit\Actions\DeciderPret;
use App\Domain\Credit\Actions\DemanderPret;
use App\Domain\Credit\Actions\EnregistrerProduitCredit;
use App\Domain\Credit\MethodeCalcul;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Membres\Models\Membre;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Str;

/**
 * Un membre épargnant (caisse ouverte par le caissier), un produit de crédit USD et les acteurs du circuit.
 */
final readonly class Credit
{
    public function __construct(
        public Guichet $g,
        public Membre $membre,
        public CompteEpargne $compte,
        public ProduitCredit $produit,
        public User $agent,
        public User $comite,
        public User $comptable,
    ) {}

    /**
     * Produit : 3 % par mois, mensuel, 24 échéances au plus, 100 à 10 000 USD, plafond 3 × épargne,
     * garantie 20 % d'épargne bloquée, pénalité 0,5 % par jour après 3 jours de grâce.
     *
     * @param  array<string, mixed>  $produit
     */
    public static function preparer(array $produit = [], string $epargne = '500'): self
    {
        $g = Guichet::creer();
        $type = Epargnant::type($g->gerant, 'EPV', Devise::USD);
        $membre = Epargnant::membre($g);
        $compte = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, $type);
        auth()->setUser($g->caissier);
        app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());
        app(Deposer::class)->executer($g->caissier, $compte, self::usd($epargne), (string) Str::uuid());

        return new self($g, $membre, $compte, self::produit($g->gerant, $produit),
            Acteurs::avecRole('agent_credit', $g->organisation),
            Acteurs::avecRole('comite', $g->organisation),
            Acteurs::avecRole('comptable', $g->organisation));
    }

    /**
     * @param  array<string, mixed>  $parametres
     */
    public static function produit(User $acteur, array $parametres = []): ProduitCredit
    {
        return app(EnregistrerProduitCredit::class)->creer($acteur, [
            'code' => 'PME', 'libelle' => 'Crédit PME', 'devise' => 'USD', 'taux_mensuel' => '3',
            'periodicite' => 'mensuelle', 'duree_max' => 24, 'montant_minimum' => '100', 'montant_maximum' => '10000',
            'anciennete_mois' => 0, 'multiple_epargne' => 3, 'garantie_epargne' => '20', 'penalite_jour' => '0,5',
            'delai_grace_jours' => 3, ...$parametres,
        ]);
    }

    /**
     * Autre membre épargnant (500 USD) de la même organisation, avec le même produit et les mêmes acteurs.
     */
    public static function autreEmprunteur(self $c): self
    {
        $membre = Epargnant::membre($c->g);
        $compte = app(OuvrirCompteEpargne::class)->executer($c->g->gerant, $membre, $c->compte->type);
        auth()->setUser($c->g->caissier);
        app(Deposer::class)->executer($c->g->caissier, $compte, self::usd('500'), (string) Str::uuid());

        return new self($c->g, $membre, $compte, $c->produit, $c->agent, $c->comite, $c->comptable);
    }

    public static function usd(string $montant): Montant
    {
        return Montant::depuisSaisie($montant, Devise::USD);
    }

    /**
     * @param  list<array<string, mixed>>  $garanties
     */
    public function demande(string $montant = '1000', int $nombre = 10, MethodeCalcul $methode = MethodeCalcul::Lineaire, array $garanties = [], ?User $auteur = null): Pret
    {
        return app(DemanderPret::class)->executer($auteur ?? $this->agent, $this->membre, $this->produit, $this->compte,
            self::usd($montant), $nombre, $methode, 'Achat de marchandises', $garanties);
    }

    /**
     * Demande analysée et approuvée par le comité (sous le seuil du gérant).
     */
    public function approuve(string $montant = '1000', int $nombre = 10, MethodeCalcul $methode = MethodeCalcul::Lineaire): Pret
    {
        $pret = $this->demande($montant, $nombre, $methode);
        app(AnalyserPret::class)->executer($this->agent, $pret, 'Commerce rentable, épargne régulière.');

        return app(DeciderPret::class)->approuver($this->comite, $pret, 'Accordé');
    }
}
