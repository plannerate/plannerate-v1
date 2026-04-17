/**
 * useTheme - Composable para gerenciamento de temas
 *
 * Gerencia a aplicação de temas (cores, fontes, variantes) na aplicação.
 * Os temas são aplicados através de classes CSS no elemento raiz.
 *
 * @example
 * const { theme, setTheme, availableThemes } = useTheme()
 * setTheme({ color: 'blue', font: 'inter', rounded: 'medium' })
 */

import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export interface ThemeConfig {
    color?: string;
    font?: string;
    rounded?: string;
    variant?: string;
}

export interface Theme extends ThemeConfig {
    name: string;
    label: string;
}

// Temas disponíveis
const AVAILABLE_THEMES: Theme[] = [
    { name: 'default', label: 'Padrão', color: 'default' },
    { name: 'blue', label: 'Azul', color: 'blue' },
    { name: 'green', label: 'Verde', color: 'green' },
    { name: 'amber', label: 'Âmbar', color: 'amber' },
    { name: 'rose', label: 'Rosa', color: 'rose' },
    { name: 'purple', label: 'Roxo', color: 'purple' },
    { name: 'orange', label: 'Laranja', color: 'orange' },
    { name: 'teal', label: 'Azul Turquesa', color: 'teal' },
    { name: 'red', label: 'Vermelho', color: 'red' },
    { name: 'yellow', label: 'Amarelo', color: 'yellow' },
    { name: 'violet', label: 'Violeta', color: 'violet' },
];

const AVAILABLE_FONTS = [
    { value: 'default', label: 'Padrão (Geist)' },
    { value: 'inter', label: 'Inter' },
    { value: 'noto-sans', label: 'Noto Sans' },
    { value: 'nunito-sans', label: 'Nunito Sans' },
    { value: 'figtree', label: 'Figtree' },
];

const AVAILABLE_ROUNDED = [
    { value: 'none', label: 'Nenhum' },
    { value: 'small', label: 'Pequeno' },
    { value: 'medium', label: 'Médio' },
    { value: 'large', label: 'Grande' },
    { value: 'full', label: 'Completo' },
];

const AVAILABLE_VARIANTS = [
    { value: 'default', label: 'Padrão' },
    { value: 'mono', label: 'Monoespaçado' },
    { value: 'scaled', label: 'Escalado' },
];

// Estado global do tema
const currentTheme = ref<ThemeConfig>({
    color: 'default',
    font: 'default',
    rounded: 'medium',
    variant: 'default',
});

// Chave para persistência no localStorage
const STORAGE_KEY = 'app-theme';

/**
 * Carrega o tema salvo do localStorage
 */
function loadThemeFromStorage(): ThemeConfig {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            return JSON.parse(saved);
        }
    } catch (error) {
        console.error('Erro ao carregar tema do localStorage:', error);
    }
    return currentTheme.value;
}

/**
 * Salva o tema no localStorage
 */
function saveThemeToStorage(theme: ThemeConfig) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(theme));
    } catch (error) {
        console.error('Erro ao salvar tema no localStorage:', error);
    }
}

/**
 * Salva o tema no servidor
 */
async function saveThemeToServer(theme: ThemeConfig) {
    try {
        router.put(
            '/tenant/update-theme',
            { ...theme },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    } catch (error) {
        console.error('Error saving theme to server:', error);
    }
}

/**
 * Aplica as classes de tema no elemento raiz
 */
function applyThemeClasses(theme: ThemeConfig) {
    const html = document.documentElement;
    const body = document.body;

    // Adiciona a classe theme-container no body se não existir
    if (!body.classList.contains('theme-container')) {
        body.classList.add('theme-container');
    }

    // Remove todas as classes de tema existentes
    html.classList.forEach((className) => {
        if (className.startsWith('theme-')) {
            html.classList.remove(className);
        }
    });

    // Aplica as novas classes de tema
    if (theme.color && theme.color !== 'default') {
        html.classList.add(`theme-${theme.color}`);
    }

    if (theme.font && theme.font !== 'default') {
        html.classList.add(`theme-${theme.font}`);
    }

    if (theme.rounded && theme.rounded !== 'medium') {
        html.classList.add(`theme-rounded-${theme.rounded}`);
    }

    if (theme.variant && theme.variant !== 'default') {
        html.classList.add(`theme-${theme.variant}`);
    }
}

/**
 * Inicializa o sistema de temas
 * Deve ser chamado uma vez no app.ts
 *
 * @param serverTheme Tema carregado do servidor (tem prioridade sobre localStorage)
 */
export function initializeThemeSystem(serverTheme?: ThemeConfig | null) {
    if (typeof window !== 'undefined') {
        // Prefer server theme over localStorage
        const savedTheme = serverTheme || loadThemeFromStorage();
        currentTheme.value = savedTheme;
        applyThemeClasses(savedTheme);
    }
}

/**
 * Composable para gerenciamento de temas
 */
export function useTheme() {
    // Garante que o tema está aplicado
    if (
        typeof window !== 'undefined' &&
        !document.body.classList.contains('theme-container')
    ) {
        applyThemeClasses(currentTheme.value);
    }

    // Observa mudanças no tema e aplica as classes
    watch(
        currentTheme,
        (newTheme) => {
            applyThemeClasses(newTheme);
            saveThemeToStorage(newTheme);
            saveThemeToServer(newTheme);
        },
        { deep: true },
    );

    /**
     * Define um novo tema
     */
    function setTheme(theme: Partial<ThemeConfig>) {
        currentTheme.value = {
            ...currentTheme.value,
            ...theme,
        };
    }

    /**
     * Define a cor do tema
     */
    function setColor(color: string) {
        setTheme({ color });
    }

    /**
     * Define a fonte do tema
     */
    function setFont(font: string) {
        setTheme({ font });
    }

    /**
     * Define o arredondamento do tema
     */
    function setRounded(rounded: string) {
        setTheme({ rounded });
    }

    /**
     * Define a variante do tema
     */
    function setVariant(variant: string) {
        setTheme({ variant });
    }

    /**
     * Reseta o tema para o padrão
     */
    function resetTheme() {
        currentTheme.value = {
            color: 'default',
            font: 'default',
            rounded: 'medium',
            variant: 'default',
        };
    }

    /**
     * Tema atual como computed
     */
    const theme = computed(() => currentTheme.value);

    return {
        theme,
        setTheme,
        setColor,
        setFont,
        setRounded,
        setVariant,
        resetTheme,
        availableThemes: AVAILABLE_THEMES,
        availableFonts: AVAILABLE_FONTS,
        availableRounded: AVAILABLE_ROUNDED,
        availableVariants: AVAILABLE_VARIANTS,
    };
}
