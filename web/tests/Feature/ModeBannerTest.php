<?php

use App\Models\User;

test('mode lokal tidak menampilkan banner peringatan', function () {
    config(['radar.mode' => 'local', 'radar.presentation_mask' => false]);

    $this->get('/')->assertDontSee('Mode lokal.');
    $this->actingAs(User::factory()->create())->get(route('cases.index'))->assertDontSee('Mode lokal.');
});

test('mode publik tetap memberi tahu pengunjung bahwa datanya sintetis', function () {
    config(['radar.mode' => 'public']);

    $this->actingAs(User::factory()->create())->get(route('cases.index'))->assertSee('Mode publik.');
});

test('halaman masuk punya tautan kembali ke halaman depan', function () {
    $this->get(route('login'))->assertOk()->assertSee('Beranda')->assertSee('href="'.route('home').'"', false);
});
