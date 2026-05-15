<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('login does not terminate other sessions when single_session is disabled', function () {
    config(['fortify.single_session' => false]);

    $user = User::factory()->create();
    $originalPasswordHash = $user->password;

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(config('fortify.home'));

    expect($user->refresh()->password)->toBe($originalPasswordHash);
});

test('login terminates other sessions when single_session is enabled', function () {
    config(['fortify.single_session' => true]);

    $user = User::factory()->create();
    $originalPasswordHash = $user->password;

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(config('fortify.home'));

    // logoutOtherDevices rehashes the password in DB, invalidating all other sessions
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue()
        ->and($user->refresh()->password)->not->toBe($originalPasswordHash);
});
