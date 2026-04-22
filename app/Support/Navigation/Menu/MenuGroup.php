<?php

namespace App\Support\Navigation\Menu;

class MenuGroup extends MenuNode
{
    private string $label = '';

    /** @var array<int, MenuNode> */
    private array $children = [];

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function type(): string
    {
        return 'group';
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function item(string $key, callable $configure): static
    {
        $item = MenuItem::make($key);
        $configure($item);
        $this->children[] = $item;

        return $this;
    }

    public function submenu(string $key, callable $configure): static
    {
        $submenu = MenuSubmenu::make($key);
        $configure($submenu);
        $this->children[] = $submenu;

        return $this;
    }

    public function separator(string $key, ?int $order = null): static
    {
        $separator = MenuSeparator::make($key);

        if ($order !== null) {
            $separator->setOrder($order);
        }

        $this->children[] = $separator;

        return $this;
    }

    /**
     * @return array<int, MenuNode>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
