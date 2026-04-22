<?php

namespace App\Support\Navigation\Menu;

class MenuSeparator extends MenuNode
{
    public static function make(string $key): self
    {
        return new self($key);
    }

    public function type(): string
    {
        return 'separator';
    }
}
