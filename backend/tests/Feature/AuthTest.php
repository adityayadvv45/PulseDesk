<?php

use function Pest\Laravel\postJson;

it('registers a new organization with an admin user', function () {
    postJson('/api/v1/auth/register', [
        'name' => 'New Admin',
        'email' => 'new@startup.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_name' => 'Startup Inc',
    ])->assertCreated()
      ->assertJsonPath('user.role', 'admin')
      ->assertJsonStructure(['token', 'user' => ['id', 'organization' => ['id', 'slug']]]);
});

it('rejects login with bad credentials', function () {
    postJson('/api/v1/auth/login', [
        'email' => 'nobody@nowhere.test',
        'password' => 'wrong',
    ])->assertStatus(422);
});

it('requires authentication for tickets', function () {
    postJson('/api/v1/tickets', [])->assertUnauthorized();
});
