<?php

namespace App\Services\Integrations\Support;

/**
 * Detecta erro LÓGICO em resposta com HTTP 2xx.
 *
 * Algumas APIs (RP Info, p.ex.) devolvem HTTP 200 mesmo em falha, sinalizando o
 * erro no corpo: `{"response": {"status": "error", "messages": [...]}}`. Sem essa
 * checagem o motor lê zero itens, trata como "página vazia" e — pior — chama
 * `IntegrationImportRun::recordCovered()`, marcando o dia como coberto. O dia
 * nunca mais seria re-buscado e o buraco no dado ficaria invisível.
 *
 * Configuração no `response` do blueprint (opcional; sem ela nada muda):
 *
 *   "error_status_path":   "response.status"
 *   "error_status_values": ["error"]        // default quando omitido
 *   "error_message_path":  "response.messages"
 */
class IntegrationResponseGuard
{
    /**
     * Retorna a mensagem do erro lógico, ou null quando a resposta está ok.
     *
     * @param  array<string, mixed>  $responseData  Corpo já decodificado
     * @param  array<string, mixed>  $responseMeta  IntegrationApi->response
     */
    public static function logicalErrorMessage(array $responseData, array $responseMeta): ?string
    {
        $statusPath = (string) data_get($responseMeta, 'error_status_path', '');

        if ($statusPath === '') {
            return null;
        }

        $status = data_get($responseData, $statusPath);

        if (! is_scalar($status)) {
            return null;
        }

        $errorValues = array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) (data_get($responseMeta, 'error_status_values') ?: ['error']),
        );

        if (! in_array(mb_strtolower(trim((string) $status)), $errorValues, true)) {
            return null;
        }

        return self::extractMessage($responseData, $responseMeta) ?? sprintf('status "%s"', $status);
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     */
    private static function extractMessage(array $responseData, array $responseMeta): ?string
    {
        $messagePath = (string) data_get($responseMeta, 'error_message_path', '');

        if ($messagePath === '') {
            return null;
        }

        $message = data_get($responseData, $messagePath);

        if (is_scalar($message)) {
            return self::truncate((string) $message);
        }

        if (! is_array($message)) {
            return null;
        }

        // Formato comum: [{"message": "..."}, ...] — achata para uma linha só.
        $parts = [];

        foreach ($message as $entry) {
            $text = is_array($entry) ? data_get($entry, 'message') : $entry;

            if (is_scalar($text) && trim((string) $text) !== '') {
                $parts[] = trim((string) $text);
            }
        }

        return $parts === [] ? null : self::truncate(implode(' | ', $parts));
    }

    private static function truncate(string $message): string
    {
        return mb_strimwidth($message, 0, 300, '…');
    }
}
