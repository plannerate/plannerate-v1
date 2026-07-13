<?php

beforeEach(function (): void {
    config()->set('whatsapp-cloud.verify_token', 'test-verify-token');
});

function whatsappVerifyQuery(): array
{
    return [
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'test-verify-token',
        'hub_challenge' => 'the-challenge',
    ];
}

test('webhook verify succeeds on the landlord domain', function (): void {
    $url = 'http://'.config('app.landlord_domain').'/webhooks/whatsapp/cloud?'.http_build_query(whatsappVerifyQuery());

    $this->get($url)
        ->assertOk()
        ->assertSee('the-challenge');
});

test('webhook verify is blocked outside the landlord domain even with a valid token', function (): void {
    $url = 'http://some-tenant.test/webhooks/whatsapp/cloud?'.http_build_query(whatsappVerifyQuery());

    $this->get($url)->assertForbidden();
});
