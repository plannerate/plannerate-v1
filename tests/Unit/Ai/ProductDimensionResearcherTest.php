<?php

use App\Ai\Agents\ProductDimensionResearcher;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;

/**
 * O provider do agente precisa continuar sendo o Gemini: é a única credencial configurada
 * (`GEMINI_API_KEY`), e é o que o resto do pipeline assume — a lock `gemini-rate-limit` do
 * ResearchProductDimensionsJob e o supervisor `ai-research` do Horizon, ambos dimensionados
 * para os 15 req/min do Gemini. Trocar só o atributo, sem levar o resto junto, derruba todos
 * os jobs com 401 Invalid API Key.
 *
 * O modelo precisa ser um alias `-latest`, não uma versão fixada: o `gemini-2.5-flash` foi
 * aposentado para chaves novas e passou a responder 404/429 no `generateContent` — ainda que
 * continuasse aparecendo no `ListModels`. O alias acompanha a versão vigente e não expira.
 */
it('pesquisa dimensões pelo Gemini', function () {
    $reflection = new ReflectionClass(ProductDimensionResearcher::class);

    $provider = $reflection->getAttributes(Provider::class)[0] ?? null;
    $model = $reflection->getAttributes(Model::class)[0] ?? null;

    expect($provider)->not->toBeNull()
        ->and($provider->newInstance()->value)->toBe(Lab::Gemini)
        ->and($model)->not->toBeNull()
        ->and($model->newInstance()->value)->toEndWith('-latest');
});

it('mantém a lock de rate limit alinhada com o provider do agente', function () {
    $jobSource = file_get_contents(base_path('app/Jobs/ResearchProductDimensionsJob.php'));

    expect($jobSource)->toContain("Cache::lock('gemini-rate-limit'");
});
