import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::productSearch
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
export const productSearch = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: productSearch.url(options),
    method: 'get',
})

productSearch.definition = {
    methods: ["get","head"],
    url: '/similar-groups/products/search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::productSearch
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
productSearch.url = (options?: RouteQueryOptions) => {
    return productSearch.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::productSearch
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
productSearch.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: productSearch.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::productSearch
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
productSearch.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: productSearch.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::productSearch
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
const productSearchForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: productSearch.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::productSearch
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
productSearchForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: productSearch.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::productSearch
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:86
* @route '/similar-groups/products/search'
*/
productSearchForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: productSearch.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

productSearch.form = productSearchForm

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::index
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:33
* @route '/similar-groups'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/similar-groups',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::index
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:33
* @route '/similar-groups'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::index
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:33
* @route '/similar-groups'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::index
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:33
* @route '/similar-groups'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::index
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:33
* @route '/similar-groups'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::index
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:33
* @route '/similar-groups'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::index
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:33
* @route '/similar-groups'
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
* @see \App\Http\Controllers\Tenant\SimilarGroupController::create
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:97
* @route '/similar-groups/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/similar-groups/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::create
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:97
* @route '/similar-groups/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::create
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:97
* @route '/similar-groups/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::create
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:97
* @route '/similar-groups/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::create
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:97
* @route '/similar-groups/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::create
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:97
* @route '/similar-groups/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::create
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:97
* @route '/similar-groups/create'
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
* @see \App\Http\Controllers\Tenant\SimilarGroupController::store
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:108
* @route '/similar-groups'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/similar-groups',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::store
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:108
* @route '/similar-groups'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::store
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:108
* @route '/similar-groups'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::store
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:108
* @route '/similar-groups'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::store
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:108
* @route '/similar-groups'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::edit
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:132
* @route '/similar-groups/{similar_group}/edit'
*/
export const edit = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/similar-groups/{similar_group}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::edit
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:132
* @route '/similar-groups/{similar_group}/edit'
*/
edit.url = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { similar_group: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { similar_group: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            similar_group: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        similar_group: typeof args.similar_group === 'object'
        ? args.similar_group.id
        : args.similar_group,
    }

    return edit.definition.url
            .replace('{similar_group}', parsedArgs.similar_group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::edit
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:132
* @route '/similar-groups/{similar_group}/edit'
*/
edit.get = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::edit
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:132
* @route '/similar-groups/{similar_group}/edit'
*/
edit.head = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::edit
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:132
* @route '/similar-groups/{similar_group}/edit'
*/
const editForm = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::edit
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:132
* @route '/similar-groups/{similar_group}/edit'
*/
editForm.get = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::edit
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:132
* @route '/similar-groups/{similar_group}/edit'
*/
editForm.head = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\SimilarGroupController::update
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:154
* @route '/similar-groups/{similar_group}'
*/
export const update = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/similar-groups/{similar_group}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::update
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:154
* @route '/similar-groups/{similar_group}'
*/
update.url = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { similar_group: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { similar_group: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            similar_group: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        similar_group: typeof args.similar_group === 'object'
        ? args.similar_group.id
        : args.similar_group,
    }

    return update.definition.url
            .replace('{similar_group}', parsedArgs.similar_group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::update
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:154
* @route '/similar-groups/{similar_group}'
*/
update.put = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::update
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:154
* @route '/similar-groups/{similar_group}'
*/
update.patch = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::update
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:154
* @route '/similar-groups/{similar_group}'
*/
const updateForm = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::update
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:154
* @route '/similar-groups/{similar_group}'
*/
updateForm.put = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::update
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:154
* @route '/similar-groups/{similar_group}'
*/
updateForm.patch = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\SimilarGroupController::destroy
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:178
* @route '/similar-groups/{similar_group}'
*/
export const destroy = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/similar-groups/{similar_group}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::destroy
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:178
* @route '/similar-groups/{similar_group}'
*/
destroy.url = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { similar_group: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { similar_group: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            similar_group: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        similar_group: typeof args.similar_group === 'object'
        ? args.similar_group.id
        : args.similar_group,
    }

    return destroy.definition.url
            .replace('{similar_group}', parsedArgs.similar_group.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::destroy
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:178
* @route '/similar-groups/{similar_group}'
*/
destroy.delete = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::destroy
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:178
* @route '/similar-groups/{similar_group}'
*/
const destroyForm = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\SimilarGroupController::destroy
* @see app/Http/Controllers/Tenant/SimilarGroupController.php:178
* @route '/similar-groups/{similar_group}'
*/
destroyForm.delete = (args: { similar_group: string | number | { id: string | number } } | [similar_group: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const SimilarGroupController = { productSearch, index, create, store, edit, update, destroy }

export default SimilarGroupController