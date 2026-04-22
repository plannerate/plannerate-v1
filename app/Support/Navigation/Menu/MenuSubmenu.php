<?php

namespace App\Support\Navigation\Menu;

class MenuSubmenu extends MenuNode
{
    private string $label = '';

    private ?string $icon = null;

    private ?string $ability = null;

    /** @var class-string|null */
    private ?string $subject = null;

    /** @var array<int, MenuNode> */
    private array $children = [];

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function type(): string
    {
        return 'submenu';
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  class-string|null  $subject
     */
    public function authorize(?string $ability, ?string $subject = null): static
    {
        $this->ability = $ability;
        $this->subject = $subject;

        return $this;
    }

    public function item(string $key, callable $configure): static
    {
        $item = MenuItem::make($key);
        $configure($item);
        $this->children[] = $item;

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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getAbility(): ?string
    {
        return $this->ability;
    }

    /**
     * @return class-string|null
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }
}
