# Import Legacy Simplified - Documentação

## Objetivo
Criar uma versão simplificada do ImportLegacyCommand para migrar dados essenciais do sistema legado MySQL para PostgreSQL multi-tenant.

## Arquitetura de Dados

### Tabelas Base (Banco Principal)
Estas tabelas ficam no banco principal e são comuns a todos os tenants:

- `tenants` - Tenants do sistema
- `users` - Usuários do sistema  
- `clients` - Clientes (cada um pode ter seu próprio banco)
- `client_integrations` - Integrações dos clientes
- `stores` - Lojas dos clientes
- `clusters` - Clusters de produtos
- `addresses` - Endereços
- `roles` - Roles do sistema
- `permission_role` - Relacionamento permissão-role
- `permission_user` - Relacionamento permissão-usuário
- `tenant_users` - Relacionamento tenant-usuário
- `role_user` - Relacionamento role-usuário
- `workflow_step_templates` - Templates de workflow
- `planogram_workflow_steps` - Steps de workflow de planogramas
- `user_workflow_step_template` - Relacionamento usuário-workflow
- `inspirations` - Inspirações/referências

### Tabelas por Client (Bancos Separados)
Estas tabelas ficam em bancos separados por client:

- `planograms` - **TEM client_id** - Tabela principal
- `gondolas` - Filha de planograms
- `store_maps` - Mapas de loja
- `store_map_gondolas` - Gôndolas dos mapas
- `gondola_zones` - Zonas das gôndolas
- `sections` - Seções das gôndolas
- `shelves` - Prateleiras das seções
- `segments` - Segmentos das prateleiras
- `layers` - Camadas dos segmentos

## Fluxo de Importação

### Fase 1: Importação Base
1. Importar tabelas base no banco principal
2. Identificar todos os clients disponíveis

### Fase 2: Importação por Client
Para cada client encontrado:
1. Configurar conexão para o banco específico do client
2. Importar `planograms` (filtrando por client_id)
3. Importar tabelas filhas baseadas nos planograms importados:
   - `gondolas` → relacionadas aos planograms
   - `store_maps` → relacionadas ao client
   - `store_map_gondolas` → relacionadas aos store_maps
   - `gondola_zones` → relacionadas às gondolas
   - `sections` → relacionadas às gondolas
   - `shelves` → relacionadas às sections
   - `segments` → relacionadas às shelves
   - `layers` → relacionadas aos segments

## Considerações Técnicas

### Relacionamentos
- `planograms.client_id` → Filtro principal
- `gondolas.planogram_id` → FK para planograms
- `sections.gondola_id` → FK para gondolas
- `shelves.section_id` → FK para sections
- `segments.shelf_id` → FK para shelves
- `layers.segment_id` → FK para segments

### Bancos de Destino
- **Banco Principal**: Usa conexão default ou tenant configurado
- **Banco do Client**: Usa `client.database` para configurar conexão dinâmica

### Ordem de Importação
1. Estrutura hierárquica respeitada (pai antes dos filhos)
2. Dentro de cada client, importar planograms primeiro
3. Depois importar tabelas dependentes em ordem de dependência

## Próximos Passos

1. Implementar comando simplificado
2. Testar importação com um client específico
3. Validar integridade dos relacionamentos
4. Implementar importação em lote para todos os clients