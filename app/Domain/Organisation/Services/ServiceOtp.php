<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Services;

use App\Domain\Organisation\Models\CodeOtp;
use App\Domain\Organisation\Notifications\CodeOtpNotification;
use App\Models\User;
use App\Support\Audit\JournalAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Codes à usage unique : émission, envoi (config otp.canal) et vérification.
 */
final class ServiceOtp
{
    public function __construct(private readonly JournalAudit $journal) {}

    /**
     * Émet et envoie un nouveau code ; les codes précédents deviennent invalides.
     * Renvoie false si un code a été émis il y a moins de otp.delai_renvoi_secondes (rien n'est envoyé).
     */
    public function emettre(User $utilisateur): bool
    {
        $recent = $this->codesUtilisables($utilisateur)
            ->where('created_at', '>', now()->subSeconds((int) config('otp.delai_renvoi_secondes')))
            ->exists();
        if ($recent) {
            return false;
        }

        $longueur = (int) config('otp.longueur');
        $code = str_pad((string) random_int(0, 10 ** $longueur - 1), $longueur, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($utilisateur, $code) {
            $this->codesUtilisables($utilisateur)->update(['expire_le' => now()]);

            $ligne = new CodeOtp([
                'code_hache' => $this->hacher($code),
                'canal' => (string) config('otp.canal'),
                'expire_le' => now()->addMinutes((int) config('otp.validite_minutes')),
            ]);
            $ligne->organisation_id = $utilisateur->organisation_id;
            $ligne->user_id = $utilisateur->id;
            $ligne->save();
        });

        $utilisateur->notify(new CodeOtpNotification($code));

        return true;
    }

    public function verifier(User $utilisateur, string $saisie): ResultatOtp
    {
        return DB::transaction(function () use ($utilisateur, $saisie) {
            $ligne = $this->codesUtilisables($utilisateur)->latest('id')->lockForUpdate()->first();
            if ($ligne === null) {
                return ResultatOtp::AucunCode;
            }

            if (! hash_equals($ligne->code_hache, $this->hacher($saisie))) {
                $ligne->increment('tentatives');
                $this->journal->enregistrer('otp.echec', $utilisateur, apres: ['tentatives' => $ligne->tentatives], acteur: $utilisateur);

                return ResultatOtp::Incorrect;
            }

            $ligne->utilise_le = now();
            $ligne->save();

            return ResultatOtp::Valide;
        });
    }

    /**
     * @return Builder<CodeOtp>
     */
    private function codesUtilisables(User $utilisateur): Builder
    {
        return CodeOtp::query()
            ->where('user_id', $utilisateur->id)
            ->whereNull('utilise_le')
            ->where('expire_le', '>', now())
            ->where('tentatives', '<', (int) config('otp.essais_max'));
    }

    private function hacher(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
