<?php

use App\Support\YouTubeVideoId;

test('ID video diekstrak dari berbagai bentuk link', function (string $input, ?string $expected) {
    expect(YouTubeVideoId::extract($input))->toBe($expected);
})->with([
    'ID mentah' => ['dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'watch dengan parameter lain' => ['https://www.youtube.com/watch?list=PL1&v=dQw4w9WgXcQ&t=42s', 'dQw4w9WgXcQ'],
    'tanpa skema' => ['youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', 'dQw4w9WgXcQ'],
    'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'spasi di tepi' => ['  https://youtu.be/dQw4w9WgXcQ  ', 'dQw4w9WgXcQ'],
    'domain palsu' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ', null],
    'domain lain' => ['https://evil.example/watch?v=dQw4w9WgXcQ', null],
    'skema javascript' => ['javascript://youtube.com/watch?v=dQw4w9WgXcQ', null],
    'ID terlalu pendek' => ['https://youtu.be/abc', null],
    'ID dengan karakter asing' => ['dQw4w9WgXc<', null],
    'kanal, bukan video' => ['https://www.youtube.com/@kanalcontoh', null],
    'v berupa array' => ['https://www.youtube.com/watch?v[]=dQw4w9WgXcQ', null],
]);
