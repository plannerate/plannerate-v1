<?php

namespace App\Support\Navigation\Menu;

abstract class MenuNode
{
    public function __construct(
        protected string $key,
        protected int $order = 100,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    abstract public function type(): string;
}
