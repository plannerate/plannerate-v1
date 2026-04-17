<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\DTOs;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

abstract class BaseDTOProcessor
{
    protected array $params = [];

    /**
     * Processa os dados e envia para a API
     *
     * @param  array  $data  Dados a serem processados
     * @return array Resposta da API
     */
    abstract public function process(array $data, array $params = []): array;

    protected function getClientId(): ?string
    {
        return data_get($this->params, 'client_id', null);
    }

    protected function getStoreId(): ?string
    {
        return data_get($this->params, 'store_id', null);
    }

    public function getIntegrationId(): ?string
    {
        return data_get($this->params, 'integration_id', null);
    }

    protected function getTenantId(): ?string
    {
        return data_get($this->params, 'tenant_id', config('app.current_tenant_id'));
    }

    protected function getUserId(): ?string
    {
        return data_get($this->params, 'user_id', null);
    }

    /**
     * Método para obter o valor de uma chave específica
     *
     * @param  string  $key  Chave a ser buscada
     * @param  array  $data  Dados de onde buscar
     * @param  mixed  $default  Valor padrão a ser retornado se a chave não existir
     * @return mixed Valor encontrado ou null se não existir
     */
    protected function getProcessedValue(string $key, array $data, mixed $default = null): mixed
    {
        return data_get($data, $key, $default);
    }

    protected function getProcessedGtin(string $key, array $rawData): ?string
    {
        $gtins = data_get($rawData, $key, []);

        if (! is_array($gtins) || count($gtins) === 0) {
            return null;
        }

        // Estrutura Sysmo: {"completo": [...], "resumido": [...]}
        if (isset($gtins['completo']) && is_array($gtins['completo'])) {
            $principalGtin = null;
            $firstValidGtin = null;

            foreach ($gtins['completo'] as $gtin) {
                $gtinValue = data_get($gtin, 'gtin', data_get($gtin, 'ean', null));

                if (! empty($gtinValue)) {
                    if ($firstValidGtin === null) {
                        $firstValidGtin = $gtinValue;
                    }

                    if (data_get($gtin, 'principal', null) === 'S') {
                        $principalGtin = $gtinValue;
                    }
                }
            }

            if ($principalGtin !== null) {
                return $principalGtin;
            }
            if ($firstValidGtin !== null) {
                return $firstValidGtin;
            }
        }

        // Estrutura Sysmo resumida: apenas array de strings
        if (isset($gtins['resumido']) && is_array($gtins['resumido']) && count($gtins['resumido']) > 0) {
            $firstResumido = $gtins['resumido'][0] ?? null;
            if (! empty($firstResumido)) {
                return $firstResumido;
            }
        }

        // Estrutura simples: array direto de GTINs
        $principalGtin = null;
        $firstValidGtin = null;

        foreach ($gtins as $gtin) {
            if (is_string($gtin) && ! empty($gtin)) {
                return $gtin;
            } elseif (is_array($gtin)) {
                $gtinValue = data_get($gtin, 'gtin', data_get($gtin, 'ean', null));

                if (! empty($gtinValue)) {
                    if ($firstValidGtin === null) {
                        $firstValidGtin = $gtinValue;
                    }

                    if (data_get($gtin, 'principal', null) === 'S') {
                        $principalGtin = $gtinValue;
                    }
                }
            }
        }

        return $principalGtin ?? $firstValidGtin;
    }

    /**
     * Converte string para float de forma segura
     */
    protected function convertToFloat(?string $value): ?float
    {
        if (empty($value)) {
            return null;
        }

        // Remover caracteres não numéricos exceto vírgula e ponto
        $cleaned = preg_replace('/[^\d,.,-]/', '', $value);

        if (empty($cleaned)) {
            return null;
        }

        // Substituir vírgula por ponto para conversão
        $cleaned = str_replace(',', '.', $cleaned);

        try {
            return (float) $cleaned;
        } catch (\Exception $e) {
            Log::warning('Valor não numérico encontrado para conversão', [
                'tenant_id' => $this->tenantId,
                'original_value' => $value,
                'cleaned_value' => $cleaned,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Converte string para integer de forma segura
     */
    protected function convertToInt(?string $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        // Remover caracteres não numéricos
        $cleaned = (int) $value;

        if (empty($cleaned)) {
            return null;
        }

        try {
            return (int) $cleaned;
        } catch (\Exception $e) {
            Log::warning('Valor não numérico encontrado para conversão para inteiro', [
                'tenant_id' => $this->tenantId,
                'original_value' => $value,
                'cleaned_value' => $cleaned,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Converte string para data de forma segura
     */
    protected function convertToDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Tentar diferentes formatos de data
            $formats = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }

            // Se nenhum formato funcionou, tentar strtotime
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Data inválida encontrada', [
                'tenant_id' => $this->tenantId,
                'original_value' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Gera ID determinístico baseado no EAN + tenant_id
     */
    protected function generateProductId(string $ean, string $tenantId): string
    {
        // Chave única baseada na constraint única da tabela products
        $uniqueKey = $tenantId.'|'.$ean;

        // Gerar hash determinístico que sempre produz o mesmo resultado
        $hash = md5($uniqueKey);

        // Criar ID determinístico baseado APENAS no hash (sem time)
        // Usa prefixo fixo + hash para garantir formato ULID de 26 chars
        $prefix = 'T1'; // Prefixo fixo para products (T=Tenant, 1=versão)
        $hashComponent = strtoupper(substr($hash, 0, 24)); // 24 chars restantes

        return $prefix.$hashComponent;
    }
}
