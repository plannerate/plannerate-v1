<?php

namespace App\Http\Middleware;

use App\Support\Workflow\GondolaEditGate;
use Callcocam\LaravelRaptorPlannerate\Models\Layer;
use Callcocam\LaravelRaptorPlannerate\Models\Section;
use Callcocam\LaravelRaptorPlannerate\Models\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Shelf;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que apenas o responsável que iniciou a gôndola possa gravar mudanças
 * nela pelas APIs do editor (save-changes, seções, prateleiras, segmentos,
 * camadas). Sem isso, bloquear apenas a página do editor seria inócuo — a
 * edição real trafega por estes endpoints.
 *
 * Fecha por padrão (fail-closed): se, numa rota protegida com Kanban ativo, a
 * gôndola-alvo não puder ser resolvida, a requisição é negada (403). Assim,
 * incluir uma rota nova sem mapeá-la aqui falha de forma segura em vez de abrir
 * um buraco. Fora do Kanban, mantém o comportamento legado (sem restrição).
 */
class EnsureCanEditGondola
{
    public function __construct(private GondolaEditGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gate->kanbanActive()) {
            return $next($request);
        }

        $user = $request->user();
        $gondolaId = $this->resolveGondolaId($request);

        abort_if($user === null || $gondolaId === null, 403);

        abort_unless($this->gate->decide($user, $gondolaId)->allowsEditing(), 403);

        return $next($request);
    }

    /**
     * Resolve a gôndola-alvo subindo a hierarquia
     * gondolas → sections → shelves → segments → layers.
     */
    private function resolveGondolaId(Request $request): ?string
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $name = (string) $route->getName();

        // save-changes: o alvo efetivo é o corpo (gondola_id) — o controller grava
        // por ele, não pelo parâmetro da rota; validar o parâmetro seria burlável.
        if (str_ends_with($name, 'gondolas.save-changes')) {
            $bodyId = $request->input('gondola_id');

            return $bodyId !== null ? (string) $bodyId : $this->idOf($route->parameter('gondola'));
        }

        if ($gondolaId = $this->idOf($route->parameter('gondola'))) {
            return $gondolaId;
        }

        if (str_ends_with($name, 'sections.update')) {
            return $this->sectionGondola($this->idOf($route->parameter('id')));
        }

        if ($section = $this->idOf($route->parameter('section'))) {
            return $this->sectionGondola($section);
        }

        if (str_ends_with($name, 'shelves.update')) {
            return $this->shelfGondola($this->idOf($route->parameter('id')));
        }

        if ($shelf = $this->idOf($route->parameter('shelf'))) {
            return $this->shelfGondola($shelf);
        }

        if (str_ends_with($name, 'segments.update')) {
            return $this->segmentGondola($this->idOf($route->parameter('id')));
        }

        if ($segment = $this->idOf($route->parameter('segment'))) {
            return $this->segmentGondola($segment);
        }

        if (str_ends_with($name, 'layers.update')) {
            return $this->layerGondola($this->idOf($route->parameter('id')));
        }

        if ($layer = $this->idOf($route->parameter('layer'))) {
            return $this->layerGondola($layer);
        }

        return null;
    }

    private function idOf(mixed $param): ?string
    {
        if ($param === null) {
            return null;
        }

        if ($param instanceof Model) {
            return (string) $param->getKey();
        }

        return (string) $param;
    }

    private function sectionGondola(?string $sectionId): ?string
    {
        if ($sectionId === null) {
            return null;
        }

        $gondolaId = Section::query()->whereKey($sectionId)->value('gondola_id');

        return $gondolaId !== null ? (string) $gondolaId : null;
    }

    private function shelfGondola(?string $shelfId): ?string
    {
        if ($shelfId === null) {
            return null;
        }

        $sectionId = Shelf::query()->whereKey($shelfId)->value('section_id');

        return $sectionId !== null ? $this->sectionGondola((string) $sectionId) : null;
    }

    private function segmentGondola(?string $segmentId): ?string
    {
        if ($segmentId === null) {
            return null;
        }

        $shelfId = Segment::query()->whereKey($segmentId)->value('shelf_id');

        return $shelfId !== null ? $this->shelfGondola((string) $shelfId) : null;
    }

    private function layerGondola(?string $layerId): ?string
    {
        if ($layerId === null) {
            return null;
        }

        $segmentId = Layer::query()->whereKey($layerId)->value('segment_id');

        return $segmentId !== null ? $this->segmentGondola((string) $segmentId) : null;
    }
}
