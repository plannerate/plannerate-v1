import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/participants',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

const participants = {
    store: Object.assign(store, store),
}

export default participants