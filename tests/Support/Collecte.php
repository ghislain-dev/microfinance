<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Collecte\Actions\AffecterMembre;
use App\Domain\Collecte\Actions\DeciderAppareil;
use App\Domain\Collecte\Actions\FixerParametresCollecte;
use App\Domain\Collecte\Models\Appareil;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\FixerSeuil;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Membres\Models\Membre;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\postJson;

/**
 * Un collecteur de l'agence BUK, deux membres affectés (épargne 100 USD chacun), un troisième non affecté,
 * paramètres de collecte fixés (24 h, retrait 50 USD au plus, 1 000 USD d'espèces au plus ; seuil 4 yeux 200 USD).
 */
final readonly class Collecte
{
    /**
     * @param  list<Membre>  $membres
     * @param  list<CompteEpargne>  $comptes
     */
    public function __construct(
        public Guichet $g,
        public User $collecteur,
        public array $membres,
        public array $comptes,
        public Membre $horsTournee,
    ) {}

    public static function preparer(): self
    {
        $g = Guichet::creer();
        $collecteur = Acteurs::avecRole('collecteur', $g->organisation);
        $collecteur->agences()->attach($g->agence, ['organisation_id' => $g->organisation->id]);
        $type = Epargnant::type($g->gerant, 'EPV', Devise::USD);
        auth()->setUser($g->caissier);
        app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

        $membres = [];
        $comptes = [];
        foreach ([1, 2, 3] as $i) {
            $membre = Epargnant::membre($g);
            $compte = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, $type);
            app(Deposer::class)->executer($g->caissier, $compte, Montant::depuisSaisie('100', Devise::USD), (string) Str::uuid());
            $membres[] = $membre;
            $comptes[] = $compte;
        }
        foreach ([0, 1] as $i) {
            app(AffecterMembre::class)->executer($g->gerant, $collecteur, $membres[$i]);
        }
        app(FixerSeuil::class)->executer($g->gerant, Devise::USD, Montant::depuisSaisie('200', Devise::USD));
        app(FixerParametresCollecte::class)->executer($g->gerant, 24, [
            'USD' => ['plafond_retrait' => '50', 'plafond_especes' => '1000'],
            'CDF' => ['plafond_retrait' => '100000', 'plafond_especes' => '2000000'],
        ]);

        return new self($g, $collecteur, [$membres[0], $membres[1]], [$comptes[0], $comptes[1]], $membres[2]);
    }

    /**
     * Connexion du collecteur depuis un téléphone autorisé par le gérant ; renvoie le jeton.
     */
    public function jeton(string $identifiant = 'b1946ac9-2f1e-4b4b-9a0b-6a0e1e0c0d01'): string
    {
        Notification::fake();
        auth()->forgetGuards();
        $demande = ['email' => $this->collecteur->email, 'password' => 'password', 'appareil' => 'Itel A70', 'appareil_id' => $identifiant];
        postJson('/api/v1/auth/jetons', $demande); // enregistre le téléphone (en attente)
        $appareil = Appareil::query()->where('identifiant', $identifiant)->sole();
        app(DeciderAppareil::class)->autoriser($this->g->gerant, $appareil);
        auth()->forgetGuards();
        postJson('/api/v1/auth/jetons', $demande)->assertAccepted();
        $jeton = (string) postJson('/api/v1/auth/jetons', [...$demande, 'code' => Connexion::dernierCode($this->collecteur)])
            ->assertCreated()->json('jeton');
        auth()->forgetGuards();

        return $jeton;
    }
}
