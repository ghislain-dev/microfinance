<?php

declare(strict_types=1);

namespace App\Support\Push;

enum ResultatPush
{
    case Envoye;
    case JetonInvalide; // application désinstallée ou jeton périmé : à oublier
    case Echec;         // erreur passagère : la file d'attente réessaie
}
