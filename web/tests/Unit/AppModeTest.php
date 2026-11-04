<?php

use App\Support\AppMode;

test('APP_MODE dinormalisasi dan nilai tidak dikenal dianggap publik', function (?string $input, string $expected) {
    expect(AppMode::normalize($input))->toBe($expected);
})->with([
    'kosong (null)' => [null, 'public'],
    'string kosong' => ['', 'public'],
    'local' => ['local', 'local'],
    'huruf besar dan spasi' => [' LOCAL ', 'local'],
    'public' => ['public', 'public'],
    'salah ketik' => ['lokal', 'public'],
]);
