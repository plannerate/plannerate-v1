/**
 * Service worker do PWA de campo do Trade.
 *
 * Publicado em `public/sw-trade.js` por `php artisan trade:publish-pwa` e
 * registrado só no módulo /campo (via useFieldPwa). Não faz cache offline de
 * propósito: `fetch` passa direto. Sua única razão de existir é receber push e
 * abrir a atividade certa no clique.
 *
 * NÃO EDITE aqui — este é o stub do pacote. Ajuste no pacote e republique.
 */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = { body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Trade — Campo';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/vendor/trade/icons/trade-icon.svg',
        badge: payload.badge || '/vendor/trade/icons/trade-icon.svg',
        data: { url: (payload.data && payload.data.url) || payload.url || '/trade/campo' },
        tag: payload.tag || undefined,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = (event.notification.data && event.notification.data.url) || '/trade/campo';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client) {
                        client.navigate(targetUrl);
                    }
                    return undefined;
                }
            }

            return self.clients.openWindow ? self.clients.openWindow(targetUrl) : undefined;
        }),
    );
});
