<?php

namespace App\Support\Modules;

final class ModuleSlug
{
    public const KANBAN = 'kanban';

    public const IMAGE_BANK = 'image-bank';

    public const PLANOGRAM_AUTOTIC = 'planogram-automatic';

    public const PLANOGRAM_TEMPLATE = 'planogram-template';

    /**
     * Slugs equivalentes por módulo. Cadastros antigos da tabela `modules` usam
     * nomes em PT-BR, então ambos precisam resolver para o mesmo módulo.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        self::PLANOGRAM_AUTOTIC => ['planograma-automatico'],
        self::PLANOGRAM_TEMPLATE => ['planograma-template'],
    ];

    /**
     * Resolve um slug (canônico ou alias) para o slug canônico do módulo.
     */
    public static function canonical(string $slug): string
    {
        foreach (self::ALIASES as $canonical => $aliases) {
            if ($slug === $canonical || in_array($slug, $aliases, true)) {
                return $canonical;
            }
        }

        return $slug;
    }

    /**
     * Todos os slugs que identificam o mesmo módulo (canônico + aliases).
     *
     * @return list<string>
     */
    public static function variants(string $slug): array
    {
        $canonical = self::canonical($slug);

        return [$canonical, ...(self::ALIASES[$canonical] ?? [])];
    }
}
