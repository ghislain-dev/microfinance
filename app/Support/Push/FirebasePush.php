<?php

declare(strict_types=1);

namespace App\Support\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging, API HTTP v1. Jeton d'accès OAuth 2 obtenu avec le compte de service
 * (JWT signé RS256 par openssl, sans dépendance supplémentaire) et gardé en cache 50 minutes.
 */
final class FirebasePush implements EnvoyeurPush
{
    public function __construct(private readonly string $projet, private readonly string $fichierCompteService) {}

    public function envoyer(string $jeton, string $titre, string $corps, array $donnees = []): ResultatPush
    {
        $reponse = Http::withToken($this->jetonAcces())->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projet}/messages:send", ['message' => [
                'token' => $jeton,
                'notification' => ['title' => $titre, 'body' => $corps],
                'data' => (object) $donnees,
                'android' => ['priority' => 'high'],
            ]]);

        if ($reponse->successful()) {
            return ResultatPush::Envoye;
        }
        $code = (string) $reponse->json('error.details.0.errorCode', $reponse->json('error.status', ''));

        return in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true) || $reponse->status() === 404
            ? ResultatPush::JetonInvalide : ResultatPush::Echec;
    }

    private function jetonAcces(): string
    {
        return Cache::remember('fcm:jeton-acces', now()->addMinutes(50), function () {
            $compte = json_decode((string) file_get_contents($this->fichierCompteService), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($compte) || ! isset($compte['client_email'], $compte['private_key'])) {
                throw new RuntimeException('Compte de service Firebase invalide.');
            }
            $maintenant = time();
            $base64 = fn (string $texte) => rtrim(strtr(base64_encode($texte), '+/', '-_'), '=');
            $entete = $base64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $charge = $base64((string) json_encode([
                'iss' => $compte['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $maintenant, 'exp' => $maintenant + 3600,
            ]));
            if (! openssl_sign("{$entete}.{$charge}", $signature, (string) $compte['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Signature du jeton Firebase impossible.');
            }

            return (string) Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$entete}.{$charge}.{$base64($signature)}",
            ])->throw()->json('access_token');
        });
    }
}
