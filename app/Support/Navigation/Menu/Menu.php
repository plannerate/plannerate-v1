<?php

namespace App\Support\Navigation\Menu;

class Menu
{
    /** @var array<int, MenuNode> */
    private array $nodes = [];

    private function __construct(
        private string $context,
    ) {}

    public static function make(string $context): self
    {
        return new self($context);
    }

    public function context(): string
    {
        return $this->context;
    }

    public function item(string $key, callable $configure): self
    {
        $item = MenuItem::make($key);
        $configure($item);
        $this->nodes[] = $item;

        return $this;
    }

    public function group(string $key, callable $configure): self
    {
        $group = MenuGroup::make($key);
        $configure($group);
        $this->nodes[] = $group;

        return $this;
    }

    public function submenu(string $key, callable $configure): self
    {
        $submenu = MenuSubmenu::make($key);
        $configure($submenu);
        $this->nodes[] = $submenu;

        return $this;
    }

    public function separator(string $key, ?int $order = null): self
    {
        $separator = MenuSeparator::make($key);

        if ($order !== null) {
            $separator->setOrder($order);
        }

        $this->nodes[] = $separator;

        return $this;
    }

    /**
     * @return array<int, MenuNode>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }
}
