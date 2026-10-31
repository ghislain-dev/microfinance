---
name: finance-cooperative
description: Règles métier et modèles de code sûrs pour l'application de gestion financière (coopérative, tontine, épargne AVEC, microfinance) en Laravel/PHP + MySQL/SQLite. Utiliser ce skill dès qu'une tâche touche aux montants, comptes, opérations, écritures, soldes, cotisations, séances, tours de tontine, partage de fin de cycle, parts sociales, épargne, crédits, échéanciers, remboursements, pénalités, caisse, billetage, taux de change, synchronisation mobile hors-ligne, audit, rôles ou sécurité, même si l'utilisateur ne le demande pas explicitement.
---

# Skill : gestion financière coopérative / tontine / microfinance

Lire aussi `CLAUDE.md` (règles absolues, plan) et `docs/cahier-des-charges.md` (détail fonctionnel).
Pour la grille de sécurité complète : `references/securite.md`.

## 1. Avant de coder (toujours)

1. Identifier le module : Finance, Membres, Tontines, Épargne, Crédit, Caisse, Comptabilité, Sync.
2. Si la tâche **déplace de l'argent** → elle passe par le `MoteurFinancier` (§3). Aucune exception.
3. Écrire d'abord le test qui prouve la règle (montant, équilibre, doublon, droit d'accès).
4. Coder, puis : `./vendor/bin/pint && ./vendor/bin/phpstan analyse && php artisan test`.
5. Résumer : fichiers modifiés, migrations, tests ajoutés, points ouverts.

## 2. Montants

- Stockage : colonne `BIGINT` signée en **unité mineure** (1/100) pour toutes les devises, + `devise CHAR(3)`.
- Calcul : `brick/money` (`composer require brick/money`) ou le value object ci-dessous. Jamais de `float`.

```php
final class Montant
{
    public function __construct(public readonly int $centimes, public readonly string $devise)
    {
        if (!in_array($devise, ['USD', 'CDF'], true)) throw new InvalidArgumentException('Devise');
    }
    public static function depuisSaisie(string $saisie, string $devise): self
    {   // "12.50" ou "12,50" → 1250 ; refuse plus de 2 décimales
        $s = str_replace([' ', ','], ['', '.'], trim($saisie));
        if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $s)) throw new InvalidArgumentException('Montant invalide');
        [$u, $d] = array_pad(explode('.', $s), 2, '0');
        return new self((int) $u * 100 + (int) str_pad($d, 2, '0'), $devise);
    }
    public function plus(self $m): self { $this->memeDevise($m); return new self($this->centimes + $m->centimes, $this->devise); }
    public function moins(self $m): self { $this->memeDevise($m); return new self($this->centimes - $m->centimes, $this->devise); }
    public function estPositif(): bool { return $this->centimes > 0; }
    private function memeDevise(self $m): void { if ($m->devise !== $this->devise) throw new LogicException('Devises différentes'); }
    public function formate(): string { return number_format($this->centimes / 100, 2, ',', ' ') . ' ' . $this->devise; } // affichage seulement
}
```

Cast Eloquent : un cast `MontantCast` qui lit `montant` + `devise` et renvoie un `Montant`.
Validation dans les Form Requests : `'montant' => ['required', 'regex:/^\d{1,13}([.,]\d{1,2})?$/']`.

## 3. Moteur financier (le seul à écrire l'argent)

Tables : `operations` (en-tête, `idempotency_key` UNIQUE, `type`, `organisation_id`, `auteur_id`,
`valide_par_id`, `extourne_de_id`, `date_valeur`, `motif`) et `ecritures` (`operation_id`, `compte_id`,
`debit`, `credit`, `devise`) — **insertion seule**.

```php
final class MoteurFinancier
{
    /** @param LigneEcriture[] $lignes */
    public function executer(string $cle, string $type, array $lignes, array $meta = []): Operation
    {
        if ($deja = Operation::where('idempotency_key', $cle)->first()) {
            return $deja;                                   // 2e envoi : même résultat, rien de créé
        }
        $this->verifierEquilibre($lignes);                  // Σ débits = Σ crédits, même devise, > 0

        try {
            return DB::transaction(function () use ($cle, $type, $lignes, $meta) {
                $ids = collect($lignes)->pluck('compteId')->unique()->sort()->values();
                $comptes = Compte::whereIn('id', $ids)->orderBy('id')   // ordre fixe = pas d'interblocage
                    ->lockForUpdate()->get()->keyBy('id');

                $this->verifierPeriodeOuverte($meta['date_valeur'] ?? today());
                $this->verifierComptes($comptes, $lignes);   // actifs, même organisation, pas de découvert interdit

                $op = Operation::create([
                    'idempotency_key' => $cle, 'type' => $type,
                    'auteur_id' => auth()->id(), 'date_valeur' => $meta['date_valeur'] ?? today(),
                    'motif' => $meta['motif'] ?? null, 'extourne_de_id' => $meta['extourne_de_id'] ?? null,
                ]);
                foreach ($lignes as $l) {
                    $op->ecritures()->create(['compte_id' => $l->compteId, 'debit' => $l->debit,
                        'credit' => $l->credit, 'devise' => $l->devise]);
                    $comptes[$l->compteId]->appliquerMouvement($l);     // met à jour solde_cache
                }
                OperationEnregistree::dispatch($op);   // l'événement implémente ShouldDispatchAfterCommit
                return $op;
            }, attempts: 3);                              // rejoue si interblocage MySQL
        } catch (UniqueConstraintViolationException) {
            return Operation::where('idempotency_key', $cle)->firstOrFail();   // course gagnée par l'autre requête
        }
    }

    public function extourner(Operation $origine, string $motif, User $validateur): Operation
    {
        abort_if($validateur->id === $origine->auteur_id, 403, 'Validation par une autre personne obligatoire');
        abort_if($origine->extournes()->exists(), 409, 'Déjà extournée');
        $lignes = $origine->ecritures->map(fn ($e) => new LigneEcriture($e->compte_id, $e->credit, $e->debit, $e->devise));
        return $this->executer("extourne:{$origine->id}", 'extourne', $lignes->all(),
            ['motif' => $motif, 'extourne_de_id' => $origine->id]);
    }
}
```

Protection en base (migration MySQL, en plus du code) :

```php
DB::unprepared("CREATE TRIGGER ecritures_no_update BEFORE UPDATE ON ecritures FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ecritures: insertion seule'");
DB::unprepared("CREATE TRIGGER ecritures_no_delete BEFORE DELETE ON ecritures FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ecritures: insertion seule'");
// idem pour operations (UPDATE autorisé seulement sur valide_par_id si besoin) et audit_logs
```
Entourer ces triggers de `if (DB::getDriverName() === 'mysql')` pour que les tests SQLite passent.

## 4. Schémas d'opérations courantes

| Opération | Débit | Crédit |
|---|---|---|
| Dépôt épargne en espèces | Caisse du caissier | Compte d'épargne du membre |
| Retrait | Compte d'épargne du membre | Caisse |
| Cotisation tontine | Caisse (ou compte du collecteur) | Compte du cycle de tontine |
| Versement du tour au bénéficiaire | Compte du cycle | Caisse (ou compte épargne du bénéficiaire) |
| Achat de parts (AVEC) | Caisse | Parts sociales du membre |
| Décaissement de prêt | Prêt du membre (capital) | Caisse |
| Remboursement | Caisse | Pénalités → intérêts → capital, dans cet ordre |
| Pénalité constatée | Compte du membre (à recevoir) | Produit pénalités |
| Cotisation fonds social | Caisse | Fonds social |

Chaque ligne métier (`cotisations`, `remboursements`…) garde un `operation_id` vers l'opération créée.

## 5. Tontines

- **Une cotisation par membre, par séance et par type** : `UNIQUE(seance_id, membre_id, type)`.
- Saisie d'une séance = une seule action qui enregistre présences + cotisations + pénalités, chaque
  montant via le moteur, la clé d'idempotence = `seance:{id}:membre:{id}:{type}`.
- Tour rotatif : `tours (cycle_id, rang, membre_id, seance_id, montant_verse)` avec
  `UNIQUE(cycle_id, rang)` et `UNIQUE(cycle_id, membre_id)` (un membre reçoit une fois par cycle, sauf
  paramètre « parts multiples »). Tirage : `random_int()`, résultat enregistré + audit, jamais recalculé.
- Partage de fin de cycle (AVEC) : part de chaque membre = actif net du cycle × (ses parts / total parts).
  Calculer en entiers, distribuer le **reste d'arrondi** aux plus grosses parts (méthode du plus fort reste),
  vérifier que Σ des parts versées = actif net exactement, puis faire valider par le comité avant écriture.

## 6. Crédit

- Linéaire : capital constant ; intérêt = capital restant × taux de la période.
- Dégressif (annuité constante) : calculer avec `brick/math` (`BigDecimal`, échelle 10), arrondir chaque
  intérêt en `RoundingMode::HALF_EVEN`, et mettre **l'écart d'arrondi du capital sur la dernière échéance**.
- Test obligatoire : Σ capital des échéances = montant décaissé, au centime près.
- Approbation : seuils par organisation (`regles_approbation`) ; l'agent qui saisit ne peut pas approuver.
- Retard : commande planifiée quotidienne qui marque les échéances en retard et constate les pénalités
  (idempotente : clé `penalite:{echeance_id}:{date}`).
- PAR 30 / PAR 90 = encours des prêts ayant une échéance impayée depuis plus de 30 / 90 jours ÷ encours total.

## 7. Caisse

- Une seule session ouverte par caisse : colonne générée `ouverte_flag` (1 si ouverte, NULL sinon) +
  `UNIQUE(caisse_id, ouverte_flag)` — fonctionne sur MySQL et SQLite.
- Toute opération espèces exige une session ouverte du caissier connecté.
- Clôture : billetage saisi (nombre × coupure, en entiers) comparé au solde théorique ; écart ≠ 0 →
  statut `ecart_a_justifier`, opération d'écart validée par le gérant.

## 8. Synchronisation mobile (hors-ligne, sans doublon)

- Le mobile génère un **UUID v4 par opération** au moment de la saisie (clé d'idempotence) et un UUID par lot.
- `POST /api/v1/sync/lots` : `{device_id, lot_uuid, operations: [{cle, type, membre_id, montant, devise, saisi_le}]}`.
- Le serveur traite chaque opération via le moteur et répond **par opération** :
  `{cle, statut: "cree" | "deja_traite" | "rejete", raison?, operation_id?}`. Le mobile marque comme
  synchronisées les `cree` et `deja_traite`, affiche les `rejete`.
- Le serveur est la seule source de vérité : le mobile ne calcule jamais un solde officiel ; il le reçoit.
- `saisi_le` (heure du téléphone) est conservé pour info ; `date_valeur` est fixée par le serveur.
- Un collecteur ne peut synchroniser que pour les membres de ses groupes (Policy).

## 9. Multi-organisation et accès

```php
trait AppartientAOrganisation
{
    protected static function bootAppartientAOrganisation(): void
    {
        static::addGlobalScope('organisation', function (Builder $q) {
            if ($org = auth()->user()?->organisation_id) $q->where($q->getModel()->getTable().'.organisation_id', $org);
        });
        static::creating(fn ($m) => $m->organisation_id ??= auth()->user()?->organisation_id);
    }
}
```
- Tester systématiquement : un utilisateur de l'organisation A reçoit **404** sur une ressource de B.
- Commandes planifiées (pas d'utilisateur connecté) : boucler explicitement par organisation.

## 10. Tests à écrire pour chaque fonctionnalité d'argent

```php
it('ne crée qu’une opération pour 10 envois identiques', function () {
    $cle = (string) Str::uuid();
    foreach (range(1, 10) as $_) { $this->postJson('/api/v1/depots', [...$data, 'cle' => $cle]); }
    expect(Operation::where('idempotency_key', $cle)->count())->toBe(1);
});

it('garde la balance équilibrée', function () {
    // ... jeu d'opérations
    expect(Ecriture::sum('debit'))->toBe(Ecriture::sum('credit'));
});

it('refuse l’accès aux données d’une autre organisation', function () { /* 404 attendu */ });

#[Group('mysql')] // SQLite ignore lockForUpdate
it('ne rend jamais un compte négatif avec 50 retraits simultanés', function () { /* processus parallèles */ });
```

## 11. Erreurs fréquentes à refuser en revue

- `$compte->solde -= $montant; $compte->save();` hors moteur et hors verrou.
- `->delete()` ou `SoftDeletes` sur une table financière.
- `round($x, 2)` ou `(float)` sur un montant.
- `Operation::create()` appelé depuis un contrôleur ou un autre module.
- `organisation_id` lu depuis `$request`.
- Un `whereRaw("... $variable ...")`.
- Un envoi de SMS ou un PDF généré dans la requête HTTP au lieu d'une file d'attente.
