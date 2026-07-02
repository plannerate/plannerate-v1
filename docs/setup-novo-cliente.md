# Setup de Novo Cliente — Passo a Passo

> Guia operacional para provisionar um novo cliente (tenant) no Plannerate do zero.
> Escrito para servir de roteiro de vídeo: cada passo tem **o que fazer**, **como fazer** e um **check** para confirmar que deu certo antes de avançar.

## Como usar este guia

- Siga os passos **na ordem**. Cada passo depende do anterior.
- Ao final de cada passo, confira o bloco **✅ Como verificar** antes de seguir.
- Marque o checkbox `[ ]` → `[x]` conforme for concluindo.

## Visão geral (checklist)

| # | Passo | Obrigatório? | O que garante |
|---|---|---|---|
| 1 | [Cadastrar o Tenant](#passo-1--cadastrar-o-tenant) | Sim | Conta do cliente + domínio + plano + módulos |
| 2 | [Provisionar o Ambiente](#passo-2--provisionar-o-ambiente-setup-do-tenant) | Sim (automático) | Banco de dados criado e migrations rodadas |
| 3 | [Cadastrar Usuários (Acessos)](#passo-3--cadastrar-os-usuários-do-cliente-acessos) | Sim | Ao menos o **Tenant Admin** para acessar |
| 4 | [Integração de API](#passo-4--configurar-a-integração-de-api) | Depende | Importação de produtos/vendas do ERP do cliente |
| 5 | [Padrão de Gôndola](#passo-5--padrão-de-gôndola-do-tenant) | Opcional | Dimensões padrão ao criar gôndolas |
| 6 | [Kanban: Etapas do Workflow](#passo-6--kanban-etapas-do-workflow-templates) | Se módulo ativo | Fluxo de aprovação + responsáveis por etapa |
| 7 | [SSO (Login Social)](#passo-7--sso-login-social--oauth) | Opcional | Login via Google/Azure |
| 8 | [Primeiro Acesso do Cliente](#passo-8--primeiro-acesso-do-cliente) | Sim | Cliente entra e valida o ambiente |

> 📋 **Tenha em mãos antes de começar:** nome/slug do cliente, domínio desejado,
> plano e módulos contratados, **dados do Tenant Admin** (nome, e-mail, senha),
> **credenciais da API** do ERP (URL + autenticação) e a lista de **usuários
> responsáveis** por cada etapa do workflow.

---

## Passo 1 — Cadastrar o Tenant

**O que é:** o tenant é a "conta" do cliente dentro do Plannerate. É o registro
central (na base *landlord*) a partir do qual todo o resto é provisionado: banco de
dados próprio, domínio, plano e módulos. Todos os dados do cliente (planogramas,
produtos, lojas, usuários) ficam isolados dentro do banco desse tenant.

**Como fazer:**

1. Acesse o painel de administração de tenants: <https://plannerate.com.br/tenants>
2. Clique em **Novo Tenant** (botão de criação no topo da listagem).
3. Preencha os campos do formulário (ver a **referência abaixo**).
4. Clique em **Salvar**.

> Ao salvar, o sistema cria o registro do tenant + o domínio primário e leva você
> direto para a **tela de Setup/Provisionamento** (Passo 2), onde o banco de dados é
> efetivamente criado. **Salvar aqui ainda não cria o banco** — só o cadastro.

### Referência dos campos

Descrição de cada campo com base no código (`StoreTenantRequest`, `TenantController`,
model `Tenant`, `ProvisionTenantDatabaseJob`):

| Campo | Obrigatório | O que faz / regras |
|---|---|---|
| **Nome** | Sim | Nome de exibição do cliente/rede (ex.: `Alfa`). Texto livre, até 255 caracteres. É só rótulo — não afeta banco nem domínio. |
| **Slug** | Sim (auto) | Identificador único do tenant. Se deixado em branco, é **gerado automaticamente a partir do Nome** (`Str::slug`), então `Alfa` → `alfa`. Só minúsculas, números e hífen. Precisa ser **único** entre todos os tenants. Usado internamente para identificar o cliente e como base do nome do banco. |
| **Banco de dados** | Sim (auto) | Nome do **banco de dados físico** que será criado para o cliente. Preenchido automaticamente como `tenant_<slug>` (hífens viram `_`), ex.: slug `alfa` → `tenant_alfa`. Só aceita `A–Z`, `0–9` e `_` (até 64 caracteres) e precisa ser único. No Passo 2, o job de provisionamento cria de fato este banco e roda todas as migrations dentro dele. |
| **Status** | Sim | Estado do tenant. Valores: **Provisionando** (`provisioning`), **Ativo** (`active`), **Suspenso** (`suspended`), **Inativo** (`inactive`). Para um cliente novo, deixe em **Provisionando** — ao concluir o provisionamento no Passo 2, o sistema muda sozinho para **Ativo**. `Suspenso`/`Inativo` servem para bloquear o acesso depois. |
| **Plano** | Não | Plano comercial associado (ex.: `Proplanner`). Define limites do tenant — em especial o **limite de usuários com papel *tenant-admin*** (`user_limit` do plano). Pode ficar vazio, mas o recomendado é já selecionar o plano do contrato. |
| **Módulos** | Não | Funcionalidades liberadas para este cliente. Cada módulo marcado é vinculado ao tenant (tabela `tenant_modules`) e **libera as rotas/telas correspondentes** (rotas protegidas exigem o módulo ativo). Opções: **Banco de Imagens** (`image-bank`), **Kanban** (`kanban`), **Planograma Automático** (`planogram-automatic`), **Planograma template** (`planogram-template`). Marque conforme o que o cliente contratou. |
| **Domínio primário** | Sim | Host pelo qual o cliente acessa o sistema (ex.: `alfa.plannerate.com.br`). É por este domínio que o Plannerate identifica de qual tenant é a requisição. Precisa ser **único**. Ao salvar, cria um registro de domínio do tipo `subdomain`, marcado como primário. |
| **Domínio primário ativo** | Não (padrão: sim) | Se marcado, o domínio já entra **ativo** e o acesso funciona de imediato. Desmarque apenas se quiser cadastrar o domínio mas só liberá-lo depois (ex.: enquanto o DNS/Cloudflare ainda não propagou). |

> ℹ️ Você **não** preenche `tenant_id` em lugar nenhum — o isolamento é automático.
> **Slug** e **Banco de dados** são preenchidos sozinhos ao digitar o Nome; só
> ajuste se tiver um motivo específico.

**✅ Como verificar:**

- Após **Salvar**, você é redirecionado para a **tela de Setup** do tenant (isso já
  confirma que o cadastro deu certo).
- Voltando à listagem <https://plannerate.com.br/tenants>, o novo tenant aparece com
  o **Nome**, o **slug**, o **domínio primário** e o status **Provisionando**.
- O nome do banco ficou no formato `tenant_<slug>` (ex.: `tenant_alfa`).

- [ ] **Passo 1 concluído** — Tenant cadastrado, visível na listagem, status *Provisionando*.

---

## Passo 2 — Provisionar o Ambiente (Setup do tenant)

**O que é:** é aqui que o ambiente do cliente é **criado de fato** — o banco de dados
próprio do tenant e todas as tabelas (migrations). Você chega nesta tela
automaticamente depois de salvar o Passo 1.

**Como funciona (importante):** o provisionamento é **automático**. Assim que o
tenant é criado, o sistema dispara sozinho o job em segundo plano
(`ProvisionTenantDatabaseJob`, via `TenantObserver`). Esta tela apenas **acompanha**
o progresso e **atualiza sozinha a cada 3 segundos** (polling) enquanto o status for
*Provisionando*. Você normalmente **não precisa clicar em nada** — só aguardar.

**As 3 etapas do provisionamento** (mostradas no rodapé da tela):

1. **Criar banco de dados MySQL** — cria o banco `tenant_<slug>` (ex.: `tenant_copacol`).
2. **Executar migrations do tenant** — cria todas as tabelas dentro desse banco.
3. **Ativar ambiente** — marca o tenant como **Ativo** e grava a data em *Provisionado em*.

**Como fazer:**

1. Aguarde na tela de Setup. Enquanto processa, o topo mostra um ícone girando e a
   mensagem *"Criando banco de dados e rodando migrations..."*.
2. Quando terminar, o banner fica **verde**: *"Tenant ativo e pronto para uso"*, com a
   data em *Provisionado em ...*, e o status muda para **Active**.
3. **Se der erro:** o banner fica **vermelho** com a mensagem do erro e aparece o botão
   **Tentar novamente** — clique nele para reprocessar. (O mesmo botão aparece como
   **Provisionar agora** caso o provisionamento não tenha iniciado.)

**Os cards de resumo** confirmam o que foi provisionado:

| Card | O que confirma |
|---|---|
| **Status** | Deve estar **Active** ao final. |
| **Banco de dados** | Nome do banco criado (ex.: `tenant_copacol`). |
| **Domínio** | Host de acesso + selo **Ativo** se o domínio estiver liberado. |
| **Plano** | Plano vinculado (ex.: `Proplanner`). |

**✅ Como verificar:**

- Banner **verde** com *"Tenant ativo e pronto para uso"* e a data em *Provisionado em*.
- Card **Status** = **Active** e as **3 etapas** marcadas com o ✓ verde.
- O botão muda para **Gerenciar usuários** (é por ele que segue o próximo passo).

- [ ] **Passo 2 concluído** — Ambiente provisionado, status *Active*, 3 etapas concluídas.

---

## Passo 3 — Cadastrar os Usuários do Cliente (Acessos)

**O que é:** cria os usuários que vão realmente **acessar o sistema** dentro do tenant.
No mínimo, cadastre o **administrador do tenant** (Tenant Admin) — é ele quem depois
gerencia os demais usuários e as configurações do próprio cliente.

> ⚠️ **Pré-requisito deste passo:** ter em mãos os **dados do(s) usuário(s) do cliente**
> — pelo menos o **admin do tenant**: **nome**, **e-mail** (será o login) e a **senha
> inicial**. Sem isso, não dá para concluir o cadastro. Combine com o cliente qual
> e-mail será o administrador antes de começar.

**Como chegar:** na listagem de tenants, clique no botão **Acessos** da linha do
cliente (ou, logo após provisionar, no botão **Gerenciar usuários** da tela de Setup).

**Como fazer:**

1. Na tela **Acessos do tenant**, clique em **+ Novo usuário** (abre um painel lateral).
2. Preencha os campos (ver a **referência abaixo**).
3. Marque o **perfil de acesso** — para o administrador, marque **Tenant Admin**.
4. Deixe **Ativo** marcado.
5. Clique em **Salvar**.

### Referência dos campos

Com base no código (`TenantUserAccessController`, validação e perfis RBAC do tenant):

| Campo | Obrigatório | O que faz / regras |
|---|---|---|
| **Nome** | Sim | Nome do usuário (até 255 caracteres). |
| **E-mail** | Sim | É o **login** do usuário. Precisa ser um e-mail válido e **único dentro do tenant**. |
| **Senha** | Sim | Senha inicial de acesso. **Mínimo 8 caracteres**. (Na edição de um usuário existente, pode deixar em branco para manter a atual.) |
| **Confirmação de senha** | Sim | Deve ser **igual** à senha. |
| **Perfis de acesso** | Recomendado | Define o que o usuário pode fazer. Pode marcar mais de um. Opções: **Cliente**, **Gestor dos planogramas** e **Tenant Admin**. Para o administrador do cliente, marque **Tenant Admin**. |
| **Status (Ativo)** | Padrão: sim | Se marcado, o usuário já pode acessar. Desmarque para deixar o acesso bloqueado. |

**Perfis de acesso (o que cada um significa):**

- **Tenant Admin** — administrador do cliente; acesso total à gestão do tenant. **É o único perfil que conta no limite de administradores do plano.**
- **Gestor dos planogramas** — foco na montagem/gestão de planogramas.
- **Cliente** — perfil de acesso mais restrito.

> 📊 **Limite do plano:** o card **"Limite do plano — Administradores"** mostra quantos
> **Tenant Admin** já foram usados (ex.: *1 de 4*). Ao atingir o limite, o sistema
> **bloqueia** criar/promover novos administradores. Usuários com outros perfis
> (Cliente, Gestor) **não** consomem esse limite.

**✅ Como verificar:**

- O novo usuário aparece na lista **Acessos do tenant** com o selo **Ativo**.
- O contador **Usuários** aumenta e o card de **limite de administradores** reflete o
  novo Tenant Admin (ex.: passa de *0 de 4* para *1 de 4*).
- O e-mail e os perfis marcados aparecem corretos no card do usuário.

- [ ] **Passo 3 concluído** — Ao menos o **Tenant Admin** cadastrado, ativo e com o perfil correto.

---

## Passo 4 — Configurar a Integração de API

**O que é:** conecta o Plannerate à **API do ERP/sistema do cliente**, de onde serão
importados os **produtos** e as **vendas**. É por essa configuração que o sistema sabe
qual URL chamar, como se autenticar e como buscar os dados.

> ⚠️ **Pré-requisitos deste passo** (peça ao cliente / ao TI dele):
> - **Tipo de integração** já cadastrado no Plannerate (ex.: *Sysmo*, *GesCooper*).
>   O que aparece no dropdown vem das **APIs de integração ativas** — se o sistema do
>   cliente ainda não estiver na lista, cadastre-o antes em **APIs de integração**.
> - **URL base da API** (ex.: `https://api.cliente.com.br`).
> - **Credenciais de acesso** conforme o tipo de autenticação (usuário/senha, token, etc.).

**Como chegar:** na listagem de tenants, botão **Integração API** da linha do cliente.

**Como fazer:**

1. **Tipo de integração** — selecione o sistema do cliente na lista (obrigatório). A
   lista é dinâmica: **pode ter mais tipos** conforme as APIs cadastradas e ativas.
2. **URL da API** — informe a URL base da API do cliente (obrigatório, precisa ser uma
   URL válida).
3. Configure as abas conforme a API exigir:
   - **Authorization** — o **Tipo de autenticação**: `Sem autenticação`, `Bearer Token`
     (token informado ou *buscar token*) ou `Basic Auth` (usuário/senha).
   - **Headers** — cabeçalhos HTTP enviados em cada requisição.
   - **Params** — parâmetros de query string.
   - **Body** — campos enviados no corpo da requisição.
4. Clique em **Salvar** (o botão **APIs de integração** / salvar no topo).
5. **Testar conexão** — depois de salvar, selecione um **path de teste**, ajuste o
   **Body JSON (opcional)** se necessário e clique em **Executar teste**. O resultado
   mostra o status HTTP e a resposta.
6. **Ativar** — com o teste OK, clique em **Ativar** para ligar a integração.

> ℹ️ **Salvar antes de testar:** os botões **Testar conexão** e **Ativar** só ficam
> disponíveis **depois** que a integração é salva (a tela mostra *"Salve a integração
> para selecionar um path de teste"* enquanto não houver configuração salva).

### Referência dos campos principais

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Tipo de integração** | Sim | Qual API/sistema do cliente será usado. Vem das **APIs de integração ativas** cadastradas — lista dinâmica. |
| **URL da API** | Sim | URL base para onde as requisições são feitas. |
| **Tipo de autenticação** | Não | Como o Plannerate se autentica: `Sem autenticação`, `Bearer Token` (manual ou buscado via login), `Basic Auth`. |
| **Headers / Params / Body** | Não | Cabeçalhos, parâmetros e corpo padrão enviados nas requisições. |
| **Testar conexão (path + Body JSON)** | — | Faz uma chamada real de teste antes de ativar, para validar URL/credenciais. |

📄 **Detalhes técnicos** (mapeamento de campos, paginação, modos de importação):
- [integracoes/cliente-api.md](integracoes/cliente-api.md) — fluxo completo cliente ↔ API
- [integracoes/api-generica-configuracao.md](integracoes/api-generica-configuracao.md) — configuração da API genérica
- [integracoes/modos-de-importacao.md](integracoes/modos-de-importacao.md) — importação full/incremental
- [integracoes/base-ean.md](integracoes/base-ean.md) — classificação automática por EAN

**✅ Como verificar:**

- O **Testar conexão** retorna **HTTP 2xx** (sucesso) com dados na resposta.
- Após **Ativar**, a integração fica marcada como **ativa** (o botão passa a exibir
  *Desativar*).
- Voltando à listagem, o botão **Integração API** do tenant indica a integração
  configurada.

- [ ] **Passo 4 concluído** — Integração salva, conexão testada com sucesso e **ativada**.

---

## Passo 5 — Padrão de Gôndola do Tenant

**O que é:** define as **dimensões e a estrutura padrão** usadas toda vez que uma
gôndola nova é criada para este cliente. É uma conveniência: em vez de digitar tudo a
cada gôndola, o Plannerate já pré-preenche com esses valores.

> ℹ️ **Passo opcional.** Se você **não** configurar nada aqui, o sistema usa o
> **padrão do Plannerate**. Configure quando o cliente tem um padrão físico próprio de
> gôndola (ex.: altura/largura de módulo específicas). O botão **Restaurar padrão
> Plannerate** volta tudo aos valores de fábrica.

**Como chegar:** na listagem de tenants, botão **Padrão de gôndola** da linha do cliente.

**Como fazer:**

1. Ajuste os campos por seção (ver referência abaixo). Todas as medidas são em **cm**.
2. Clique em **Salvar**.

### Referência dos campos

Com base no `UpdateTenantGondolaDefaultsRequest` (espelha a estrutura física da gôndola):

**Geral**

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Localização** | Não | Texto livre de localização padrão (ex.: `Corredor 03`). |
| **Lado do corredor** | Sim | Identifica o lado da gôndola no corredor (ex.: `A`). |
| **Fator de escala** | Sim | Multiplicador de escala visual do editor (número ≥ 1). |
| **Fluxo** | Sim | Sentido de leitura da gôndola: **Esquerda para direita** ou **Direita para esquerda**. Afeta a ordem dos módulos. |

**Módulo** (o "vão" da gôndola)

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Altura do módulo (cm)** | Sim | Altura total do módulo. |
| **Largura do módulo (cm)** | Sim | Largura de cada módulo. |
| **Número de módulos** | Sim | Quantos módulos a gôndola terá (inteiro ≥ 1). |

**Base** (o rodapé/base física)

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Altura da base (cm)** | Sim | Altura da base. |
| **Largura da base (cm)** | Sim | Largura da base. |
| **Profundidade da base (cm)** | Sim | Profundidade da base. |

**Cremalheira** (a coluna com furos onde as prateleiras encaixam)

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Largura da cremalheira (cm)** | Sim | Largura da coluna de sustentação. |
| **Altura do furo (cm)** | Sim | Altura de cada furo. |
| **Largura do furo (cm)** | Sim | Largura de cada furo. |
| **Espaçamento vertical dos furos (cm)** | Sim | Distância entre furos (define os "níveis" onde a prateleira encaixa). |

**Prateleiras**

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Espessura da prateleira (cm)** | Sim | Espessura física da prateleira. |
| **Largura da prateleira (cm)** | Sim | Largura da prateleira. |
| **Profundidade da prateleira (cm)** | Sim | Profundidade da prateleira. |
| **Número de prateleiras** | Sim | Quantidade padrão de prateleiras por módulo (inteiro ≥ 0). |
| **Tipo de produto padrão** | Sim | Como os produtos são dispostos por padrão: **Normal** (na prateleira) ou **Gancho/Hook** (pendurado). |

**✅ Como verificar:**

- Após **Salvar**, os valores permanecem preenchidos ao reabrir a tela.
- Ao criar uma **nova gôndola** para este tenant, os campos já vêm pré-preenchidos com
  esses padrões.
- (Opcional) **Restaurar padrão Plannerate** retorna aos valores de fábrica.

- [ ] **Passo 5 concluído** — Padrão de gôndola configurado (ou mantido o padrão Plannerate).

---

## Passo 6 — Kanban: Etapas do Workflow (Templates)

**O que é:** define as **etapas do fluxo de aprovação** (workflow kanban) por onde cada
planograma do cliente vai passar — ex.: *Criação do planograma → Revisão de imagens →
Revisão de dimensões → Aprovação comercial → Aprovação da área de GC → Execução loja →
Revisão periódica*. Cada etapa tem responsáveis sugeridos, duração estimada e ordem.

> ⚠️ **Este passo só existe se o módulo *Kanban* estiver ativo** para o tenant (marcado
> no Passo 1). O botão **Kanban** só aparece na listagem quando o módulo está ligado.

> 🔑 **Pré-requisito fundamental:** os **usuários responsáveis por cada etapa precisam
> já estar cadastrados** no tenant (Passo 3). A lista de **Usuários sugeridos** de cada
> etapa é montada a partir dos usuários existentes — quem não estiver cadastrado **não
> aparece** para ser vinculado. Cadastre todos os responsáveis **antes** de montar as
> etapas.

**Como chegar:** na listagem de tenants, botão **Kanban** da linha do cliente.

**Como fazer:**

Você tem dois caminhos:

- **Opção A — Criar templates padrão (recomendado):** com o tenant ainda sem etapas,
  clique em **Criar templates padrão**. O sistema cria de uma vez as **7 etapas
  padrão** (todas *Obrigatórias*, já **Publicadas** e **encadeadas na ordem** correta).
  Depois é só ajustar os **usuários sugeridos** de cada uma.
- **Opção B — Nova etapa (manual):** clique em **+ Nova etapa** e preencha os campos
  (ver referência abaixo) para criar/editar etapa por etapa.

Em qualquer caminho, **abra cada etapa e marque os usuários responsáveis** na seção
**Usuários sugeridos**.

### Etapas padrão criadas (Opção A)

1. **Criação do planograma** — 2d, obrigatória
2. **Revisão de imagens** — 1d, obrigatória
3. **Revisão de dimensões** — 1d, obrigatória
4. **Aprovação comercial** — 2d, obrigatória
5. **Aprovação da área de GC** — 2d, obrigatória
6. **Execução loja** — 1d, obrigatória
7. **Revisão periódica** — 1d, obrigatória

### Referência dos campos (Nova etapa / Editar)

Com base no `WorkflowTemplateStoreRequest` e no `WorkflowTemplateController`:

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Nome** | Sim | Nome da etapa (ex.: `Aprovação comercial`). |
| **Descrição** | Não | O que deve ser feito nesta etapa. |
| **Ordem sugerida** | Não | Posição da etapa no fluxo (ordena os cards). |
| **Duração estimada (dias)** | Não | Prazo previsto da etapa (aparece como `Xd estimados`). |
| **Obrigatória por padrão** | Não | Se a etapa é obrigatória no fluxo (badge *Obrigatória*). |
| **Cor / Ícone** | Não | Identidade visual do card da etapa. |
| **Etapa anterior / próxima** | Não | Encadeia as etapas em sequência (a Opção A já preenche isso). |
| **Status** | Sim | **Rascunho** (`draft`) ou **Publicado** (`published`). Só etapas publicadas valem no fluxo. |
| **Usuários sugeridos** | Recomendado | Responsáveis pela etapa. **Só lista usuários já cadastrados no tenant.** |

### Perfis de acesso por etapa (pré-criados)

O sistema já traz um **perfil (role) pronto para cada etapa** do kanban, criado pelo
seeder `LandlordKanbanStageRolesSeeder`. São perfis do tipo *tenant* (aparecem na lista
**Perfis de acesso** ao cadastrar usuários — Passo 3). Atribuir o perfil da etapa ao
usuário já concede as permissões coerentes com aquela função:

| Perfil (= nome da etapa) | Permissões principais (além da base¹) |
|---|---|
| **Criação do planograma** | Criar/editar planograma e gôndola, autogerar, ver produtos, iniciar fluxo |
| **Revisão de imagens** | Editar planograma/gôndola, ver/editar produtos |
| **Revisão de dimensões** | Editar planograma/gôndola, ver/editar dimensões |
| **Aprovação comercial** | Ver vendas (só leitura + mover card) |
| **Aprovação da área de GC** | Ver categorias (só leitura + mover card) |
| **Execução loja** | Ver lojas (só leitura + mover card) |
| **Revisão periódica** | Ver vendas, iniciar novo ciclo |

> ¹ **Base comum a todos:** ver dashboard, ver kanban, **mover card** na sua etapa e
> **abrir o planograma em leitura** (inclui os **templates de planograma**).

> ℹ️ **Sobre `/planogram-templates`:** os templates de planograma têm **permissões
> próprias** (`tenant.planogram-templates.viewAny/view/create/update/delete`), separadas
> das de planograma, via `PlanogramTemplatePolicy`. Como **criar template é tarefa de
> especialista**, apenas o **Tenant Admin** e o perfil **Criação do planograma** recebem
> `create/update/delete`; os demais perfis do kanban têm só **visualização**
> (`viewAny/view`, incluída na base comum).

**✅ Como verificar:**

- As etapas aparecem como cards, na ordem definida, com o badge **Publicado**.
- Cada etapa tem, na seção **Usuários sugeridos**, os responsáveis **marcados**
  (nenhuma etapa crítica deve ficar sem responsável).
- O contador no topo confirma o total (ex.: *Exibindo 7 etapas*).

- [ ] **Passo 6 concluído** — Etapas do workflow criadas, publicadas e com responsáveis atribuídos.

---

## Passo 7 — SSO (Login Social / OAuth)

**O que é:** permite que os usuários do cliente entrem no Plannerate usando **login
social (OAuth)** — **Google** ou **Azure/Microsoft** — em vez de senha própria. É
configurado por um **drawer** (painel lateral) que abre a partir do botão **SSO** na
listagem de tenants.

> ℹ️ **Passo opcional.** Configure apenas se o cliente for usar login único (SSO).
> Sem isso, os usuários entram normalmente com e-mail e senha (Passo 3).

> ⚠️ **Pré-requisito:** ter as **credenciais OAuth** criadas no provedor do cliente
> (Console do Google Cloud ou Azure AD): **Client ID** e **Client Secret**. Para
> **Azure**, também o **Tenant ID (Directory)**.

**Como chegar:** na listagem de tenants, botão **SSO** da linha do cliente (abre o
drawer *"SSO — {nome do tenant}"*).

**Como fazer:**

1. **Tipo de provider** — selecione **Google** ou **Azure**.
2. **Label** (opcional) — rótulo exibido no botão de login (ex.: `Google Workspace`).
3. Preencha **Client ID** e **Client Secret** das credenciais OAuth do cliente.
4. Se for **Azure**, informe também o **Tenant ID** (identificador do diretório).
5. Deixe **Ativo** marcado para habilitar o login social.
6. Clique em **Salvar**.

### Referência dos campos

Com base no `TenantSocialiteProviderController`:

| Campo | Obrigatório | O que faz |
|---|---|---|
| **Tipo de provider** | Sim | Provedor OAuth: **Google** ou **Azure** (Microsoft). |
| **Label** | Não | Texto do botão de login social (até 100 caracteres). |
| **Client ID** | Sim | Identificador público da aplicação OAuth. |
| **Client Secret** | Condicional | Segredo da aplicação OAuth. **Na edição, pode deixar em branco para manter o atual** — só preencha para trocar. |
| **Tenant ID (Azure)** | Só p/ Azure | Identificador do diretório (aplicável quando o provider é Azure). |
| **Status (Ativo)** | Padrão: sim | Liga/desliga o login social para este tenant. |

> 🔒 **Segredo preservado:** ao editar, se você **não** digitar um novo *Client Secret*,
> o valor já salvo é mantido (o campo não é sobrescrito com vazio).

**✅ Como verificar:**

- Após **Salvar**, ao reabrir o drawer **SSO**, o provider e o Client ID aparecem
  preenchidos e o status **Ativo**.
- Na tela de login do domínio do cliente, aparece o botão de login social do provedor
  configurado.

- [ ] **Passo 7 concluído** — SSO configurado e ativo (ou dispensado, se o cliente não usa login social).

---

## Passo 8 — Primeiro Acesso do Cliente

**O que é:** validação final — o cliente entra no próprio ambiente pela primeira vez,
confirmando que domínio, login e dados estão funcionando. É o "aceite" do setup.

> ⚠️ **Pré-requisitos:** o tenant precisa estar **Ativo** (Passo 2), com o **domínio
> primário ativo** (Passo 1) e ao menos o **Tenant Admin** cadastrado (Passo 3).

**Como fazer:**

1. Acesse o **domínio primário do cliente** (ex.: `https://alfa.plannerate.com.br`).
2. Faça login com o **Tenant Admin**:
   - **E-mail e senha** cadastrados no Passo 3, ou
   - **Login social (SSO)**, se configurado no Passo 7.
3. Confirme que o ambiente carrega **isolado** (só os dados deste cliente).
4. Entregue as credenciais ao cliente e oriente-o a **trocar a senha** no primeiro acesso.

**✅ Como verificar:**

- O login pelo domínio do cliente funciona (sem erro de tenant/domínio).
- O usuário entra com o perfil **Tenant Admin** e enxerga a gestão do próprio tenant.
- Se houver integração ativa (Passo 4), os **produtos/vendas** começam a aparecer
  conforme a importação roda.
- Se o Kanban estiver ativo (Passo 6), as **etapas do workflow** aparecem configuradas.

- [ ] **Passo 8 concluído** — Cliente acessou o ambiente com sucesso e o setup está validado.

---

## 🎉 Setup concluído

Com os 8 passos concluídos, o cliente está **provisionado, acessível e operacional**.
Resumo do que foi entregue:

- ✅ Tenant criado, com banco próprio, domínio, plano e módulos
- ✅ Ambiente provisionado (banco + migrations) e **Ativo**
- ✅ Usuário administrador (Tenant Admin) cadastrado
- ✅ Integração de API configurada e testada *(quando aplicável)*
- ✅ Padrão de gôndola definido *(quando aplicável)*
- ✅ Etapas do workflow Kanban com responsáveis *(quando aplicável)*
- ✅ SSO configurado *(quando aplicável)*
- ✅ Primeiro acesso do cliente validado
