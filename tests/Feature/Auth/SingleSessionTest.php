<?php

use App\Listeners\EnforceSingleSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

function fakeSessionTenant(array $settings = []): object
{
    return (object) [
        'id' => 'tenant-session-test',
        'settings' => $settings,
    ];
}

function fireLoginEvent(User $user): void
{
    event(new Login('web', $user, false));
}

function sessionsTable()
{
    return DB::connection(config('raptor.database.landlord_connection_name', 'landlord'))
        ->table('sessions');
}

it('does not delete sessions when single_session is disabled', function () {
    app()->instance('current.tenant', fakeSessionTenant(['features' => ['single_session' => false]]));

    $user = User::factory()->create();
    $currentSessionId = session()->getId();

    sessionsTable()->insert([
        'id' => 'old-session-abc',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    fireLoginEvent($user);

    expect(sessionsTable()->where('id', 'old-session-abc')->exists())->toBeTrue();
});

it('does not delete sessions when single_session is not configured (default off)', function () {
    app()->instance('current.tenant', fakeSessionTenant());

    $user = User::factory()->create();

    sessionsTable()->insert([
        'id' => 'old-session-xyz',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    fireLoginEvent($user);

    expect(sessionsTable()->where('id', 'old-session-xyz')->exists())->toBeTrue();
});

it('deletes other sessions when single_session is enabled', function () {
    app()->instance('current.tenant', fakeSessionTenant(['features' => ['single_session' => true]]));

    $user = User::factory()->create();
    $currentSessionId = session()->getId();

    sessionsTable()->insert([
        'id' => 'old-session-to-delete',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    sessionsTable()->insert([
        'id' => $currentSessionId,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    fireLoginEvent($user);

    expect(sessionsTable()->where('id', 'old-session-to-delete')->exists())->toBeFalse();
    expect(sessionsTable()->where('id', $currentSessionId)->exists())->toBeTrue();
});

it('does not delete the current session when single_session is enabled', function () {
    app()->instance('current.tenant', fakeSessionTenant(['features' => ['single_session' => true]]));

    $user = User::factory()->create();
    $currentSessionId = session()->getId();

    sessionsTable()->insert([
        'id' => $currentSessionId,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    fireLoginEvent($user);

    expect(sessionsTable()->where('id', $currentSessionId)->exists())->toBeTrue();
});

it('does nothing when current.tenant is not bound', function () {
    $user = User::factory()->create();

    sessionsTable()->insert([
        'id' => 'orphan-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    // Não faz bind de current.tenant
    $listener = new EnforceSingleSession;
    $listener->handle(new Login('web', $user, false));

    expect(sessionsTable()->where('id', 'orphan-session')->exists())->toBeTrue();
});

it('executes single-session delete on landlord connection only', function () {
    app()->instance('current.tenant', fakeSessionTenant(['features' => ['single_session' => true]]));

    $user = User::factory()->create();
    $currentSessionId = session()->getId();

    sessionsTable()->insert([
        'id' => 'old-session-landlord-only',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    sessionsTable()->insert([
        'id' => $currentSessionId,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    DB::connection('tenant')->flushQueryLog();
    DB::connection('tenant')->enableQueryLog();
    DB::connection('landlord')->flushQueryLog();
    DB::connection('landlord')->enableQueryLog();

    fireLoginEvent($user);

    $tenantDeleteQueries = collect(DB::connection('tenant')->getQueryLog())
        ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'delete')
            && str_contains(strtolower($query['query']), 'sessions'));

    $landlordDeleteQueries = collect(DB::connection('landlord')->getQueryLog())
        ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'delete')
            && str_contains(strtolower($query['query']), 'sessions'));

    expect($tenantDeleteQueries)->toHaveCount(0);
    expect($landlordDeleteQueries)->not->toBeEmpty();
});
