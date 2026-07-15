<?php

namespace App\Services\Integrations\Lookup;

/**
 * Resultado de uma busca pontual de produto + vendas na API do tenant.
 *
 * Carrega contagens por seção e erros acumulados, sem lançar exceções — cada
 * seção (produto/vendas) é isolada para que uma falha parcial ainda persista o
 * que deu certo.
 */
class SingleProductFetchResult
{
    /** @param array<int, string> $errors */
    public function __construct(
        public bool $configured = true,
        public int $productsPersisted = 0,
        public int $salesPersisted = 0,
        public int $storesQueried = 0,
        public array $errors = [],
    ) {}

    /** Nenhum bloco requests.lookups configurado para o provider. */
    public static function notConfigured(): self
    {
        return new self(configured: false);
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function persistedAnything(): bool
    {
        return $this->productsPersisted > 0 || $this->salesPersisted > 0;
    }
}
