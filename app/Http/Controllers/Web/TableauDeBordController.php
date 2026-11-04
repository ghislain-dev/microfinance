<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Rapports\Services\TableauDeBord;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, TableauDeBord $tableau): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return view('tableau-de-bord', ['blocs' => $tableau->pour($utilisateur)]);
    }
}
