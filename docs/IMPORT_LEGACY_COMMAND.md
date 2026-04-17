# Comando de Importação Legacy Simplificado

Este documento descreve como usar o comando `import:legacy-simplified` para migrar dados do sistema legado MySQL para o novo sistema multi-tenant PostgreSQL.

## Visão Geral

O comando funciona em duas fases com modo "diff" inteligente:
1. **Fase 1**: Importa tabelas base para o banco principal
2. **Fase 2**: Importa dados específicos dos clientes para bancos separados

## Uso Básico

```bash
# Importar tudo (tabelas base + dados de todos os clientes)
./vendor/bin/sail artisan import:legacy-simplified

# Modo dry-run (mostra o que seria importado sem executar)
./vendor/bin/sail artisan import:legacy-simplified --dry-run

# Importar apenas um cliente específico
./vendor/bin/sail artisan import:legacy-simplified --client=01kbr0jmjzk0n5vdjyxd3q3nd0

# Importar apenas uma tabela específica
./vendor/bin/sail artisan import:legacy-simplified --table=planograms

# Importar tabela específica para cliente específico
./vendor/bin/sail artisan import:legacy-simplified --table=planograms --client=coasgo

# Listar tabelas disponíveis
./vendor/bin/sail artisan import:legacy-simplified --show-tables
```

## Opções Disponíveis

### `--client=ID_OR_SLUG`
- Importa dados apenas do cliente especificado
- Aceita ID do cliente (ULID) ou slug
- Exemplo: `--client=01kbr0jmjzk0n5vdjyxd3q3nd0` ou `--client=coasgo`

### `--table=TABLE_NAME`
- Importa apenas a tabela especificada
- Pode ser combinado com `--client` para tabelas de cliente
- Exemplo: `--table=planograms`

### `--dry-run`
- Mostra o que seria importado sem executar a importação
- Útil para verificar quantos registros serão processados

### `--chunk=NUMBER`
- Define o tamanho do lote para processamento (padrão: 1000)

### `--show-tables`
- Lista todas as tabelas disponíveis com contagens

### Modo Diff
- O comando usa "modo diff" por padrão, importando apenas registros novos
- Compara IDs existentes para evitar duplicações
- Para tabelas pivot (sem coluna `id`), compara contagens totais

## Sintaxe do Comando

```bash
./vendor/bin/sail artisan import:legacy-simplified [opções]
```

## Opções Disponíveis

### `--client=ID_OU_SLUG`
Importa dados apenas de um cliente específico.

```bash
# Por ID do cliente
./vendor/bin/sail artisan import:legacy-simplified --client=01k1xx8evcx6pygzkwax3wrpfm

# Por slug do cliente  
./vendor/bin/sail artisan import:legacy-simplified --client=franciosi
```

### `--table=NOME_TABELA`
Importa apenas uma tabela específica (útil para testes ou correções pontuais).

```bash
# Importar apenas planogramas
./vendor/bin/sail artisan import:legacy-simplified --table=planograms

# Importar apenas gondolas de um cliente específico
./vendor/bin/sail artisan import:legacy-simplified --client=coasgo --table=gondolas
```

### `--dry-run`
Mostra o que seria importado sem executar as operações.

```bash
./vendor/bin/sail artisan import:legacy-simplified --dry-run
```

### `--chunk=TAMANHO`
Define o tamanho do lote para processamento (padrão: 1000).

```bash
./vendor/bin/sail artisan import:legacy-simplified --chunk=500
```

### `--show-tables`
Lista todas as tabelas disponíveis no banco legado com contagens.

```bash
./vendor/bin/sail artisan import:legacy-simplified --show-tables
```

## Exemplos de Uso

### 1. Importação Completa (Primeira Execução)
```bash
./vendor/bin/sail artisan import:legacy-simplified
```

### 2. Verificar o que Seria Importado
```bash
./vendor/bin/sail artisan import:legacy-simplified --dry-run
```

### 3. Importar Apenas um Cliente
```bash
./vendor/bin/sail artisan import:legacy-simplified --client=coasgo
```

### 4. Verificar Problema com Planogramas da Coasgo
```bash
# Primeiro, verificar se existem planogramas no banco legado
./vendor/bin/sail artisan import:legacy-simplified --show-tables

# Verificar quantos seriam importados em dry-run
./vendor/bin/sail artisan import:legacy-simplified --client=coasgo --table=planograms --dry-run

# Forçar importação apenas dos planogramas
./vendor/bin/sail artisan import:legacy-simplified --client=coasgo --table=planograms
```

### 5. Verificar Tabela Específica
```bash
./vendor/bin/sail artisan import:legacy-simplified --table=gondolas --dry-run
```

## Estrutura das Tabelas

### Tabelas Base (Banco Principal)
- `tenants` - Inquilinos do sistema
- `users` - Usuários
- `clients` - Clientes
- `client_integrations` - Integrações dos clientes
- `stores` - Lojas
- `clusters` - Agrupamentos
- `addresses` - Endereços
- `roles` - Perfis de usuário
- `permission_role` - Permissões por perfil
- `permission_user` - Permissões por usuário
- `tenant_users` - Usuários por inquilino
- `role_user` - Perfis por usuário
- `workflow_step_templates` - Templates de workflow
- `planogram_workflow_steps` - Etapas de workflow de planogramas
- `user_workflow_step_template` - Templates por usuário

### Tabelas de Cliente (Bancos Específicos)
- `planograms` - Planogramas (ponto de entrada - tem `client_id`)
- `gondolas` - Gôndolas (filhos de planogramas)
- `store_maps` - Mapas de loja
- `store_map_gondolas` - Gôndolas dos mapas
- `gondola_zones` - Zonas das gôndolas
- `sections` - Seções (filhos de gôndolas)
- `shelves` - Prateleiras (filhos de seções)
- `segments` - Segmentos (filhos de prateleiras)
- `layers` - Camadas (filhos de segmentos)

## Hierarquia de Dependências

```
Cliente
├── Planogramas (client_id)
│   └── Gôndolas (planogram_id)
│       ├── Zonas (gondola_id)
│       └── Seções (gondola_id)
│           └── Prateleiras (section_id)
│               └── Segmentos (shelf_id)
│                   └── Camadas (segment_id)
└── Lojas (client_id)
    └── Mapas de Loja (store_id)
        └── Gôndolas do Mapa (store_map_id)
```

## Solução de Problemas

### Problema: "0 new records" mas dados deviam existir

Isso pode acontecer quando:
1. Os dados já foram importados anteriormente (modo diff funciona corretamente)
2. Há problema no filtro de cliente

**Diagnóstico:**
```bash
# 1. Verificar se existem dados no banco legado
./vendor/bin/sail artisan import:legacy-simplified --show-tables

# 2. Verificar quantos registros existem para o cliente específico
./vendor/bin/sail artisan import:legacy-simplified --client=coasgo --table=planograms --dry-run

# 3. Verificar no banco local se já existem registros
```

**Verificação Manual:**
```sql
-- No banco legado (MySQL)
SELECT COUNT(*) FROM planograms WHERE client_id = '01kbr0jmjzk0n5vdjyxd3q3nd0';

-- No banco local do cliente (PostgreSQL)
\c plannerate_coasgo;
SELECT COUNT(*) FROM planograms;
```

### Problema: Erro de conexão com banco legado

Verificar configuração em `config/database.php`:
```php
'mysql_legacy' => [
    'driver' => 'mysql',
    'host' => env('LEGACY_DB_HOST'),
    'port' => env('LEGACY_DB_PORT', '3306'),
    'database' => env('LEGACY_DB_DATABASE'),
    'username' => env('LEGACY_DB_USERNAME'),
    'password' => env('LEGACY_DB_PASSWORD'),
    // ...
]
```

### Problema: Banco de cliente não existe

O comando oferece criar automaticamente:
```
Client Supermercado Cooasgo has no database. Create 'plannerate_coasgo'? (yes/no) [yes]:
```

Ou criar manualmente:
```bash
./vendor/bin/sail artisan raptor:migrate-tenants
```

## Configuração do Banco Legado

Adicionar no `.env`:
```env
LEGACY_DB_HOST=seu_host_mysql
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=nome_banco_legado
LEGACY_DB_USERNAME=usuario
LEGACY_DB_PASSWORD=senha
```

## Logs e Monitoramento

O comando fornece feedback em tempo real:
- ✅ Sucesso com contagem de registros importados
- ⚠️ Avisos para tabelas não encontradas
- ❌ Erros de conexão ou estrutura
- 📊 Estatísticas em modo dry-run

## Performance

- **Chunk size**: Ajuste conforme memória disponível (padrão: 1000)
- **Conexões**: Usa conexões separadas para source e target
- **Memória**: Processa dados em lotes para evitar estouro de memória
- **Duplicados**: Usa `insertOrIgnore` para tratar duplicações graciosamente

## Segurança

- **Modo diff**: Previne sobrescrita acidental de dados
- **Transações**: Cada chunk é processado em transação separada
- **Validação**: Verifica existência de tabelas antes da importação
- **Rollback**: Falhas em um chunk não afetam chunks anteriores

## Solução de Problemas

### Problema: "0 new records" mas deveria ter dados

**Diagnóstico**: Os dados provavelmente já existem localmente

1. **Verificar dados locais**:
   ```bash
   ./vendor/bin/sail tinker --execute="
   Config::set('database.connections.client_db', array_merge(config('database.connections.pgsql'), ['database' => 'plannerate_coasgo']));
   echo 'Planogramas locais: ' . DB::connection('client_db')->table('planograms')->count() . PHP_EOL;
   "
   ```

2. **Verificar dados remotos**:
   ```bash
   ./vendor/bin/sail artisan import:legacy-simplified --table=planograms --client=coasgo --dry-run
   ```

3. **Comparar IDs para encontrar diferenças**:
   ```bash
   ./vendor/bin/sail tinker --execute="
   // Setup conexões
   Config::set('database.connections.client_db', array_merge(config('database.connections.pgsql'), ['database' => 'plannerate_coasgo']));
   
   // IDs locais vs remotos
   \$localIds = DB::connection('client_db')->table('planograms')->pluck('id')->sort()->values();
   \$remoteIds = DB::connection('mysql_legacy')->table('planograms')->where('client_id', '01kbr0jmjzk0n5vdjyxd3q3nd0')->pluck('id')->sort()->values();
   \$newIds = \$remoteIds->diff(\$localIds);
   
   echo 'Local: ' . \$localIds->count() . ', Remote: ' . \$remoteIds->count() . ', New: ' . \$newIds->count() . PHP_EOL;
   "
   ```

### Forçar Reimportação (com cuidado)

Se necessário deletar e reimportar dados:

```bash
# 1. Deletar dados locais (CUIDADO!)
./vendor/bin/sail tinker --execute="
Config::set('database.connections.client_db', array_merge(config('database.connections.pgsql'), ['database' => 'plannerate_coasgo']));
DB::connection('client_db')->table('planograms')->delete();
"

# 2. Reimportar
./vendor/bin/sail artisan import:legacy-simplified --table=planograms --client=coasgo
```

### Clientes Disponíveis

Para referência, os IDs dos clientes principais:

- **Coasgo**: `01kbr0jmjzk0n5vdjyxd3q3nd0` → `plannerate_coasgo`
- **Franciosi**: `01k1xx8evcx6pygzkwax3wrpfm` → `plannerate_franciosi`  
- **Bruda**: `01k7ez88kzrywyejk3xgvskyhj` → `plannerate_bruda`
- **Michelon**: `01k8vf7dsq3mxm15jxecf3r99s` → `plannerate_michelon`
- **Alberti**: `01jym02qk8n1cwdq2hd5drpgsz` → `plannerate_alberti_supermercado`

### Exemplos de Diagnóstico

```bash
# Ver todas as tabelas e contagens
./vendor/bin/sail artisan import:legacy-simplified --show-tables

# Verificar cliente específico em modo dry-run
./vendor/bin/sail artisan import:legacy-simplified --client=coasgo --dry-run

# Importar apenas planogramas com verbose
./vendor/bin/sail artisan import:legacy-simplified --table=planograms --client=coasgo
```