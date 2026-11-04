<?php

test('tamu yang membuka beranda diarahkan ke halaman login', function () {
    $this->get('/')->assertRedirect('/login');
});
