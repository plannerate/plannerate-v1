Segue uma versão **otimizada para IA de desenvolvimento (Cursor / Copilot / GPT Agent)**. Ela está estruturada para que a ferramenta entenda **contexto, regras de negócio e arquivos envolvidos**, o que aumenta muito a chance de gerar código correto.

---

# Implementar Workflow de Responsabilidade para Planogramas

## Contexto

Existe um sistema de **planogramas** onde cada fluxo de trabalho é composto por **etapas configuráveis**.

As etapas do workflow são definidas em:

`app/Models/Workflow/PlanogramWorkflowConfig.php`

A execução do workflow acontece em:

`app/Models/Workflow/GondolaWorkflowExecution.php`

E o histórico das ações deve ser registrado em:

`app/Models/Workflow/GondolaWorkflowHistory.php`.

O cadastro de planogramas acontece em:

`app/Http/Controllers/Tenant/PlanogramController.php`

Durante o cadastro já é possível definir:

* **role responsável**
* **usuários responsáveis**

Essas informações definem **quem pode assumir e executar cada etapa do workflow**.

---

# Regras de Negócio

## 1. Início do Workflow

Quando um planograma for listado, deve ser verificado se **existe uma execução iniciada** em:

`GondolaWorkflowExecution`.

Se **não existir execução iniciada**:

* apenas usuários que possuam a **role configurada para a etapa inicial** poderão:

  * abrir a modal
  * iniciar o workflow

---

## 2. Iniciar Execução

Quando um usuário iniciar o workflow:

* deve ser criado um registro em `GondolaWorkflowExecution`
* o **usuário logado se torna o responsável pela execução atual**

Registrar:

* `execution_started_by`
* `current_responsible_id`
* `current_step_id`

---

## 3. Responsável da Etapa

Após o workflow ser iniciado:

* **somente o usuário responsável atual** pode mover o workflow para a próxima etapa
* a próxima etapa deve ser definida usando `PlanogramWorkflowConfig`

---

## 4. Avançar Etapa

Quando o responsável mover para a próxima etapa:

* atualizar `current_step_id`
* atualizar `current_responsible_id` (normalmente permanece o mesmo até que alguém desista)

Registrar no histórico:

`GondolaWorkflowHistory`

Exemplo de evento:

* `step_moved`

---

## 5. Desistir da Responsabilidade

O responsável atual pode **desistir da etapa**.

Ao desistir:

* `current_responsible_id` deve ser definido como `null`
* o workflow volta a ficar disponível para **outros usuários elegíveis**

Usuários elegíveis são:

* usuários com a **role da etapa**
* usuários listados como **responsáveis na configuração**

Registrar no histórico:

`responsibility_released`

---

## 6. Assumir Etapa

Quando `current_responsible_id` estiver `null`, qualquer usuário elegível pode **assumir a etapa**.

Ao assumir:

* atualizar `current_responsible_id`
* registrar histórico

Evento:

`responsibility_taken`

---

## 7. Histórico do Workflow

Todas as ações devem ser registradas em:

`GondolaWorkflowHistory`

Eventos esperados:

* `execution_started`
* `step_moved`
* `responsibility_taken`
* `responsibility_released`
* `workflow_completed`

Campos recomendados:

* execution_id
* step_id
* user_id
* action
* metadata (json opcional)

---

# Ajustes Estruturais Necessários

Adicionar campo em:

`app/Models/Workflow/GondolaWorkflowExecution.php`

```php
current_responsible_id
```

Responsável atual da etapa.

Relacionamento sugerido:

```php
public function currentResponsible()
{
    return $this->belongsTo(User::class, 'current_responsible_id');
}
```

---

# Restrições Importantes

* Apenas o **responsável atual** pode mover etapas.
* Apenas usuários **elegíveis** podem iniciar ou assumir etapas.
* Todas as ações devem gerar **registro no histórico**.
* O sistema deve permitir **liberação voluntária da responsabilidade**.
 
