Documento Conceitual — Fluxo da Geração Automática de Planogramas por Template 

1. Objetivo da funcionalidade 

A funcionalidade tem como objetivo permitir que o sistema gere automaticamente um planograma a partir de um template pré-configurado. 

O usuário cria a base do planograma, informando cliente, loja ou cluster, estrutura mercadológica, quantidade de módulos, dimensões da gôndola, prateleiras e fluxo de leitura. 

Depois disso, o sistema deve usar o template correspondente e aplicar uma sequência obrigatória de cálculos para gerar o planograma automaticamente. 

A lógica principal é: 

Configurar planograma → aplicar template → rodar cálculos obrigatórios → organizar visualmente → gerar planograma final. 

Forma 

2. Primeiro passo — Criar a base do planograma 

O usuário inicia criando o planograma normalmente. 

Nesta etapa, são definidas as informações básicas: 

· Cliente; 
· Loja ou cluster; 
· Estrutura mercadológica; 
· Quantidade de módulos; 
· Quantidade de prateleiras; 
· Altura, largura e profundidade da gôndola; 
· Fluxo de leitura do cliente; 
· Demais configurações físicas necessárias. 

Essa etapa define o espaço onde o planograma será gerado. 

Forma 

3. Segundo passo — Buscar o template correspondente 

Após a configuração inicial, o sistema deve buscar automaticamente o template correspondente. 

O template deve ser identificado pela combinação entre: 

Estrutura mercadológica + quantidade de módulos 

Exemplo: 

· Limpeza com 1 módulo = Template Limpeza 1M; 
· Limpeza com 2 módulos = Template Limpeza 2M; 
· Limpeza com 3 módulos = Template Limpeza 3M. 

O template será a base da geração automática. 

Ele deve trazer previamente: 

· distribuição dos módulos; 
· zonas quentes e frias; 
· regras por módulo; 
· regras por zona; 
· regras por slot; 
· tipo de exposição; 
· critérios de ordenação visual; 
· regras de falta ou sobra de espaço. 

Forma 

4. Terceiro passo — Aplicar validações automáticas 

Antes de gerar o planograma, o sistema deve validar automaticamente quais produtos podem participar da geração. 

Essas validações são regras internas do sistema. 

O produto só deve participar se: 

· estiver ativo; 
· pertencer ao sortimento da loja ou cluster; 
· pertencer à estrutura mercadológica escolhida; 
· tiver dimensões cadastradas; 
· couber fisicamente na gôndola ou prateleira; 
· não estiver bloqueado. 

Produtos que não atenderem esses critérios devem ser rejeitados antes da geração. 

Forma 

5. Quarto passo — Aplicar o cálculo de papel (BCG) 

O primeiro cálculo obrigatório é o papel do produto ou categoria. 

Esse cálculo ajuda o sistema a entender a função estratégica de cada produto ou grupo dentro do planograma. 

Ele responde perguntas como: 

· esse produto deve ser mantido? 
· esse produto gera valor? 
· esse produto gera margem? 
· esse produto é um peso morto? 
· esse produto deve receber incentivo? 
· esse produto deve ir para uma zona quente ou fria? 

Exemplos de classificações: 

· Alto valor — manutenção; 
· Peso morto; 
· Incentivo lucro; 
· Incentivo valor; 
· Incentivo margem. 

O papel não decide sozinho o que entra ou sai. 
Ele orienta a estratégia e o posicionamento dos produtos dentro da gôndola. 

Forma 

6. Quinto passo — Aplicar a análise de sortimento (ABC) 

Depois do cálculo de papel, o sistema deve aplicar a análise de sortimento. 

Essa análise define quais produtos devem compor o planograma. 

Ela responde: 

O que fica e o que sai? 

A análise de sortimento pode considerar critérios como: 

· volume vendido; 
· valor vendido; 
· margem; 
· lucro; 
· frequência de venda; 
· pesos configurados por categoria. 

Essa etapa define: 

· produtos prioritários; 
· produtos intermediários; 
· produtos de baixa prioridade; 
· produtos que devem ser retirados; 
· produtos que devem ser preservados. 

Em resumo: 

A análise de sortimento define a composição do planograma. 

Forma 

7. Sexto passo — Calcular o estoque alvo 

Depois de definir quais produtos devem compor o planograma, o sistema deve calcular o estoque alvo. 

O estoque alvo responde: 

Quanto espaço cada produto precisa ocupar? 

Ele deve ajudar a definir: 

· quantidade ideal exposta; 
· quantidade sugerida de frentes; 
· necessidade de cobertura; 
· redução de risco de ruptura; 
· equilíbrio entre venda, abastecimento e espaço disponível. 

O estoque alvo não deve decidir sozinho se o produto entra ou sai. 
Ele calcula a necessidade de exposição dos produtos selecionados. 

Forma 

8. Sétimo passo — Aplicar frente mínima e ajustar espaço 

Depois do estoque alvo, o sistema deve aplicar a regra de frente mínima. 

A frente mínima garante que um produto alocado tenha presença visual suficiente. 

Regra principal: 

Se o produto entrou no planograma, ele deve respeitar a frente mínima configurada. 

Depois disso, o sistema deve verificar se há falta ou sobra de espaço. 

Se faltar espaço, o sistema deve aplicar a regra configurada no template. 

Exemplos: 

· reduzir frentes até o mínimo; 
· remover produtos de menor prioridade; 
· remover produtos classificados como peso morto; 
· remover curva C primeiro; 
· preservar produtos obrigatórios. 

Se sobrar espaço, o sistema deve aplicar a regra de expansão. 

Exemplos: 

· não expandir; 
· expandir produtos prioritários; 
· expandir produtos com maior estoque alvo; 
· expandir produtos de maior margem; 
· expandir produtos de maior venda. 

Forma 

9. Oitavo passo — Aplicar estratégia por zona 

Depois dos cálculos e ajustes de espaço, o sistema deve aplicar a estratégia por zona definida no template. 

Exemplos: 

· zona quente prioriza maior margem; 
· zona quente prioriza produtos de alto valor; 
· zona quente prioriza maior giro; 
· zona fria recebe produtos complementares; 
· zona fria recebe produtos de menor prioridade; 
· zona fria recebe embalagens maiores. 

Essa regra orienta onde os produtos devem ficar dentro da gôndola. 

Ela deve ser preferencial, não absoluta. 

Ou seja, a zona ajuda no posicionamento, mas não pode quebrar regras básicas como: 

· produto pertencer à categoria correta; 
· produto caber fisicamente; 
· produto respeitar frente mínima; 
· produto não estar bloqueado. 

Forma 

10. Nono passo — Aplicar o tipo de exposição 

Depois que o sistema já sabe quais produtos entram, quanto espaço precisam e em qual zona devem ficar, ele deve aplicar o tipo de exposição definido no template. 

Opções principais: 

· Vertical; 
· Horizontal; 
· Combinada. 

Exposição vertical 

O grupo de produtos desce entre prateleiras, formando uma coluna visual. 

Exposição horizontal 

O grupo de produtos segue lateralmente na prateleira. 

Exposição combinada 

O sistema pode usar regras diferentes por módulo, zona, prateleira ou grupo de produtos. 

Exemplo: 

· módulo 1 com exposição vertical por marca; 
· módulo 2 com exposição horizontal por tipo; 
· zona quente com produtos de maior margem; 
· zona fria com embalagens maiores. 

Forma 

11. Décimo passo — Aplicar a ordenação visual 

Depois do tipo de exposição, o sistema deve aplicar a ordenação visual definida no template. 

A ordenação visual define como os produtos serão lidos pelo cliente. 

Critérios possíveis: 

· marca; 
· tipo; 
· embalagem; 
· tamanho; 
· preço; 
· versão; 
· atributo. 

A lógica deve ser hierárquica. 

Exemplo: 

Marca; 
 

Tipo; 
 

Tamanho; 
 

Preço. 

Nesse caso, o sistema deve: 

· primeiro agrupar por marca; 
· dentro da marca, organizar por tipo; 
· dentro do tipo, ordenar por tamanho; 
· dentro do tamanho, ordenar por preço. 

Se a ordem for alterada para: 

Tipo; 
 

Embalagem; 
 

Marca; 
 

Preço. 

O sistema deve seguir essa nova hierarquia. 

A regra principal é: 

O primeiro critério visual manda primeiro. 
O segundo organiza dentro do primeiro. 
O terceiro organiza dentro do segundo. 

Forma 

12. Décimo primeiro passo — Respeitar o fluxo de leitura 

A ordenação visual deve respeitar o fluxo definido na gôndola. 

Exemplos: 

· esquerda para direita; 
· direita para esquerda; 
· entrada para saída; 
· saída para entrada. 

Exemplo: 

Se o fluxo for da esquerda para direita e o preço for configurado do menor para o maior: 

· início do fluxo = menor preço; 
· final do fluxo = maior preço. 

Se a regra for tamanho do maior para o menor: 

· início do fluxo = maior embalagem; 
· final do fluxo = menor embalagem. 

O fluxo define onde começa e onde termina a leitura da exposição. 

Forma 

13. Décimo segundo passo — Gerar o planograma final 

Após aplicar o template, os cálculos obrigatórios, as regras de espaço, as zonas, o tipo de exposição e a ordenação visual, o sistema deve gerar o planograma final. 

O resultado deve conter: 

· produtos alocados; 
· produtos rejeitados; 
· quantidade de frentes; 
· posição por módulo; 
· posição por prateleira; 
· zona de cada grupo ou produto; 
· organização visual aplicada. 

Forma 

14. Ajustes após a geração 

Depois do planograma gerado, o usuário poderá fazer ajustes no editor. 

Esses ajustes podem ter três comportamentos: 

Reordenar 

Quando muda apenas a ordem visual. 

Exemplos: 

· mudar ordem das marcas; 
· mudar preço de menor para maior; 
· mudar tamanho de maior para menor. 

Nesse caso, o sistema mantém os mesmos produtos e frentes, apenas reorganiza. 

Redistribuir 

Quando muda a estrutura visual. 

Exemplos: 

· mudar de vertical para horizontal; 
· mudar de horizontal para combinada; 
· trocar agrupamento principal. 

Nesse caso, o sistema tenta manter os mesmos produtos e frentes, mas redistribui as posições. 

Regerar 

Quando muda uma regra de decisão. 

Exemplos: 

· mudar parâmetros dos cálculos; 
· mudar regra de sortimento; 
· mudar estoque alvo; 
· mudar frente mínima; 
· mudar estratégia por zona; 
· mudar estrutura mercadológica. 

Nesse caso, o sistema deve recalcular a geração. 

Forma 

15. Resumo da lógica final 

A geração automática deve seguir esta ordem: 

Criar a base do planograma; 
 

Buscar o template correspondente; 
 

Validar produtos elegíveis; 
 

Calcular papel do produto ou categoria; 
 

Aplicar análise de sortimento; 
 

Calcular estoque alvo; 
 

Aplicar frente mínima; 
 

Resolver falta ou sobra de espaço; 
 

Aplicar estratégia por zona; 
 

Aplicar tipo de exposição; 
 

Aplicar ordenação visual; 
 

Respeitar o fluxo de leitura; 
 

Gerar o planograma final; 
 

Permitir ajustes no editor. 

Forma 

16. Síntese conceitual 

O template define a estrutura da exposição. 

O papel define a intenção estratégica. 

A análise de sortimento define o que entra e o que sai. 

O estoque alvo define quanto espaço cada produto precisa. 

As regras de falta e sobra ajustam a ocupação. 

A estratégia por zona define onde os produtos devem ficar. 

O tipo de exposição define se a leitura será vertical, horizontal ou combinada. 

A ordenação visual define a sequência dos produtos. 

O fluxo define o sentido da leitura. 

O resultado deve ser um planograma gerado automaticamente com base em estratégia, dados e regras previamente configuradas. 