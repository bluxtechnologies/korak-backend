<?php

it('returns a successful response', function (): void {
    $response = $this->getJson('/');

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'message' => 'Korak API is running.',
        ]);
});
