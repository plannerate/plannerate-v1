import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:33
* @route '/whatsapp/cloud/templates'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/whatsapp/cloud/templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:33
* @route '/whatsapp/cloud/templates'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:33
* @route '/whatsapp/cloud/templates'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:33
* @route '/whatsapp/cloud/templates'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:33
* @route '/whatsapp/cloud/templates'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:33
* @route '/whatsapp/cloud/templates'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:33
* @route '/whatsapp/cloud/templates'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:61
* @route '/whatsapp/cloud/templates'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:61
* @route '/whatsapp/cloud/templates'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:61
* @route '/whatsapp/cloud/templates'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:61
* @route '/whatsapp/cloud/templates'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::store
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:61
* @route '/whatsapp/cloud/templates'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::send
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:105
* @route '/whatsapp/cloud/templates/send'
*/
export const send = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})

send.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/templates/send',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::send
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:105
* @route '/whatsapp/cloud/templates/send'
*/
send.url = (options?: RouteQueryOptions) => {
    return send.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::send
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:105
* @route '/whatsapp/cloud/templates/send'
*/
send.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: send.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::send
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:105
* @route '/whatsapp/cloud/templates/send'
*/
const sendForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: send.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::send
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:105
* @route '/whatsapp/cloud/templates/send'
*/
sendForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: send.url(options),
    method: 'post',
})

send.form = sendForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::update
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:76
* @route '/whatsapp/cloud/templates/{id}/edit'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/templates/{id}/edit',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::update
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:76
* @route '/whatsapp/cloud/templates/{id}/edit'
*/
update.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return update.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::update
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:76
* @route '/whatsapp/cloud/templates/{id}/edit'
*/
update.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::update
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:76
* @route '/whatsapp/cloud/templates/{id}/edit'
*/
const updateForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::update
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:76
* @route '/whatsapp/cloud/templates/{id}/edit'
*/
updateForm.post = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, options),
    method: 'post',
})

update.form = updateForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::destroy
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:91
* @route '/whatsapp/cloud/templates/{name}'
*/
export const destroy = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/whatsapp/cloud/templates/{name}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::destroy
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:91
* @route '/whatsapp/cloud/templates/{name}'
*/
destroy.url = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { name: args }
    }

    if (Array.isArray(args)) {
        args = {
            name: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        name: args.name,
    }

    return destroy.definition.url
            .replace('{name}', parsedArgs.name.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::destroy
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:91
* @route '/whatsapp/cloud/templates/{name}'
*/
destroy.delete = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::destroy
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:91
* @route '/whatsapp/cloud/templates/{name}'
*/
const destroyForm = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\TemplatePanelController::destroy
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/TemplatePanelController.php:91
* @route '/whatsapp/cloud/templates/{name}'
*/
destroyForm.delete = (args: { name: string | number } | [name: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const TemplatePanelController = { index, store, send, update, destroy }

export default TemplatePanelController