<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Comptabilite\PlanSyscohada;
use App\Domain\Rapports\Actions\DemanderExport;
use App\Domain\Rapports\Models\Export;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(Request $request): View
    {
        $acteur = $this->acteur($request);
        $permis = array_filter(array_keys(Export::TYPES), fn (string $type) => Gate::forUser($acteur)->allows('demander', [Export::class, $type]));
        abort_if($permis === [], 403);

        return view('rapports.exports', [
            'types' => array_intersect_key(Export::TYPES, array_flip($permis)),
            'rubriques' => array_keys(PlanSyscohada::PROPOSITION),
            'exports' => Export::query()->where('user_id', $acteur->id)->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request, DemanderExport $demander): RedirectResponse
    {
        $donnees = $request->validate([
            'type' => ['required', Rule::in(array_keys(Export::TYPES))],
            'format' => ['required', Rule::in(['pdf', 'csv'])],
            'devise' => ['nullable', 'string'],
            'du' => ['nullable', 'string'],
            'au' => ['nullable', 'string'],
            'rubrique' => ['nullable', 'string'],
            'compte_epargne_id' => ['nullable', 'integer'],
        ]);

        try {
            $demander->executer($this->acteur($request), $donnees['type'], $donnees['format'], $donnees);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['type' => $e->getMessage()]);
        }

        return to_route('exports.index')->with('statut', 'Rapport en préparation : il apparaîtra ci-dessous dans quelques instants.');
    }

    public function fichier(Request $request, Export $export): StreamedResponse
    {
        abort_unless(Gate::forUser($this->acteur($request))->allows('telecharger', $export), 404);

        return Storage::disk('local')->download((string) $export->getAttribute('chemin'), (string) $export->getAttribute('nom_fichier'));
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
