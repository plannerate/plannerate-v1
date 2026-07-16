<?php

beforeEach(function (): void {
    config()->set('services.metrics.token', 'test-metrics-token');
});

it('nega acesso sem token', function (): void {
    $this->get('/metrics')->assertStatus(401);
});

it('nega acesso com token errado', function (): void {
    $this->withHeader('Authorization', 'Bearer token-errado')
        ->get('/metrics')
        ->assertStatus(401);
});

it('nega acesso quando o token não está configurado', function (): void {
    config()->set('services.metrics.token', null);

    $this->withHeader('Authorization', 'Bearer qualquer-coisa')
        ->get('/metrics')
        ->assertStatus(401);
});

it('expõe métricas no formato Prometheus com token válido', function (): void {
    $response = $this->withHeader('Authorization', 'Bearer test-metrics-token')
        ->get('/metrics');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    $body = (string) $response->getContent();
    expect($body)
        ->toContain('plannerate_up 1')
        ->toContain('# TYPE plannerate_horizon_queue_pending gauge')
        ->toContain('plannerate_horizon_queue_pending{queue="critical"}')
        ->toContain('plannerate_horizon_queue_pending{queue="imports-process"}')
        ->toContain('plannerate_horizon_failed_jobs_total')
        ->toContain('plannerate_horizon_up');
});
