<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

trait HandlesFileImports
{
    /**
     * Valida se o arquivo foi enviado e é válido
     */
    protected function validateFileUpload(?UploadedFile $file, string $fieldName = 'file'): ?RedirectResponse
    {
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->withErrors([
                $fieldName => 'Arquivo inválido ou não enviado.',
            ]);
        }

        return null;
    }

    /**
     * Valida o tipo MIME do arquivo
     */
    protected function validateFileMimeType(UploadedFile $file, array $allowedMimes, string $fieldName = 'file', string $errorMessage = 'Tipo de arquivo não permitido.'): ?RedirectResponse
    {
        if (! in_array($file->getMimeType(), $allowedMimes)) {
            return redirect()->back()->withErrors([
                $fieldName => $errorMessage,
            ]);
        }

        return null;
    }

    /**
     * Valida arquivo Excel (.xlsx ou .xls)
     */
    protected function validateExcelFile(UploadedFile $file, string $fieldName = 'file'): ?RedirectResponse
    {
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls
        ];

        return $this->validateFileMimeType(
            $file,
            $allowedMimes,
            $fieldName,
            'Arquivo deve ser um Excel (.xlsx ou .xls).'
        );
    }

    /**
     * Garante que o diretório existe com permissões corretas
     */
    protected function ensureDirectoryExists(string $path, int $permissions = 0775): void
    {
        if (! file_exists($path)) {
            mkdir($path, $permissions, true);
            chmod($path, $permissions);
        }
    }

    /**
     * Sanitiza o nome do arquivo removendo caracteres especiais
     */
    protected function sanitizeFileName(string $originalName): string
    {
        // Remove extensão se houver
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Remove caracteres especiais
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        
        // Remove underscores duplicados
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        
        // Remove underscores do início e fim
        return trim($sanitized, '_');
    }

    /**
     * Gera nome único para arquivo de importação
     */
    protected function generateImportFileName(UploadedFile $file, string $prefix = 'import'): string
    {
        $sanitizedName = $this->sanitizeFileName($file->getClientOriginalName());
        $extension = $file->getClientOriginalExtension();
        
        return sprintf(
            '%s_%s_%s.%s',
            $prefix,
            time(),
            $sanitizedName,
            $extension
        );
    }

    /**
     * Salva o arquivo de importação
     */
    protected function saveImportFile(UploadedFile $file, string $prefix = 'import', string $directory = 'imports', string $disk = 'public'): array
    {
        // Garante que o diretório existe
        $fullPath = storage_path("app/{$disk}/{$directory}");
        $this->ensureDirectoryExists($fullPath);

        // Gera nome único e sanitizado
        $fileName = $this->generateImportFileName($file, $prefix);

        // Salva o arquivo
        $filePath = $file->storeAs($directory, $fileName, $disk);

        if (! $filePath) {
            return [
                'success' => false,
                'error' => 'Erro ao salvar o arquivo. Tente novamente.',
            ];
        }

        // Define permissões corretas no arquivo salvo
        $savedFilePath = storage_path("app/{$disk}/{$filePath}");
        if (file_exists($savedFilePath)) {
            chmod($savedFilePath, 0664);
        }

        return [
            'success' => true,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'full_path' => storage_path("app/{$disk}/{$filePath}"),
        ];
    }

    /**
     * Obtém o contexto atual (tenant_id e client_id)
     */
    protected function getCurrentContext(): array
    {
        return [
            'tenant_id' => config('app.current_tenant_id'),
            'client_id' => config('app.current_client_id'),
        ];
    }

    /**
     * Processo completo de validação e salvamento de arquivo Excel
     */
    protected function handleExcelImport(UploadedFile $file, string $fieldName = 'file', string $prefix = 'import'): array|RedirectResponse
    {
        // Valida o upload
        if ($error = $this->validateFileUpload($file, $fieldName)) {
            return $error;
        }

        // Valida o tipo Excel
        if ($error = $this->validateExcelFile($file, $fieldName)) {
            return $error;
        }

        // Salva o arquivo
        $result = $this->saveImportFile($file, $prefix);

        if (! $result['success']) {
            return redirect()->back()->withErrors([
                $fieldName => $result['error'],
            ]);
        }

        // Adiciona contexto ao resultado
        $result['context'] = $this->getCurrentContext();

        return $result;
    }
}
