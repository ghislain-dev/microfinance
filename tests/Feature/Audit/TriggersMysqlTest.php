<?php

declare(strict_types=1);

use App\Support\Audit\JournalAudit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('bloque UPDATE et DELETE sur audit_logs dans MySQL', function () {
    app(JournalAudit::class)->enregistrer('essai');

    expect(fn () => DB::statement("update audit_logs set action = 'falsifie'"))
        ->toThrow(QueryException::class, 'audit_logs : insertion seule')
        ->and(fn () => DB::statement('delete from audit_logs'))
        ->toThrow(QueryException::class, 'audit_logs : insertion seule')
        ->and(DB::table('audit_logs')->where('action', 'essai')->count())->toBe(1);
})->group('mysql');
