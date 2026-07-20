<?php

return [
    'navigation' => 'Gerador de Propostas',
    'title' => 'Gerador de Propostas',
    'description' => 'Monte a proposta comercial no editor e gere o PDF para envio ao cliente.',
    'brand' => 'GERADOR DE PROPOSTAS 1.0',

    'sections' => [
        'proposal' => 'Proposta',
        'client' => 'Cliente',
        'commercial' => 'Composição comercial',
        'modules' => 'Módulos incluídos',
        'discount' => 'Desconto e pagamento',
        'conditions' => 'Condições comerciais',
        'contact' => 'Contato Plannerate',
        'template' => 'Modelo padrão',
        'drafts' => 'Propostas salvas',
    ],

    'fields' => [
        'number' => 'Número',
        'date' => 'Data',
        'city' => 'Cidade de emissão',
        'validity' => 'Validade (dias)',
        'plan' => 'Plano',
        'intro' => 'Resumo da solução',

        'client' => 'Razão social / nome fantasia *',
        'cnpj' => 'CNPJ',
        'client_city' => 'Cidade / UF',
        'contact' => 'Responsável',

        'note' => 'Observação',
        'commercial_component' => 'Componente comercial',
        'billing_type' => 'Tipo de cobrança',
        'quantity' => 'Quantidade',
        'billing_base' => 'Base de cobrança',
        'unit_value' => 'Valor unitário',
        'installments' => 'Parcelas',
        'commercial_subtotal' => 'Subtotal comercial',
        'unlimited' => 'Ilimitados',

        'module_description' => 'Descrição',

        'setup_discount' => 'Desconto no setup',
        'setup_discount_value' => 'Valor do desconto no setup',
        'monthly_discount' => 'Desconto na mensalidade',
        'monthly_discount_value' => 'Valor do desconto na mensalidade',
        'store_discount' => 'Desconto em loja',
        'store_discount_value' => 'Valor do desconto em loja',
        'discount_reason' => 'Motivo / observação do desconto',
        'discount_term' => 'Prazo contratual vinculado ao desconto',
        'payment' => 'Forma de pagamento',
        'implementation' => 'Prazo de implantação',
        'due' => 'Vencimento',
        'adjustment' => 'Reajuste',

        'condition' => 'Condição',
        'discount_value' => 'Valor do desconto',
        'discount_percent' => 'Percentual de desconto',
        'bonus_quantity' => 'Quantidade bonificada',

        'seller' => 'Responsável',
        'seller_role' => 'Cargo',
        'phone' => 'Telefone',
        'email' => 'E-mail',
        'site' => 'Site',
    ],

    'placeholders' => [
        'item_description' => 'Descrição do item',
        'item_note' => 'Ex.: inclui até 10 lojas',
        'billing_base' => 'Ex.: projeto, usuário, loja',
        'module_name' => 'Nome do módulo',
    ],

    'options' => [
        'category' => [
            'setup' => 'Setup',
            'admin' => 'Usuário administrativo',
            'assistant' => 'Usuário assistente',
            'store' => 'Loja / unidade',
            'other' => 'Outro item',
        ],
        'billing' => [
            'mensal' => 'Mensal',
            'unico' => 'Único',
        ],
        'discount' => [
            'none' => 'Sem desconto',
            'fixed' => 'Valor fixo',
            'percent' => 'Percentual',
        ],
        'user_discount' => [
            'none' => 'Sem desconto',
            'percent' => 'Desconto percentual',
            'fixed' => 'Desconto em valor',
            'bonus' => 'Bonificação de usuários',
        ],
        'term' => [
            'none' => 'Sem vínculo',
            'months_12' => '12 meses',
            'months_24' => '24 meses',
        ],
    ],

    'commercial' => [
        'hint' => 'Monte a proposta por setup, usuários administrativos, usuários assistentes e número de lojas.',
        'add_free_item' => '+ Adicionar item livre',
        'add_setup' => '+ Setup',
        'add_admin' => '+ Usuário administrativo',
        'add_assistant' => '+ Usuário assistente',
        'add_store' => '+ Loja',
        'user_discounts_title' => 'Descontos e bonificações por usuário',
        'user_discounts_empty' => 'Adicione usuários administrativos ou assistentes na composição comercial para configurar descontos e bonificações.',
        'unlimited_summary' => 'Quantidade contratada: ilimitada · Valor atual: :value',
        'bonus_summary' => ':bonus de :total usuário(s) bonificado(s).',
        'current_value' => 'Valor atual: :value',
    ],

    'modules' => [
        'add' => '+ Adicionar módulo',
    ],

    'conditions' => [
        'add' => '+ Adicionar condição',
    ],

    'template' => [
        'hint' => 'Salve módulos, itens, preços, condições e dados comerciais como base para as próximas propostas.',
        'save' => 'Salvar editor como padrão',
        'restore' => 'Restaurar padrão inicial',
        'status_custom' => 'Modelo personalizado salvo em :date.',
        'status_default' => 'Usando o modelo padrão original do Plannerate.',
    ],

    'drafts' => [
        'empty' => 'Nenhuma proposta salva.',
        'no_client' => 'Sem cliente',
        'remove' => 'Remover proposta',
    ],

    'actions' => [
        'new' => 'Nova proposta',
        'save' => 'Salvar proposta',
        'export' => 'Exportar',
        'import' => 'Importar',
        'print' => 'Imprimir / salvar em PDF',
        'remove' => 'Remover',
    ],

    'document' => [
        'title' => 'Proposta Comercial',
        'prepared_for' => 'Preparado para',
        'plan' => 'Plano',
        'plan_fallback' => 'Plannerate',
        'client_fallback' => 'Nome do cliente',
        'client_label' => 'Cliente',
        'cnpj_label' => 'CNPJ',
        'city_label' => 'Cidade',
        'contact_label' => 'Responsável',

        'modules' => 'Módulos incluídos',
        'investment' => 'Investimento',
        'col_description' => 'Descrição',
        'col_quantity' => 'Quantidade',
        'col_unit' => 'Valor unitário',
        'col_type' => 'Tipo',
        'col_value' => 'Valor',
        'empty_items' => 'Adicione os itens comerciais no editor.',
        'type_unico' => 'Único',
        'type_mensal' => 'Mensal',
        'unlimited' => 'Ilimitados',
        'installment_line' => ':count x de :value',

        'card_setup' => 'Implantação',
        'card_setup_discounted' => 'Implantação com desconto',
        'card_monthly' => 'Mensalidade',
        'card_monthly_discounted' => 'Mensalidade com desconto',
        'installments_multi' => 'pagamento em até :count parcelas',
        'installments_single' => 'pagamento único',
        'status_original' => 'valor original',
        'status_new' => 'novo valor',
        'discount_condition_label' => 'Condição do desconto:',
        'discount_condition' => 'valores válidos para contratação com vigência mínima de :months meses.',

        'conditions' => 'Condições comerciais',
        'condition_payment' => 'Forma de pagamento: :value',
        'condition_implementation' => 'Prazo de implantação: :value',
        'condition_adjustment' => 'Reajuste: :value',
        'condition_due' => 'Vencimento: :value',

        'accept' => 'Aceite da proposta',
        'accept_text' => 'Ao assinar este documento, o cliente declara estar de acordo com o escopo, os valores e as condições comerciais apresentados. A contratação definitiva poderá ser formalizada por instrumento contratual próprio.',
        'sign_client_fallback' => 'Cliente',
        'company' => 'Plannerate',

        'footer_thanks' => 'Obrigado pela confiança.',
        'footer_validity' => 'Proposta válida por :days dias.',

        'discount_of_percent' => 'Desconto de :percent%',
        'discount_of_value' => 'Desconto de :value',
        'bonus_user' => 'usuário bonificado',
        'bonus_users' => 'usuários bonificados',
        'unlimited_assistants' => 'Usuários assistentes ilimitados',
    ],

    'messages' => [
        'draft_saved' => 'Proposta :num salva.',
        'draft_updated' => 'Proposta :num atualizada.',
        'draft_saved_hint' => 'Guardada neste navegador para :client.',
        'draft_deleted' => 'Proposta :num removida.',

        'template_saved' => 'Modelo padrão salvo.',
        'template_saved_hint' => 'As próximas propostas nascerão com esta estrutura.',
        'template_restore_confirm' => 'Restaurar o modelo padrão original do Plannerate? As propostas já salvas não serão alteradas.',
        'template_restored' => 'Modelo padrão original restaurado.',

        'exported' => 'Arquivo :file gerado.',
        'imported' => 'Proposta :num importada.',
        'import_invalid' => 'Arquivo inválido. Selecione um JSON exportado por esta ferramenta.',

        'validation_title' => 'Revise a proposta antes de continuar.',
        'storage_failed' => 'Não foi possível gravar no navegador. Verifique o espaço disponível ou saia da janela anônima.',

        'client_required' => 'Informe o nome do cliente.',
        'item_required' => 'Adicione ao menos um item com valor maior que zero.',
        'date_required' => 'Informe a data da proposta.',
    ],
];
