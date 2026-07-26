<?php

use App\Models\User;
use Database\Seeders\AdministratorSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('administrator.name', 'Interview Administrator');
    config()->set('administrator.email', 'administrator@example.test');
    config()->set('administrator.password', 'a-local-test-password');
});

it('protects operations and allows only the configured administrator', function () {
    $this->get(route('operations.home'))
        ->assertRedirect(route('login'));

    $this->seed(AdministratorSeeder::class);
    $administrator = User::query()
        ->where('email', 'administrator@example.test')
        ->sole();

    expect($administrator->name)->toBe('Interview Administrator')
        ->and(Hash::check('a-local-test-password', $administrator->password))
        ->toBeTrue();

    $this->post(route('login.store'), [
        'email' => 'administrator@example.test',
        'password' => 'a-local-test-password',
    ])->assertRedirect(route('operations.home'));

    $this->assertAuthenticatedAs($administrator);
    $this->get(route('operations.home'))
        ->assertSuccessful()
        ->assertSee('Interview Administrator');

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();

    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->get(route('operations.home'))
        ->assertForbidden();
});

it('throttles repeated failed login attempts by email and address', function () {
    foreach (range(1, 5) as $_attempt) {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'attacker@example.test',
                'password' => 'incorrect-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'attacker@example.test',
            'password' => 'incorrect-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))
        ->toContain('Too many login attempts.');

    $this->assertGuest();
});
