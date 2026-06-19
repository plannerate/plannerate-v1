<?php

namespace App\Support\Translation;

use Illuminate\Translation\FileLoader;

/**
 * Loader de traduções que mescla, em cada grupo, um diretório homônimo.
 *
 * Além do arquivo padrão lang/{locale}/{group}.php, mescla recursivamente os
 * arquivos em lang/{locale}/{group}/ — cada arquivo .php vira uma chave e cada
 * subdiretório vira um nível aninhado. Permite dividir traduções grandes em
 * vários arquivos (inclusive um por recurso CRUD) preservando a notação de
 * ponto nativa do Laravel (ex.: __('app.tenant.products.navigation')) tanto no
 * backend quanto nas props compartilhadas com o frontend.
 */
class MergingFileLoader extends FileLoader
{
    /**
     * {@inheritDoc}
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = parent::load($locale, $group, $namespace);

        // Apenas para o namespace padrão (arquivos da própria aplicação),
        // não para namespaces de pacotes (package::group).
        if (is_null($namespace) || $namespace === '*') {
            foreach ($this->paths as $path) {
                $directory = "{$path}/{$locale}/{$group}";

                if (is_dir($directory)) {
                    $lines = array_replace_recursive($lines, $this->loadDirectory($directory));
                }
            }
        }

        return $lines;
    }

    /**
     * Carrega recursivamente um diretório de traduções, aninhando cada arquivo
     * .php pela sua key (nome do arquivo) e cada subdiretório por seu nome.
     *
     * @return array<string, mixed>
     */
    protected function loadDirectory(string $directory): array
    {
        $result = [];

        foreach (glob($directory.'/*.php') as $file) {
            $result[basename($file, '.php')] = $this->files->getRequire($file);
        }

        foreach (glob($directory.'/*', GLOB_ONLYDIR) as $subdirectory) {
            $result[basename($subdirectory)] = $this->loadDirectory($subdirectory);
        }

        return $result;
    }
}
