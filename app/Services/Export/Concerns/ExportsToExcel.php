<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Services\Export\Concerns;

use Callcocam\LaravelRaptor\Events\ExportCompleted;
use Callcocam\LaravelRaptor\Notifications\ExportCompletedNotification;

trait ExportsToExcel
{
    /**
     * Exportação síncrona: gera o arquivo e retorna o formato de notificação do AbstractController.
     *
     * @param  array<string, mixed>  $filters
     * @return array{notification: array{title: string, text: string, type: string}}
     */
    public function export(array $filters = []): array
    {
        $fileName = $this->getFileNamePrefix().'-'.now()->format('Y-m-d-H-i-s').'.xlsx';
        $filePath = 'exports/'.$fileName;
        $resourceName = $this->getResourceName();
        $modelName = $this->getExportModelName();
        $userId = auth()->id();

        try {
            $totalRows = $this->exportToFile($filters, $filePath, $fileName, $resourceName, $userId);

            if ($userId) {
                $downloadUrl = ExportCompleted::resolveDownloadExportUrl($fileName);
                auth()->user()->notify(new ExportCompletedNotification($fileName, $downloadUrl, $resourceName));
            }

            return $this->successNotification($totalRows);
        } catch (\Throwable $e) {
            report($e);

            return $this->errorNotification($e);
        }
    }

    /**
     * Notifica o usuário e dispara o evento após gerar o arquivo (uso em exportToFile pelo Job).
     */
    protected function notifyAndDispatchEvent(
        string $fileName,
        string $filePath,
        string $resourceName,
        int $totalRows,
        int|string|null $userId,
        string $modelName,
        bool $fromJob = true
    ): void {
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                $downloadUrl = ExportCompleted::resolveDownloadExportUrl($fileName);
                $user->notify(new ExportCompletedNotification(
                    $fileName,
                    $downloadUrl,
                    $resourceName,
                    $fromJob
                ));
            }
        }

        event(new ExportCompleted(
            userId: $userId,
            modelName: $modelName,
            totalRows: $totalRows,
            filePath: $filePath,
            fileName: $fileName
        ));
    }

    /** @return array{notification: array{title: string, text: string, type: string}} */
    protected function successNotification(int $totalRows): array
    {
        return [
            'notification' => [
                'title' => 'Exportação concluída',
                'text' => "Exportação concluída: {$totalRows} registro(s). Verifique suas notificações para download.",
                'type' => 'success',
            ],
        ];
    }

    /** @return array{notification: array{title: string, text: string, type: string}} */
    protected function errorNotification(\Throwable $e): array
    {
        return [
            'notification' => [
                'title' => 'Erro na exportação',
                'text' => $e->getMessage(),
                'type' => 'error',
            ],
        ];
    }

    /** Prefixo do nome do arquivo (ex.: 'categorias', 'produtos'). */
    abstract protected function getFileNamePrefix(): string;

    /** Nome do recurso para notificação (ex.: 'Categorias', 'Produtos'). */
    abstract protected function getResourceName(): string;

    /** Nome do model para o evento (ex.: 'Category', 'Product'). */
    abstract protected function getExportModelName(): string;

    /**
     * Gera o arquivo Excel e notifica (implementado pelo service concreto).
     *
     * @param  array<string, mixed>  $filters
     * @return int Total de linhas exportadas
     */
    abstract public function exportToFile(
        array $filters,
        string $filePath,
        string $fileName,
        string $resourceName,
        int|string|null $userId
    ): int;
}
