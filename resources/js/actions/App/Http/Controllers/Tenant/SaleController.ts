import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\SaleController::index
* @see app/Http/Controllers/Tenant/SaleController.php:27
* @route '/sales'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/sales',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SaleController::index
* @see app/Http/Controllers/Tenant/SaleController.php:27
* @route '/sales'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SaleController::index
* @see app/Http/Controllers/Tenant/SaleController.php:27
* @route '/sales'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::index
* @see app/Http/Controllers/Tenant/SaleController.php:27
* @route '/sales'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::index
* @see app/Http/Controllers/Tenant/SaleController.php:27
* @route '/sales'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::index
* @see app/Http/Controllers/Tenant/SaleController.php:27
* @route '/sales'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::index
* @see app/Http/Controllers/Tenant/SaleController.php:27
* @route '/sales'
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
* @see \App\Http\Controllers\Tenant\SaleController::create
* @see app/Http/Controllers/Tenant/SaleController.php:128
* @route '/sales/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/sales/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SaleController::create
* @see app/Http/Controllers/Tenant/SaleController.php:128
* @route '/sales/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SaleController::create
* @see app/Http/Controllers/Tenant/SaleController.php:128
* @route '/sales/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::create
* @see app/Http/Controllers/Tenant/SaleController.php:128
* @route '/sales/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::create
* @see app/Http/Controllers/Tenant/SaleController.php:128
* @route '/sales/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::create
* @see app/Http/Controllers/Tenant/SaleController.php:128
* @route '/sales/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::create
* @see app/Http/Controllers/Tenant/SaleController.php:128
* @route '/sales/create'
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
* @see \App\Http\Controllers\Tenant\SaleController::store
* @see app/Http/Controllers/Tenant/SaleController.php:138
* @route '/sales'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/sales',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\SaleController::store
* @see app/Http/Controllers/Tenant/SaleController.php:138
* @route '/sales'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SaleController::store
* @see app/Http/Controllers/Tenant/SaleController.php:138
* @route '/sales'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::store
* @see app/Http/Controllers/Tenant/SaleController.php:138
* @route '/sales'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::store
* @see app/Http/Controllers/Tenant/SaleController.php:138
* @route '/sales'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\SaleController::edit
* @see app/Http/Controllers/Tenant/SaleController.php:152
* @route '/sales/{sale}/edit'
*/
export const edit = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/sales/{sale}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SaleController::edit
* @see app/Http/Controllers/Tenant/SaleController.php:152
* @route '/sales/{sale}/edit'
*/
edit.url = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: args.sale,
    }

    return edit.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SaleController::edit
* @see app/Http/Controllers/Tenant/SaleController.php:152
* @route '/sales/{sale}/edit'
*/
edit.get = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::edit
* @see app/Http/Controllers/Tenant/SaleController.php:152
* @route '/sales/{sale}/edit'
*/
edit.head = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::edit
* @see app/Http/Controllers/Tenant/SaleController.php:152
* @route '/sales/{sale}/edit'
*/
const editForm = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::edit
* @see app/Http/Controllers/Tenant/SaleController.php:152
* @route '/sales/{sale}/edit'
*/
editForm.get = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::edit
* @see app/Http/Controllers/Tenant/SaleController.php:152
* @route '/sales/{sale}/edit'
*/
editForm.head = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\SaleController::update
* @see app/Http/Controllers/Tenant/SaleController.php:178
* @route '/sales/{sale}'
*/
export const update = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/sales/{sale}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\SaleController::update
* @see app/Http/Controllers/Tenant/SaleController.php:178
* @route '/sales/{sale}'
*/
update.url = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: args.sale,
    }

    return update.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SaleController::update
* @see app/Http/Controllers/Tenant/SaleController.php:178
* @route '/sales/{sale}'
*/
update.put = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::update
* @see app/Http/Controllers/Tenant/SaleController.php:178
* @route '/sales/{sale}'
*/
update.patch = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::update
* @see app/Http/Controllers/Tenant/SaleController.php:178
* @route '/sales/{sale}'
*/
const updateForm = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::update
* @see app/Http/Controllers/Tenant/SaleController.php:178
* @route '/sales/{sale}'
*/
updateForm.put = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::update
* @see app/Http/Controllers/Tenant/SaleController.php:178
* @route '/sales/{sale}'
*/
updateForm.patch = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\SaleController::destroy
* @see app/Http/Controllers/Tenant/SaleController.php:193
* @route '/sales/{sale}'
*/
export const destroy = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/sales/{sale}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\SaleController::destroy
* @see app/Http/Controllers/Tenant/SaleController.php:193
* @route '/sales/{sale}'
*/
destroy.url = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { sale: args }
    }

    if (Array.isArray(args)) {
        args = {
            sale: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        sale: args.sale,
    }

    return destroy.definition.url
            .replace('{sale}', parsedArgs.sale.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SaleController::destroy
* @see app/Http/Controllers/Tenant/SaleController.php:193
* @route '/sales/{sale}'
*/
destroy.delete = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::destroy
* @see app/Http/Controllers/Tenant/SaleController.php:193
* @route '/sales/{sale}'
*/
const destroyForm = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SaleController::destroy
* @see app/Http/Controllers/Tenant/SaleController.php:193
* @route '/sales/{sale}'
*/
destroyForm.delete = (args: { sale: string | number } | [sale: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const SaleController = { index, create, store, edit, update, destroy }

export default SaleController