<?php

namespace App\Services;

use App\Ai\Agents\ReorganizaCategoriasMercadologico;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Category;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\MercadologicoReorganizeLog;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Planogram;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReorganizaCategoriasComIa
{
    /**
     * Tamanho de cada chunk enviado ao modelo (evita estourar a janela de contexto).
     */
    private const CHUNK_SIZE = 2500;

    /**
     * Gera sugestão de reorganização via IA em chunks, junta os resultados e salva no log.
     * Não aplica alterações; apenas persiste para pré-visualização no drawer.
     */
    public function sugerir(): MercadologicoReorganizeLog
    {
        $snapshotBackup = $this->buildBackupSnapshot();
        $categoriesPayload = $this->buildCategoriesPayload();

        if (empty($categoriesPayload)) {
            $log = MercadologicoReorganizeLog::query()->create([
                'snapshot_backup' => $snapshotBackup,
                'agent_response' => [
                    'renames' => [],
                    'merges' => [],
                    'reasoning' => 'Nenhuma categoria para analisar.',
                ],
                'status' => 'suggestion',
            ]);

            return $log;
        }

        $chunks = array_chunk($categoriesPayload, self::CHUNK_SIZE);
        $totalChunks = count($chunks);
        $allRenames = [];
        $allMerges = [];
        $allDisable = [];
        $allDelete = [];
        $reasonings = [];

        $agent = new ReorganizaCategoriasMercadologico;
        $validIds = array_flip(array_column($categoriesPayload, 'id'));

        foreach ($chunks as $index => $chunk) {
            $chunkNum = $index + 1;
            $prompt = 'Analise as categorias abaixo e retorne renames (padronização de nomes) e merges (duplicados a fundir). ';
            if ($totalChunks > 1) {
                $prompt .= "Lote {$chunkNum} de {$totalChunks} (analise apenas as categorias deste JSON). ";
            }
            $prompt .= "Chaves do JSON: i=id, n=nome, l=nível, p=parent_id, cc=filhos, pc=produtos, pn=planogramas. Use 'i' como category_id, keep_id, remove_id e nos arrays disable e delete. Categorias: ";
            $prompt .= json_encode($this->compactPayloadForChunk($chunk), JSON_UNESCAPED_UNICODE);

            $rawResponse = $agent->prompt($prompt, timeout: 120);
            $parsed = $this->parseAgentResponse($rawResponse);

            foreach ($parsed['renames'] as $r) {
                $id = $r['category_id'] ?? null;
                if ($id !== null && isset($validIds[$id])) {
                    $allRenames[$id] = $r;
                }
            }
            foreach ($parsed['merges'] as $m) {
                $key = ($m['keep_id'] ?? '').'|'.($m['remove_id'] ?? '');
                if ($key !== '|' && isset($validIds[$m['keep_id']], $validIds[$m['remove_id']])) {
                    $allMerges[$key] = $m;
                }
            }
            foreach ($parsed['disable'] as $id) {
                if (is_string($id) && $id !== '' && isset($validIds[$id])) {
                    $allDisable[$id] = true;
                }
            }
            foreach ($parsed['delete'] as $id) {
                if (is_string($id) && $id !== '' && isset($validIds[$id])) {
                    $allDelete[$id] = true;
                }
            }
            if ((string) $parsed['reasoning'] !== '') {
                $reasonings[] = $totalChunks > 1 ? "Lote {$chunkNum}: ".$parsed['reasoning'] : $parsed['reasoning'];
            }
        }

        $agentResponse = [
            'renames' => array_values($allRenames),
            'merges' => array_values($allMerges),
            'disable' => array_keys($allDisable),
            'delete' => array_keys($allDelete),
            'reasoning' => implode("\n\n", $reasonings),
        ];

        $log = MercadologicoReorganizeLog::query()->create([
            'snapshot_backup' => $snapshotBackup,
            'agent_response' => $agentResponse,
            'status' => 'suggestion',
        ]);

        return $log;
    }

    /**
     * Payload compacto por chunk (chaves curtas) para caber mais categorias no contexto.
     * O agente continua retornando category_id, new_name, keep_id, remove_id (IDs inalterados).
     *
     * @param  array<int, array<string, mixed>>  $chunk
     * @return array<int, array<string, mixed>>
     */
    protected function compactPayloadForChunk(array $chunk): array
    {
        $out = [];
        foreach ($chunk as $c) {
            $out[] = [
                'i' => $c['id'] ?? '',
                'n' => $c['name'] ?? '',
                'l' => $c['level'] ?? 0,
                'p' => $c['parent_id'] ?? null,
                'cc' => $c['children_count'] ?? 0,
                'pc' => $c['products_count'] ?? 0,
                'pn' => $c['planograms_count'] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Faz parse da resposta do agente (JSON puro ou dentro de markdown) e normaliza para renames, merges, reasoning.
     *
     * @return array{renames: array<int, array{category_id: string, new_name: string}>, merges: array<int, array{keep_id: string, remove_id: string}>, reasoning: string}
     */
    protected function parseAgentResponse(mixed $rawResponse): array
    {
        $default = [
            'renames' => [],
            'merges' => [],
            'disable' => [],
            'delete' => [],
            'reasoning' => '',
        ];

        if (is_string($rawResponse)) {
            $text = $rawResponse;
        } elseif (is_object($rawResponse) && property_exists($rawResponse, 'text')) {
            $text = $rawResponse->text;
        } else {
            $text = (string) $rawResponse;
        }
        $text = trim($text);

        if ($text === '') {
            return $default;
        }

        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```\s*$/m', $text, $m)) {
            $text = trim($m[1]);
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            return $default;
        }

        $renames = isset($data['renames']) && is_array($data['renames'])
            ? array_values(array_filter($data['renames'], function ($r) {
                return is_array($r) && isset($r['category_id'], $r['new_name']);
            }))
            : [];
        $merges = isset($data['merges']) && is_array($data['merges'])
            ? array_values(array_filter($data['merges'], function ($item) {
                return is_array($item) && isset($item['keep_id'], $item['remove_id']);
            }))
            : [];
        $disable = isset($data['disable']) && is_array($data['disable'])
            ? array_values(array_filter($data['disable'], fn ($id) => is_string($id) && $id !== ''))
            : [];
        $delete = isset($data['delete']) && is_array($data['delete'])
            ? array_values(array_filter($data['delete'], fn ($id) => is_string($id) && $id !== ''))
            : [];

        return [
            'renames' => $renames,
            'merges' => $merges,
            'disable' => $disable,
            'delete' => $delete,
            'reasoning' => is_string($data['reasoning'] ?? '') ? $data['reasoning'] : '',
        ];
    }

    /**
     * Aplica a sugestão de um log (renames + merges) e marca como aplicado.
     */
    public function aplicar(MercadologicoReorganizeLog $log): array
    {
        if ($log->isApplied()) {
            return [
                'renames_count' => 0,
                'merges_count' => 0,
                'disable_count' => 0,
                'delete_count' => 0,
                'reasoning' => 'Esta sugestão já foi aplicada.',
            ];
        }

        $agentResponse = $log->agent_response ?? [];
        $renames = $agentResponse['renames'] ?? [];
        $merges = $agentResponse['merges'] ?? [];
        $disableIds = $agentResponse['disable'] ?? [];
        $deleteIds = $agentResponse['delete'] ?? [];
        $reasoning = $agentResponse['reasoning'] ?? '';

        $validIds = Category::query()->pluck('id')->all();

        $mergeList = [];
        foreach ($merges as $item) {
            $keepId = $item['keep_id'] ?? null;
            $removeId = $item['remove_id'] ?? null;
            if ($keepId === null || $removeId === null || $keepId === $removeId) {
                continue;
            }
            if (! in_array($keepId, $validIds, true) || ! in_array($removeId, $validIds, true)) {
                continue;
            }
            $mergeList[] = ['keep_id' => $keepId, 'remove_id' => $removeId];
        }

        $renamesCount = 0;
        $mergesCount = 0;
        $disableCount = 0;
        $deleteCount = 0;

        DB::transaction(function () use ($log, $renames, $mergeList, $disableIds, $deleteIds, $validIds, &$renamesCount, &$mergesCount, &$disableCount, &$deleteCount): void {
            foreach ($renames as $item) {
                $id = $item['category_id'] ?? null;
                $newName = $item['new_name'] ?? null;
                if ($id === null || $newName === null || $newName === '' || ! in_array($id, $validIds, true)) {
                    continue;
                }
                $category = Category::query()->find($id);
                if (! $category) {
                    continue;
                }
                $slug = $this->uniqueSlugForName($newName, $id);
                $category->update(['name' => $newName, 'slug' => $slug]);
                $renamesCount++;
            }

            $removedIds = [];
            foreach ($mergeList as $merge) {
                $keepId = $merge['keep_id'];
                $removeId = $merge['remove_id'];
                if (in_array($removeId, $removedIds, true) || in_array($keepId, $removedIds, true)) {
                    continue;
                }
                $this->mergeCategoryInto($keepId, $removeId);
                $removedIds[] = $removeId;
                $mergesCount++;
            }

            foreach ($disableIds as $id) {
                if (! is_string($id) || $id === '' || ! in_array($id, $validIds, true)) {
                    continue;
                }
                $updated = Category::query()->where('id', $id)->update(['status' => 'draft']);
                if ($updated) {
                    $disableCount++;
                }
            }

            foreach ($deleteIds as $id) {
                if (! is_string($id) || $id === '' || ! in_array($id, $validIds, true)) {
                    continue;
                }
                $category = Category::query()->withCount(['children', 'products', 'planograms'])->find($id);
                if ($category && $category->children_count === 0 && $category->products_count === 0 && $category->planograms_count === 0) {
                    $category->delete();
                    $deleteCount++;
                }
            }

            $log->update(['status' => 'applied', 'applied_at' => now()]);
        });

        return [
            'renames_count' => $renamesCount,
            'merges_count' => $mergesCount,
            'disable_count' => $disableCount,
            'delete_count' => $deleteCount,
            'reasoning' => $reasoning,
        ];
    }

    /**
     * Restaura o estado a partir do backup do log (categorias, products e planograms).
     */
    public function restaurar(MercadologicoReorganizeLog $log): void
    {
        $backup = $log->snapshot_backup;
        if (! is_array($backup)) {
            return;
        }

        $categories = $backup['categories'] ?? [];
        $products = $backup['products'] ?? [];
        $planograms = $backup['planograms'] ?? [];

        DB::transaction(function () use ($categories, $products, $planograms): void {
            foreach ($products as $row) {
                $id = $row['id'] ?? null;
                $categoryId = $row['category_id'] ?? null;
                if ($id !== null) {
                    Product::query()->where('id', $id)->update(['category_id' => $categoryId]);
                }
            }

            foreach ($planograms as $row) {
                $id = $row['id'] ?? null;
                $categoryId = $row['category_id'] ?? null;
                if ($id !== null) {
                    Planogram::query()->where('id', $id)->update(['category_id' => $categoryId]);
                }
            }

            foreach ($categories as $attrs) {
                $id = $attrs['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                $category = Category::query()->withTrashed()->find($id);
                if ($category) {
                    $category->restore();
                    $category->update([
                        'name' => $attrs['name'] ?? $category->name,
                        'slug' => $attrs['slug'] ?? $category->slug,
                        'category_id' => $attrs['category_id'] ?? null,
                        'nivel' => $attrs['nivel'] ?? $category->nivel,
                        'level_name' => $attrs['level_name'] ?? $category->level_name,
                        'hierarchy_position' => $attrs['hierarchy_position'] ?? $category->hierarchy_position,
                        'status' => $attrs['status'] ?? $category->status,
                    ]);
                }
            }
        });
    }

    /**
     * Snapshot para restore: categorias (com trashed), products e planograms (id + category_id).
     *
     * @return array{categories: array<int, array<string, mixed>>, products: array<int, array{id: string, category_id: string|null}>, planograms: array<int, array{id: string, category_id: string|null}>}
     */
    protected function buildBackupSnapshot(): array
    {
        $categories = Category::query()
            ->withTrashed()
            ->orderBy('category_id')
            ->orderBy('hierarchy_position')
            ->orderBy('name')
            ->get();

        $categoriesPayload = $categories->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'category_id' => $c->category_id,
            'nivel' => $c->nivel,
            'level_name' => $c->level_name,
            'hierarchy_position' => $c->hierarchy_position,
            'status' => $c->status,
            'deleted_at' => $c->deleted_at?->toIso8601String(),
        ])->values()->all();

        $productsPayload = Product::query()
            ->get(['id', 'category_id'])
            ->map(fn (Product $p) => ['id' => $p->id, 'category_id' => $p->category_id])
            ->values()->all();

        $planogramsPayload = Planogram::query()
            ->get(['id', 'category_id'])
            ->map(fn (Planogram $p) => ['id' => $p->id, 'category_id' => $p->category_id])
            ->values()->all();

        return [
            'categories' => $categoriesPayload,
            'products' => $productsPayload,
            'planograms' => $planogramsPayload,
        ];
    }

    /**
     * Lista plana de categorias para o agente (id, name, level, level_name, parent_id, children_count, products_count).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildCategoriesPayload(): array
    {
        $categories = Category::query()
            ->withCount(['children', 'products', 'planograms'])
            ->orderBy('category_id')
            ->orderBy('hierarchy_position')
            ->orderBy('name')
            ->get();

        return $categories->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'level' => (int) $c->nivel,
            'level_name' => $c->level_name,
            'parent_id' => $c->category_id,
            'children_count' => $c->children_count ?? 0,
            'products_count' => $c->products_count ?? 0,
            'planograms_count' => $c->planograms_count ?? 0,
        ])->values()->all();
    }

    /**
     * Funde a categoria remove_id na keep_id: move filhos, produtos e planogramas para keep_id e remove remove_id.
     */
    protected function mergeCategoryInto(string $keepId, string $removeId): void
    {
        $keep = Category::query()->find($keepId);
        $remove = Category::query()->find($removeId);
        if (! $keep || ! $remove) {
            return;
        }

        Category::query()->where('category_id', $removeId)->update(['category_id' => $keepId]);
        Product::query()->where('category_id', $removeId)->update(['category_id' => $keepId]);
        Planogram::query()->where('category_id', $removeId)->update(['category_id' => $keepId]);

        $remove->delete();
    }

    private function uniqueSlugForName(string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'categoria';
        }
        $slug = $base;
        $n = 0;
        while (Category::query()->where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
