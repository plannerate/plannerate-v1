import { configureEcho } from "@laravel/echo-vue";

// Configura o Echo com driver Pusher compatível com Laravel Reverb.
// IMPORTANTE: withCredentials: true é necessário quando SESSION_DRIVER=redis
// para garantir que cookies de sessão sejam enviados na autenticação

// Obtém variáveis de ambiente do Vite (build-time) ou do window (runtime)
// No build do Docker, as variáveis VITE_* são injetadas durante o build
// No runtime, se não estiverem disponíveis, usamos window (injetado via Laravel)
const getEnvVar = (key: string, otherKey: string, fallback: string): string => {
    // Tenta primeiro import.meta.env (build-time)
    if (import.meta.env[key]) {
        return import.meta.env[key];
    }
    if (import.meta.env[otherKey]) {
        return import.meta.env[otherKey];
    }
    // Se não estiver disponível, tenta window (runtime injection via Laravel)
    if (typeof window !== 'undefined' && (window as any)[key]) {
        return (window as any)[key];
    }
    if (typeof window !== 'undefined' && (window as any)[otherKey]) {
        return (window as any)[otherKey];
    }
    // Fallback
    return fallback;
};

// Detecta se está usando Pusher oficial (com cluster) ou servidor customizado (Reverb)
const cluster = getEnvVar('VITE_REVERB_APP_CLUSTER', 'VITE_PUSHER_APP_CLUSTER', '').trim();
const customHost = getEnvVar('VITE_REVERB_HOST', 'VITE_PUSHER_HOST', '').trim();
const usePusherOfficial = Boolean(!customHost);

const echoConfig: any = {
    broadcaster: "pusher" as const,
    key: getEnvVar('VITE_REVERB_APP_KEY', 'VITE_PUSHER_APP_KEY', 'YWm0vycOJNEaiCMTUKUMLOT4ysb06WZd'),
    cluster: cluster || 'mt1',
    forceTLS: (getEnvVar('VITE_REVERB_SCHEME', 'VITE_PUSHER_SCHEME', 'https') === 'https'),
    enabledTransports: ['ws', 'wss'] as ('ws' | 'wss')[],
    withCredentials: true,
    disableStats: true,
};

// Se usar Pusher oficial, apenas define o cluster (Pusher determina o host automaticamente)
if (usePusherOfficial) {
    // Pusher oficial usa cluster para resolver o host automaticamente.
} else {
    // Se usar servidor customizado (Reverb local), define host e portas
    echoConfig.wsHost = getEnvVar('VITE_REVERB_HOST', 'VITE_PUSHER_HOST', 'localhost');
    echoConfig.wsPort = Number(getEnvVar('VITE_REVERB_PORT', 'VITE_PUSHER_PORT', '8080'));
    echoConfig.wssPort = Number(getEnvVar('VITE_REVERB_PORT', 'VITE_PUSHER_PORT', '8080'));
} 

configureEcho(echoConfig);