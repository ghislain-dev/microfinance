<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Services;

use App\Domain\Organisation\Models\Organisation;
use Barryvdh\DomPDF\Facade\Pdf;

final class EcrivainPdf
{
    public function ecrire(Tableau $tableau, Organisation $organisation): string
    {
        return Pdf::loadView('rapports.pdf', ['tableau' => $tableau, 'organisation' => $organisation])
            ->setPaper('a4', count($tableau->colonnes) > 5 ? 'landscape' : 'portrait')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])
            ->output();
    }
}
