[PLAN-145] Criar Tela loja execução loja  Criado: 24/jun/26  Atualizado(a): 26/jun/26
Status:
Prioridade
Projeto:
Plannerate
Componentes:
Nenhum
Versões afetadas:
Nenhum
Versões corrigidas:
Nenhum


Tipo:
Tarefa
Prioridade:
Medium
Relator:
anderson
Responsável:
Não atribuído
Resolução:
Não resolvido(s)
Votos:
0
Categorias:
Nenhum
Estimativa de trabalho restante:
Desconhecido
Tempo gasto:
Desconhecido
Estimativa original:
Desconhecido


Anexos:
 Captura de Tela 2026-06-26 às 00.27.13.png      Captura de Tela 2026-06-26 às 00.27.47.png      Captura de Tela 2026-06-26 às 00.27.32.png      ChatGPT Image 26 de jun. de 2026, 00_12_39 - cópia.png    
Rank:
0|k905n0:


 Descrição 
 


Visualização da mesma forma que temos no adm, lista, kanban, mapa, só deixa abrir na lista e no kanban se estiver na etapa de execução loja, no mapa consegue abrir depois de concluidos.
Função da loja comprovar execução
Comparação da execução com o planograma por IA




Regra de negócio — Execução de Planograma em Loja
1. Objetivo da funcionalidade
A funcionalidade tem como objetivo permitir que a loja execute um planograma aprovado sem alterar sua estrutura original.
Na etapa Execução em Loja, o usuário executor deve conseguir:
visualizar o planograma aprovado;
consultar o fluxo da gôndola;
consultar os dados dos produtos;
adicionar evidências da execução;
apontar divergências encontradas na montagem;
concluir a execução;
manter histórico e rastreabilidade do processo.
A loja não deve editar o planograma. Qualquer alteração estrutural, como troca de produto, alteração de frentes, posição, dimensões, módulos ou layout, deve continuar restrita aos perfis responsáveis pela criação, revisão ou aprovação.
2. Fluxo principal no Kanban
O fluxo principal do Kanban deve seguir a lógica de implantação do planograma.
Etapas principais
Criação do planograma
Revisão de imagens
Revisão de dimensões
Aprovação comercial
Aprovação GC
Execução em Loja
Concluídos
A etapa Revisão Periódica não faz parte do avanço imediato após a execução. Ela é uma regra posterior, baseada no prazo de revisão definido na criação do planograma.
3. Regra de conclusão no Kanban
Quando a loja concluir a execução, o card deve sair da etapa:
Execução em Loja
e avançar para:
Concluídos
Ou seja:
Execução em Loja → Concluídos
O card não deve ir automaticamente para Revisão Periódica.
A etapa Concluídos representa que o ciclo de implantação daquele planograma foi encerrado.
4. Regra da Revisão Periódica
A Revisão Periódica deve ser tratada como um processo posterior à conclusão da execução.
Quando o planograma for criado, deve existir um campo para definir o período de revisão, por exemplo:
revisão a cada 60 dias;
revisão a cada 90 dias;
revisão a cada 120 dias;
ou outro prazo configurável.
Após a execução ser concluída e o card ir para Concluídos, o sistema deve começar a considerar a regra de revisão periódica cadastrada no planograma.
Exemplo
Se o planograma foi concluído em 01/01/2026 e a revisão cadastrada for de 60 dias, o sistema deve gerar ou mover o card para Revisão Periódica apenas quando chegar a data prevista da revisão.
Fluxo correto:
Execução em Loja → Concluídos → aguarda prazo de revisão → Revisão Periódica
A revisão periódica, portanto, não é uma continuação imediata da execução. Ela acontece somente quando o prazo configurado for atingido.
5. Data base para contagem da revisão
A contagem do prazo de revisão periódica deve iniciar a partir da data de conclusão da execução.
Exemplo:
Data de conclusão da execução: 10/01/2026
Frequência de revisão: 60 dias
Próxima revisão: 11/03/2026
O sistema deve armazenar:
data de conclusão da execução;
frequência de revisão cadastrada;
próxima data de revisão;
status da revisão.
6. Comportamento ao atingir a data de revisão
Quando a data de revisão for atingida, o sistema deve mover ou gerar o card na etapa Revisão Periódica.
Existem duas possibilidades técnicas:
Opção 1 — Mover o mesmo card
O mesmo card sai de Concluídos e vai para Revisão Periódica.
Vantagem: mantém o histórico centralizado em um único card.
Opção 2 — Criar uma nova instância de revisão
O card original permanece em Concluídos, e o sistema cria um novo card de revisão periódica vinculado ao planograma original.
Vantagem: separa implantação inicial de ciclos de revisão.
Minha recomendação: criar uma nova instância de revisão periódica vinculada ao planograma original.
Isso preserva o histórico da execução inicial e permite controlar múltiplas revisões ao longo do tempo.
7. Tela principal do planograma em execução
A tela principal deve permanecer limpa e focada na visualização do planograma.
Ela deve conter:
cabeçalho com dados do planograma;
bloco resumido da execução;
fluxo da gôndola acima da prateleira;
imagem do planograma;
botões de visualização;
botões de execução;
área de observações.
Dados exibidos no cabeçalho
código do planograma/gôndola;
status do planograma;
loja/corredor;
categoria;
quantidade de módulos;
data de publicação;
responsável;
fluxo;
versão, se aplicável.
8. Bloco de execução na tela principal
Na tela principal, deve existir um bloco resumido de execução com as seguintes informações:
status da execução;
responsável pela execução;
data/hora de início;
SLA;
quantidade de evidências enviadas;
quantidade de evidências obrigatórias;
quantidade de divergências registradas.
Exemplo:
Status: Em execução
Responsável: Anderson
Iniciado em: 26/05/2026 09:15
SLA: 2 dias restantes
Evidências: 3/6
Divergências: 1 registrada
Esse bloco deve servir apenas como resumo e ponto de ação. As informações detalhadas devem ficar nos modais.
9. Botões principais da execução
Na parte superior direita do bloco de execução, devem existir três botões:
Adicionar evidência
Apontar divergência
Concluir execução
Cada botão deve abrir um modal separado.
Esses botões devem controlar o registro da execução, sem alterar o layout do planograma.
10. Botões já existentes de visualização
Os botões já existentes devem permanecer na tela, separados visualmente dos botões de execução.
Botões de visualização:
zoom;
performance;
colunas;
baixar PDF.
Esses botões não alteram o fluxo de execução. Eles servem apenas para consulta, leitura e apoio operacional.
11. Fluxo da gôndola
O fluxo da gôndola deve aparecer logo acima da imagem da prateleira, pois ele orienta fisicamente a execução.
Ele deve indicar:
início;
sentido do fluxo;
fim.
Exemplo:
Início: Direita
Fluxo: Direita → Esquerda
Fim: Esquerda
Essa informação deve ser somente leitura para o usuário da loja.
12. Início da execução
Quando o planograma entrar na etapa Execução em Loja, ele deve ficar disponível para o usuário executor.
A execução pode ser iniciada de duas formas:
Opção recomendada — início automático
Ao abrir o planograma pela primeira vez na etapa Execução em Loja, o sistema registra automaticamente:
status da execução: Em execução;
usuário que iniciou;
data/hora de início.
Essa opção reduz cliques e simplifica o uso pela loja.
Opção alternativa — início manual
O sistema pode exibir um botão Iniciar execução antes de liberar os três botões principais.
Após clicar em Iniciar execução, o sistema registra o início e libera:
Adicionar evidência;
Apontar divergência;
Concluir execução.
13. Modal “Adicionar evidência”
Objetivo
Permitir que a loja anexe fotos e informações que comprovem a execução do planograma.
Campos do modal
O modal deve conter:
tipo de evidência;
módulo, quando aplicável;
upload de fotos;
fotos adicionadas;
observação opcional;
indicador de progresso;
botão cancelar;
botão salvar evidência.
Tipos de evidência

Tipo
Finalidade
Foto geral
Foto da gôndola completa
Módulo
Foto de um módulo específico
Produto
Foto de um produto específico
Outro
Evidência complementar

Regras por tipo de evidência
Foto geral
Deve representar a gôndola completa.
O campo módulo não deve ser obrigatório.
Pode ser obrigatória para conclusão, conforme configuração.
Módulo
Deve representar um módulo específico.
O campo módulo deve ser obrigatório.
Pode ser exigida uma foto por módulo, conforme configuração.
Produto
Deve representar um produto específico.
O sistema deve permitir vincular ao produto.
O módulo e a prateleira podem ser obrigatórios ou preenchidos automaticamente, se o produto já estiver vinculado ao planograma.
Outro
Usado para evidências complementares.
Módulo e produto devem ser opcionais.
14. Regra de evidências obrigatórias
O sistema deve permitir configurar quais evidências são obrigatórias para concluir a execução.
Regra padrão sugerida
Para concluir a execução, exigir:
1 foto geral da gôndola;
1 foto por módulo.
Exemplo:
Planograma com 5 módulos:
Foto geral: 1
Módulo 1: 1
Módulo 2: 1
Módulo 3: 1
Módulo 4: 1
Módulo 5: 1
Total obrigatório: 6 evidências
Por isso, na tela principal e no modal, o sistema exibiria:
Evidências: 3/6
Essa regra deve ser configurável por cliente, categoria ou tipo de planograma.
15. Upload de fotos
O sistema deve permitir:
selecionar arquivos manualmente;
arrastar e soltar arquivos;
anexar múltiplas fotos;
visualizar miniaturas;
remover fotos antes de salvar;
registrar observação;
registrar data/hora;
registrar usuário responsável pelo envio.
Sugestão técnica
formatos aceitos: JPG, PNG e HEIC, se possível;
limite por arquivo: 10 MB;
limite por envio: 10 fotos.
16. Modal “Apontar divergência”
Objetivo
Permitir que a loja registre problemas encontrados na execução do planograma sem alterar o planograma aprovado.
A divergência deve funcionar como uma justificativa operacional da loja.
Campos do modal
O modal deve conter:
tipo de divergência;
módulo;
prateleira;
posição/facing;
produto;
observação;
fotos opcionais;
lista de divergências registradas;
botão cancelar;
botão salvar e continuar;
botão registrar divergência.
Tipos de divergência
Sugestão de tipos:

Tipo
Descrição
Produto em ruptura
Produto não disponível para exposição
Produto divergente
Produto físico diferente do planejado
Falta de espaço
Gôndola real não comporta o planejado
Embalagem diferente
Produto possui embalagem, tamanho ou dimensão diferente
Produto não localizado
Loja não encontrou o produto
Produto sem cadastro
Produto não está disponível na base da loja
Quantidade insuficiente
Existe produto, mas em quantidade menor que a necessária
Outro
Situação não prevista

A tela pode exibir menos opções para simplificar, mas o sistema deve permitir expansão futura.
17. Obrigatoriedade dos campos da divergência
Regras gerais
tipo de divergência: obrigatório;
módulo: obrigatório;
prateleira: obrigatório;
observação: obrigatória;
foto: opcional;
produto: obrigatório quando a divergência estiver relacionada a produto.
Regras específicas
Produto em ruptura
Obrigatório informar:
produto;
módulo;
prateleira;
observação.
Produto divergente
Obrigatório informar:
produto planejado ou produto encontrado;
módulo;
prateleira;
observação.
Falta de espaço
Obrigatório informar:
módulo;
prateleira;
observação.
Produto pode ser opcional.
Embalagem diferente
Obrigatório informar:
produto;
módulo;
prateleira;
observação.
Foto recomendada.
Outro
Obrigatório informar:
módulo;
observação.
Produto pode ser opcional.
18. Status da divergência
Cada divergência deve possuir status próprio.
Status sugeridos

Status
Descrição
Aberta
Divergência registrada pela loja
Justificada
Loja anexou informação suficiente para justificar
Em análise
Responsável está analisando
Resolvida
Divergência foi tratada
Rejeitada
Responsável não aceitou a justificativa

Na primeira versão, pode-se usar apenas:
Aberta e Resolvida
Mas o ideal é já estruturar o banco para permitir evolução futura.
19. Lista de divergências registradas
Dentro do modal, o sistema deve exibir as divergências já registradas para o planograma atual.
Cada registro deve conter:
data/hora;
tipo;
produto;
módulo/prateleira;
posição/facing, se houver;
status;
usuário que registrou.
Essa lista ajuda a evitar registros duplicados e melhora a rastreabilidade.
20. Modal “Concluir execução”
Objetivo
Permitir que a loja finalize a execução do planograma e envie o card para a etapa Concluídos no Kanban.
Informações exibidas no modal
O modal deve apresentar um resumo da execução:
evidências enviadas;
divergências registradas;
SLA;
responsável;
validações da execução;
alerta sobre encerramento;
comentário final opcional;
botão cancelar;
botão concluir execução.
21. Validações antes da conclusão
Antes de permitir a conclusão, o sistema deve validar:

Validação
Regra
Evidências obrigatórias
Verificar se foram anexadas conforme regra cadastrada
Divergências abertas
Verificar se existem divergências sem justificativa ou pendentes
Observações obrigatórias
Verificar se campos obrigatórios foram preenchidos
Responsável
Registrar quem está concluindo
SLA
Registrar se a conclusão ocorreu dentro ou fora do prazo

22. Comportamento quando houver pendência
Evidências pendentes
Se faltarem evidências obrigatórias, o sistema deve bloquear a conclusão.
Mensagem sugerida:
Não é possível concluir a execução. Existem evidências obrigatórias pendentes.
O sistema pode oferecer um atalho para abrir o modal Adicionar evidência.
Divergências abertas
Se houver divergências abertas sem justificativa, o sistema pode seguir uma das duas regras abaixo.
Regra recomendada
Permitir a conclusão somente se as divergências estiverem justificadas.
Nesse caso:
divergência com observação e/ou foto pode ser considerada justificada;
divergência sem justificativa bloqueia a conclusão.
Mensagem sugerida:
Existem divergências abertas sem justificativa. Justifique as divergências antes de concluir a execução.
Regra alternativa
Permitir concluir mesmo com divergências abertas, mas registrar o status da execução como:
Concluída com divergência
Nesse caso, o card ainda vai para Concluídos, mas deve manter um alerta para análise posterior.
Minha recomendação: permitir conclusão com divergência apenas quando ela estiver justificada.
23. Ação de concluir execução
Ao clicar em Concluir execução, o sistema deve:
validar evidências obrigatórias;
validar divergências;
registrar comentário final, se houver;
registrar usuário responsável;
registrar data/hora da conclusão;
alterar status da execução para Concluída;
mover o card no Kanban de Execução em Loja para Concluídos;
calcular a próxima data de revisão periódica com base na regra cadastrada.
24. Mensagem no modal de conclusão
O texto do modal deve deixar claro o destino do card.
Mensagem sugerida:
Ao concluir a execução, este planograma será encerrado nesta etapa e movido para Concluídos no Kanban. A revisão periódica será gerada automaticamente conforme o prazo de revisão cadastrado para este planograma.
Esse texto evita confusão entre conclusão da execução e revisão periódica.
25. Cálculo da próxima revisão periódica
Após a conclusão da execução, o sistema deve calcular a próxima data de revisão.
Dados necessários
data/hora da conclusão;
periodicidade cadastrada no planograma;
unidade da periodicidade, se necessário;
próxima data de revisão.
Exemplo
Data de conclusão: 01/01/2026
Periodicidade: 60 dias
Próxima revisão: 02/03/2026
O sistema deve salvar essa próxima data no registro do planograma ou da execução.
26. Geração da revisão periódica
Quando a próxima data de revisão for atingida, o sistema deve gerar o card de Revisão Periódica.
Regra recomendada
Ao atingir a data de revisão, criar uma nova ocorrência de revisão periódica vinculada ao planograma original.
Essa nova ocorrência deve conter:
referência ao planograma original;
loja;
categoria;
corredor;
data da última execução;
data prevista da revisão;
status: Revisão pendente;
responsável;
histórico vinculado.
O card original permanece em Concluídos.
27. Nova ocorrência de revisão periódica
A revisão periódica deve ser tratada como uma nova atividade de acompanhamento, não como continuação da execução original.
A nova ocorrência pode ter seus próprios status, por exemplo:
revisão pendente;
em revisão;
ajuste solicitado;
revisão concluída.
Dependendo da regra futura, a revisão poderá:
apenas confirmar que o planograma continua correto;
solicitar uma nova execução;
gerar nova versão do planograma;
voltar para etapas de criação/revisão/aprovação.
28. Permissões por perfil
Executor / Loja
Pode:
visualizar planograma;
consultar produto;
visualizar fluxo da gôndola;
baixar PDF;
adicionar evidências;
apontar divergências;
concluir execução;
visualizar histórico da execução.
Não pode:
editar planograma;
alterar produtos;
alterar frentes;
alterar dimensões;
alterar módulos;
alterar fluxo da gôndola;
alterar data de revisão;
excluir evidências ou divergências de outros usuários, salvo permissão específica.
Responsável / Administrador / Consultor
Pode:
visualizar tudo;
configurar periodicidade de revisão;
revisar evidências;
revisar divergências;
devolver execução para ajuste, se essa regra existir;
aprovar ou validar execução, se houver etapa futura;
alterar fluxo do Kanban;
acessar histórico completo;
gerar ou antecipar revisão periódica.
29. Histórico e auditoria
Toda ação deve ser registrada no histórico.
Eventos que devem gerar histórico
planograma entrou em Execução em Loja;
execução iniciada;
evidência adicionada;
evidência removida;
divergência registrada;
divergência atualizada;
divergência resolvida;
execução concluída;
card movido para Concluídos;
próxima revisão calculada;
revisão periódica criada;
card movido para Revisão Periódica.
Informações registradas
Cada evento deve armazenar:
usuário;
data/hora;
ação realizada;
status anterior;
status novo;
observação;
arquivos anexados, quando houver.
30. SLA da execução
O SLA da execução deve ser controlado separadamente da revisão periódica.
Regra recomendada
O SLA começa a contar quando o card entra na etapa Execução em Loja.
O sistema deve registrar também quando a loja abriu/iniciou a execução.
Isso permite medir:
tempo até iniciar execução;
tempo total até concluir execução;
execução dentro ou fora do prazo.
Status de SLA
dentro do prazo;
próximo do vencimento;
vencido;
concluído no prazo;
concluído com atraso.
31. Notificações
O sistema pode notificar os responsáveis quando:
planograma entra em Execução em Loja;
execução é iniciada;
evidência é adicionada;
divergência é registrada;
execução é concluída;
card vai para Concluídos;
execução fica atrasada;
revisão periódica está próxima;
revisão periódica é criada;
revisão periódica está atrasada.
32. Resumo do fluxo completo
Fluxo de implantação
Planograma é criado.
Passa pelas etapas de revisão e aprovação.
Ao ser aprovado, vai para Execução em Loja.
Loja abre o planograma.
Sistema registra início da execução.
Loja visualiza o fluxo da gôndola.
Loja executa fisicamente.
Loja adiciona evidências.
Loja aponta divergências, se houver.
Loja conclui execução.
Sistema valida as regras obrigatórias.
Card sai de Execução em Loja.
Card vai para Concluídos.
Sistema calcula a próxima data de revisão periódica.
Fluxo de revisão periódica
Planograma permanece em Concluídos.
Sistema aguarda o prazo cadastrado de revisão.
Ao atingir o prazo, cria uma nova ocorrência ou card de Revisão Periódica.
Responsável revisa o planograma.
A revisão pode ser concluída, gerar ajuste ou gerar nova versão.
33. Regra central final
A regra central da funcionalidade deve ser:
Na etapa Execução em Loja, o usuário executor não edita o planograma. Ele registra a execução por meio de evidências, divergências e conclusão. Após a conclusão e validação das regras obrigatórias, o card deve sair de Execução em Loja e ir para Concluídos. A Revisão Periódica não acontece imediatamente após a execução; ela deve ser gerada somente quando vencer o prazo de revisão cadastrado na criação do planograma, contado a partir da conclusão da execução.

Gerado em Fri Jun 26 12:57:29 UTC 2026 por Siga Smart usando JIRA 1001.0.0-SNAPSHOT#100292-rev:d7cd03e966bb408ad32ffe870114289c528f56fd.
