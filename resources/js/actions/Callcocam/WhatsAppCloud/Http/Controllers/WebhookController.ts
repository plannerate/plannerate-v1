import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::verify
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:28
* @route '/webhooks/whatsapp/cloud'
*/
export const verify = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})

verify.definition = {
    methods: ["get","head"],
    url: '/webhooks/whatsapp/cloud',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::verify
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:28
* @route '/webhooks/whatsapp/cloud'
*/
verify.url = (options?: RouteQueryOptions) => {
    return verify.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::verify
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:28
* @route '/webhooks/whatsapp/cloud'
*/
verify.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: verify.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::verify
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:28
* @route '/webhooks/whatsapp/cloud'
*/
verify.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: verify.url(options),
    method: 'head',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::verify
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:28
* @route '/webhooks/whatsapp/cloud'
*/
const verifyForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: verify.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::verify
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:28
* @route '/webhooks/whatsapp/cloud'
*/
verifyForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: verify.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::verify
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:28
* @route '/webhooks/whatsapp/cloud'
*/
verifyForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: verify.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

verify.form = verifyForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:53
* @route '/webhooks/whatsapp/cloud'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/webhooks/whatsapp/cloud',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:53
* @route '/webhooks/whatsapp/cloud'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:53
* @route '/webhooks/whatsapp/cloud'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:53
* @route '/webhooks/whatsapp/cloud'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\WebhookController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/WebhookController.php:53
* @route '/webhooks/whatsapp/cloud'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const WebhookController = { verify, store }

export default WebhookController