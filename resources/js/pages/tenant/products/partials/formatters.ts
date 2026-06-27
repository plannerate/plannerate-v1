/**
 * Formatadores compartilhados pela página de vendas do produto e seus partials.
 * Centralizados aqui para evitar duplicação entre o cabeçalho, os cards e a tabela.
 */

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const moneyFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const quantityFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
});

const percentFormatter = new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});

/**
 * Formata uma string de data ISO para o formato pt-BR.
 * Retorna '-' para valores nulos ou inválidos.
 */
export function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const d = new Date(`${value}T00:00:00`);

    return Number.isNaN(d.getTime()) ? value : dateFormatter.format(d);
}

/**
 * Formata um valor numérico como moeda BRL.
 * Retorna '-' para valores nulos ou não numéricos.
 */
export function formatCurrency(value: string | number | null | undefined): string {
    if (value == null || value === '') {
        return '-';
    }

    const n = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(n) ? moneyFormatter.format(n) : String(value);
}

/**
 * Formata uma quantidade com 3 casas decimais (pt-BR).
 * Retorna '-' para valores nulos ou não numéricos.
 */
export function formatQuantity(value: string | number | null | undefined): string {
    if (value == null || value === '') {
        return '-';
    }

    const n = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(n) ? quantityFormatter.format(n) : String(value);
}

/**
 * Formata um percentual com 1 casa decimal e sufixo "%".
 */
export function formatPercent(value: number): string {
    return `${percentFormatter.format(Number.isFinite(value) ? value : 0)}%`;
}
