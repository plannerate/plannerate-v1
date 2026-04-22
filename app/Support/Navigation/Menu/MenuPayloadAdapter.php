<?php

namespace App\Support\Navigation\Menu;

use App\Support\Navigation\Menu\Contracts\ResolvesMenuAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class MenuPayloadAdapter
{
    public function __construct(
        private ResolvesMenuAuthorization $authorizationResolver,
    ) {}

    /**
     * @return array{context: string, main: array<int, array<string, mixed>>}
     */
    public function toNavigation(Menu $menu, ?Authenticatable $user): array
    {
        $nodes = $this->sortNodes($menu->nodes());

        return [
            'context' => $menu->context(),
            'main' => collect($nodes)
                ->map(fn (MenuNode $node): ?array => $this->mapNode($node, $user))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, MenuNode>  $nodes
     * @return array<int, MenuNode>
     */
    private function sortNodes(array $nodes): array
    {
        return collect($nodes)
            ->sortBy(fn (MenuNode $node): int => $node->order())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapNode(MenuNode $node, ?Authenticatable $user): ?array
    {
        if ($node instanceof MenuSeparator) {
            return [
                'type' => 'separator',
                'key' => $node->key(),
                'order' => $node->order(),
                'can' => true,
            ];
        }

        if ($node instanceof MenuItem) {
            $can = $this->authorizationResolver->allows($user, $node->getAbility(), $node->getSubject());

            if (! $can) {
                return null;
            }

            return [
                'type' => 'item',
                'key' => $node->key(),
                'title' => $node->getLabel(),
                'href' => $node->getHref(),
                'icon' => $node->getIcon(),
                'order' => $node->order(),
                'can' => true,
                'ability' => $node->getAbility(),
                'subject' => $node->getSubject(),
            ];
        }

        if ($node instanceof MenuSubmenu) {
            $can = $this->authorizationResolver->allows($user, $node->getAbility(), $node->getSubject());

            if (! $can) {
                return null;
            }

            $children = collect($this->sortNodes($node->children()))
                ->map(fn (MenuNode $child): ?array => $this->mapNode($child, $user))
                ->filter()
                ->values()
                ->all();

            if ($children === []) {
                return null;
            }

            return [
                'type' => 'submenu',
                'key' => $node->key(),
                'title' => $node->getLabel(),
                'icon' => $node->getIcon(),
                'order' => $node->order(),
                'can' => true,
                'ability' => $node->getAbility(),
                'subject' => $node->getSubject(),
                'children' => $children,
            ];
        }

        if ($node instanceof MenuGroup) {
            $children = collect($this->sortNodes($node->children()))
                ->map(fn (MenuNode $child): ?array => $this->mapNode($child, $user))
                ->filter()
                ->values()
                ->all();

            if ($children === []) {
                return null;
            }

            return [
                'type' => 'group',
                'key' => $node->key(),
                'title' => $node->getLabel(),
                'order' => $node->order(),
                'can' => true,
                'children' => $children,
            ];
        }

        return null;
    }
}
