<?php

namespace App\Support\Navigation\Menu;

class MenuItem extends MenuNode
{
    private string $label = '';

    private string $href = '#';

    private ?string $icon = null;

    private ?string $ability = null;

    /** @var class-string|null */
    private ?string $subject = null;

    /**
     * Contador exibido ao lado do item (null = sem badge).
     *
     * Closure e não int: o menu é montado a cada request, e resolver a contagem só quando o item
     * sobrevive à autorização evita uma query para quem nem vai ver o item.
     *
     * @var (\Closure(): ?int)|null
     */
    private $badgeResolver = null;

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function type(): string
    {
        return 'item';
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function href(string $href): static
    {
        $this->href = $href;

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

    /**
     * @param  \Closure(): ?int  $resolver
     */
    public function badge(\Closure $resolver): static
    {
        $this->badgeResolver = $resolver;

        return $this;
    }

    /**
     * Valor do badge, ou null quando não há badge ou a contagem é zero.
     *
     * Zero vira null de propósito: um badge "0" é ruído — o item já comunica que não há nada
     * pendente pela ausência do contador.
     */
    public function resolveBadge(): ?int
    {
        if ($this->badgeResolver === null) {
            return null;
        }

        $count = ($this->badgeResolver)();

        return $count > 0 ? $count : null;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHref(): string
    {
        return $this->href;
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
