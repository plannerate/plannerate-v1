# Documentação do Plannerate

Índice da documentação do projeto. Última reorganização: 2026-06-11
(varredura pós-refatoração do pacote raptor-plannerate — obsoletos removidos).

## Núcleo

| Documento | Conteúdo |
|---|---|
| [setup-novo-cliente.md](setup-novo-cliente.md) | Passo a passo para provisionar um novo cliente (tenant) do zero — roteiro de vídeo |
| [PATTERNS.md](PATTERNS.md) | Padrões de código do projeto (controllers, models, Vue, Wayfinder, traduções) |
| [configuracao.md](configuracao.md) | Configurações do Auto-Planograma: pesos de scoring, matriz de adjacência, preferências de nível de prateleira |
| [traducoes-planograma.md](traducoes-planograma.md) | Guia de traduções dos componentes de planograma (`lang/pt_BR/planogram-templates.php`) |
| [multitenancy-validacoes-tenant.md](multitenancy-validacoes-tenant.md) | Validações específicas de tenant no multitenancy Spatie |
| [vscode-wsl-performance.md](vscode-wsl-performance.md) | Dicas de performance VSCode + WSL2 |

## Integrações com APIs de clientes

| Documento | Conteúdo |
|---|---|
| [integracoes/api-generica-configuracao.md](integracoes/api-generica-configuracao.md) | Configuração da integração genérica de API (endpoints, paginação, mapeamento) |
| [integracoes/cliente-api.md](integracoes/cliente-api.md) | Integração cliente-API (fluxo completo) |
| [integracoes/modos-de-importacao.md](integracoes/modos-de-importacao.md) | Modos de importação (full/incremental) |
| [integracoes/base-ean.md](integracoes/base-ean.md) | Base de referência por EAN para classificação automática de produtos |

## Domínio Planograma (no pacote)

Documentação técnica do motor vive junto do código, em
`packages/callcocam/laravel-raptor-plannerate/docs/`:

- `FLUXO-PLANOGRAMA-AUTOMATICO.md` — pipeline completo do modo automático
- `COMPARATIVO-FLUXO-VS-IMPLEMENTACAO.md` — fluxo de negócio × implementação
- `ABC.md` / `BCG.md` / `ESTOQUE-ALVO.md` — as três análises de performance
- `auto-planograma-min-facings.md` — frentes mínimas, overflow pass e pruning de slots

## Refatoração raptor-plannerate (2026-06)

Histórico completo da reconstrução do pacote em
[refatoracao-raptor-plannerate/](refatoracao-raptor-plannerate/):
prompt original, relatórios das fases 1-5, baseline de testes e manifesto de rotas.

## Histórico

[historico/specs/](historico/specs/) — design specs de features já implementadas
(kanban de workflow, banco de imagens, DNS Cloudflare por tenant, componentes de tabela).
Mantidas como referência de design; os planos de execução correspondentes foram removidos
por estarem concluídos.

## Infra (fora de docs/)

- `docker/DOCKER.md` — ambiente Docker local
- `vps-deployment-v2/README.md` — deploy em VPS (+ SECURITY.md, GITHUB-ENVIRONMENTS.md)
- `CLAUDE.md` / `AGENTS.md` — instruções para agentes de IA
- `.claude/dimension-research.md` — pipeline AI de pesquisa de dimensões de produto
- `storage/app/private/prompts/auto-planogram/` — prompts e resumo de sessão do auto-planograma
