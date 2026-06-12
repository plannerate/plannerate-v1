import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\ProviderController::index
* @see app/Http/Controllers/Tenant/ProviderController.php:29
* @route '/providers'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/providers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProviderController::index
* @see app/Http/Controllers/Tenant/ProviderController.php:29
* @route '/providers'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProviderController::index
* @see app/Http/Controllers/Tenant/ProviderController.php:29
* @route '/providers'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::index
* @see app/Http/Controllers/Tenant/ProviderController.php:29
* @route '/providers'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::index
* @see app/Http/Controllers/Tenant/ProviderController.php:29
* @route '/providers'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::index
* @see app/Http/Controllers/Tenant/ProviderController.php:29
* @route '/providers'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::index
* @see app/Http/Controllers/Tenant/ProviderController.php:29
* @route '/providers'
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
* @see \App\Http\Controllers\Tenant\ProviderController::create
* @see app/Http/Controllers/Tenant/ProviderController.php:83
* @route '/providers/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/providers/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProviderController::create
* @see app/Http/Controllers/Tenant/ProviderController.php:83
* @route '/providers/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProviderController::create
* @see app/Http/Controllers/Tenant/ProviderController.php:83
* @route '/providers/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::create
* @see app/Http/Controllers/Tenant/ProviderController.php:83
* @route '/providers/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::create
* @see app/Http/Controllers/Tenant/ProviderController.php:83
* @route '/providers/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::create
* @see app/Http/Controllers/Tenant/ProviderController.php:83
* @route '/providers/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::create
* @see app/Http/Controllers/Tenant/ProviderController.php:83
* @route '/providers/create'
*/
createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\Tenant\ProviderController::store
* @see app/Http/Controllers/Tenant/ProviderController.php:93
* @route '/providers'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/providers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\ProviderController::store
* @see app/Http/Controllers/Tenant/ProviderController.php:93
* @route '/providers'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProviderController::store
* @see app/Http/Controllers/Tenant/ProviderController.php:93
* @route '/providers'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::store
* @see app/Http/Controllers/Tenant/ProviderController.php:93
* @route '/providers'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::store
* @see app/Http/Controllers/Tenant/ProviderController.php:93
* @route '/providers'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\ProviderController::edit
* @see app/Http/Controllers/Tenant/ProviderController.php:115
* @route '/providers/{provider}/edit'
*/
export const edit = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/providers/{provider}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\ProviderController::edit
* @see app/Http/Controllers/Tenant/ProviderController.php:115
* @route '/providers/{provider}/edit'
*/
edit.url = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { provider: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { provider: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            provider: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        provider: typeof args.provider === 'object'
        ? args.provider.id
        : args.provider,
    }

    return edit.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProviderController::edit
* @see app/Http/Controllers/Tenant/ProviderController.php:115
* @route '/providers/{provider}/edit'
*/
edit.get = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::edit
* @see app/Http/Controllers/Tenant/ProviderController.php:115
* @route '/providers/{provider}/edit'
*/
edit.head = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::edit
* @see app/Http/Controllers/Tenant/ProviderController.php:115
* @route '/providers/{provider}/edit'
*/
const editForm = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::edit
* @see app/Http/Controllers/Tenant/ProviderController.php:115
* @route '/providers/{provider}/edit'
*/
editForm.get = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::edit
* @see app/Http/Controllers/Tenant/ProviderController.php:115
* @route '/providers/{provider}/edit'
*/
editForm.head = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Tenant\ProviderController::update
* @see app/Http/Controllers/Tenant/ProviderController.php:136
* @route '/providers/{provider}'
*/
export const update = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/providers/{provider}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\ProviderController::update
* @see app/Http/Controllers/Tenant/ProviderController.php:136
* @route '/providers/{provider}'
*/
update.url = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { provider: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { provider: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            provider: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        provider: typeof args.provider === 'object'
        ? args.provider.id
        : args.provider,
    }

    return update.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProviderController::update
* @see app/Http/Controllers/Tenant/ProviderController.php:136
* @route '/providers/{provider}'
*/
update.put = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::update
* @see app/Http/Controllers/Tenant/ProviderController.php:136
* @route '/providers/{provider}'
*/
update.patch = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::update
* @see app/Http/Controllers/Tenant/ProviderController.php:136
* @route '/providers/{provider}'
*/
const updateForm = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::update
* @see app/Http/Controllers/Tenant/ProviderController.php:136
* @route '/providers/{provider}'
*/
updateForm.put = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::update
* @see app/Http/Controllers/Tenant/ProviderController.php:136
* @route '/providers/{provider}'
*/
updateForm.patch = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Tenant\ProviderController::destroy
* @see app/Http/Controllers/Tenant/ProviderController.php:157
* @route '/providers/{provider}'
*/
export const destroy = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/providers/{provider}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\ProviderController::destroy
* @see app/Http/Controllers/Tenant/ProviderController.php:157
* @route '/providers/{provider}'
*/
destroy.url = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { provider: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { provider: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            provider: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        provider: typeof args.provider === 'object'
        ? args.provider.id
        : args.provider,
    }

    return destroy.definition.url
            .replace('{provider}', parsedArgs.provider.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\ProviderController::destroy
* @see app/Http/Controllers/Tenant/ProviderController.php:157
* @route '/providers/{provider}'
*/
destroy.delete = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::destroy
* @see app/Http/Controllers/Tenant/ProviderController.php:157
* @route '/providers/{provider}'
*/
const destroyForm = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\ProviderController::destroy
* @see app/Http/Controllers/Tenant/ProviderController.php:157
* @route '/providers/{provider}'
*/
destroyForm.delete = (args: { provider: string | { id: string } } | [provider: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const ProviderController = { index, create, store, edit, update, destroy }

export default ProviderController