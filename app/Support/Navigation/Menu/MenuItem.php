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
