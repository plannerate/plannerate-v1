# Ajustes de desempenho no VS Code com WSL

Data: 2026-05-08

## Objetivo
Reduzir travamentos do VS Code no projeto e minimizar perda de conexão com WSL durante indexação, busca e análise de código.

## O que foi feito

1. Validação de localização do projeto no WSL
- Caminho validado: /home/call/projects/plannerate-v1
- Tipo de filesystem validado: ext4
- Resultado: projeto está no filesystem Linux do WSL (cenário recomendado para desempenho).

2. Ajustes no arquivo de workspace do VS Code
- Arquivo alterado: .vscode/settings.json
- Exclusões aplicadas para reduzir carga de monitoramento e busca em diretórios pesados.
- Ajustes de desempenho aplicados para TypeScript/JavaScript e Git.

3. Atualização de configurações depreciadas
- Chave antiga: typescript.tsserver.maxTsServerMemory
- Chave nova: js/ts.tsserver.maxMemory

- Chave antiga: typescript.disableAutomaticTypeAcquisition
- Chave nova: js/ts.tsserver.automaticTypeAcquisition.enabled

## Configuração final aplicada

### Monitoramento de arquivos
- js/ts.tsserver.watchOptions: vscode
- files.watcherExclude:
  - **/.git/objects/**
  - **/.git/subtree-cache/**
  - **/.hg/store/**
  - **/node_modules/**
  - **/vendor/**
  - **/storage/**
  - **/logs/**
  - **/bootstrap/cache/**

### Exclusões no Explorer
- files.exclude:
  - **/.git
  - **/.svn
  - **/.hg
  - **/.DS_Store
  - **/Thumbs.db
  - **/node_modules
  - **/storage/app/public/repositorioimages/frente
  - **/bootstrap/cache

### Exclusões de busca/indexação
- search.exclude:
  - **/node_modules
  - **/vendor
  - **/storage/app/public/repositorioimages/frente
  - **/logs
  - **/bootstrap/cache
  - **/public/build

### Performance de linguagem e Git
- js/ts.tsserver.maxMemory: 4096
- js/ts.tsserver.automaticTypeAcquisition.enabled: false
- git.autorefresh: false

### Salvamento e formatação
- editor.codeActionsOnSave.source.fixAll.eslint: explicit
- [php].editor.defaultFormatter: me-dutour-mathieu.php-formatter
- [php].editor.formatOnSave: true

## Validações executadas
- JSON do arquivo .vscode/settings.json validado com sucesso via PHP CLI.
- Mensagens de depreciação removidas após troca para as novas chaves js/ts.

## Próximos passos recomendados
1. Executar Developer: Reload Window no VS Code.
2. Monitorar estabilidade por 1 ou 2 ciclos de trabalho.
3. Se ainda houver desconexão com WSL, ajustar recursos no .wslconfig (memória, CPU e swap).
