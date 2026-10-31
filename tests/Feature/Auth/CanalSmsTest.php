<?php

declare(strict_types=1);

use App\Domain\Organisation\Notifications\CodeOtpNotification;
use App\Support\Sms\EnvoyeurSms;
use App\Support\Sms\JournalSms;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Acteurs;

use function Pest\Laravel\post;

it('utilise l’e-mail par défaut', function () {
    expect(config('otp.canal'))->toBe('mail');
});

it('envoie le code par SMS quand le canal est activé', function () {
    config(['otp.canal' => 'sms']);
    Notification::fake();
    $caissier = Acteurs::avecRole('caissier');

    post('/connexion', ['email' => $caissier->email, 'password' => 'password']);

    Notification::assertSentTo($caissier, CodeOtpNotification::class, fn ($n, array $canaux) => $canaux === ['sms']);
});

it('passe par l’envoyeur SMS (pilote « journal » tant qu’aucun fournisseur n’est choisi)', function () {
    expect(app(EnvoyeurSms::class))->toBeInstanceOf(JournalSms::class);

    $faux = new class implements EnvoyeurSms
    {
        /** @var list<array{string, string}> */
        public array $envoyes = [];

        public function envoyer(string $telephone, string $message): void
        {
            $this->envoyes[] = [$telephone, $message];
        }
    };
    app()->instance(EnvoyeurSms::class, $faux);
    config(['otp.canal' => 'sms']);
    $caissier = Acteurs::avecRole('caissier');

    post('/connexion', ['email' => $caissier->email, 'password' => 'password'])->assertRedirect('/connexion/code');

    expect($faux->envoyes)->toHaveCount(1)
        ->and($faux->envoyes[0][0])->toBe($caissier->telephone)
        ->and($faux->envoyes[0][1])->toMatch('/\d{6}/');
});
