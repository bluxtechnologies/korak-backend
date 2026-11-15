<?php

it('returns json for unknown api v1 routes even without an accept header', function (): void {
    $response = $this->get('/api/v1/non-existent-endpoint');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'message' => 'Resource not found.',
            'code' => 'not_found',
        ]);
});

it('keeps the built-in health endpoint reachable', function (): void {
    $this->get('/up')->assertOk();
});
