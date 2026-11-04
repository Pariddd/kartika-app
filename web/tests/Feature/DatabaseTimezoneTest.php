<?php

use App\Models\CaseFile;
use Illuminate\Support\Facades\DB;

/*
| Regresi: Laravel menulis waktu UTC tanpa zona. Bila sesi PostgreSQL memakai
| zona lain (misalnya WIB bawaan Windows), waktu tersimpan bergeser 7 jam.
*/

test('sesi PostgreSQL memakai zona UTC', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Khusus PostgreSQL.');
    }

    expect(DB::selectOne('show timezone')->TimeZone)->toBe('UTC');
});

test('waktu yang ditulis Laravel tersimpan sebagai instan yang benar', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Khusus PostgreSQL.');
    }

    $case = CaseFile::factory()->create();
    $storedEpoch = (int) DB::selectOne('select extract(epoch from created_at)::bigint as epoch from cases where id = ?', [$case->id])->epoch;

    expect(abs($storedEpoch - time()))->toBeLessThan(60);
});
