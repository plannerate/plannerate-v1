<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers;

use Callcocam\LaravelRaptorPlannerate\Services\Export\GondolaPrintService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exibe visualização pública da gôndola, sem autenticação.
 * Destinado a repositores, fornecedores e pessoas com o link direto.
 */
class GondolaShareController extends Controller
{
    public function __construct(
        protected GondolaPrintService $printService
    ) {}

    public function show(string $gondolaId): Response
    {
        $data = $this->printService->prepareGondolaData($gondolaId);

        return Inertia::render('gondola/GondolaShare', [
            'gondola' => $data['gondola'],
            'sections' => $data['sections'],
        ]);
    }
}
