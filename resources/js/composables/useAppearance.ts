import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

const LEGACY_THEME_KEY = 'theme';

const isAppearance = (value: string | null): value is Appearance => {
    return value === 'light' || value === 'dark' || value === 'system';
};

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    const storedAppearance = localStorage.getItem('appearance');

    if (isAppearance(storedAppearance)) {
        return storedAppearance;
    }

    const legacyTheme = localStorage.getItem(LEGACY_THEME_KEY);

    if (legacyTheme === 'dark' || legacyTheme === 'light') {
        return legacyTheme;
    }

    return null;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    appearance.value = savedAppearance || 'system';
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

export function useAppearance() {
    onMounted(() => {
        const savedAppearance = getStoredAppearance();

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        const legacyThemeValue = value === 'system'
            ? (mediaQuery()?.matches ? 'dark' : 'light')
            : value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);
        localStorage.setItem(LEGACY_THEME_KEY, legacyThemeValue);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
