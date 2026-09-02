<?php

test('the application returns a successful response', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});
