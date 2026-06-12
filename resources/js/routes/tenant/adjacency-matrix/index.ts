import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::edit
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:20
* @route '/settings/adjacency-matrix'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/adjacency-matrix',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::edit
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:20
* @route '/settings/adjacency-matrix'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::edit
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:20
* @route '/settings/adjacency-matrix'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::edit
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:20
* @route '/settings/adjacency-matrix'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::edit
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:20
* @route '/settings/adjacency-matrix'
*/
const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::edit
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:20
* @route '/settings/adjacency-matrix'
*/
editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::edit
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:20
* @route '/settings/adjacency-matrix'
*/
editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::store
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:57
* @route '/settings/adjacency-matrix'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/adjacency-matrix',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::store
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:57
* @route '/settings/adjacency-matrix'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::store
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:57
* @route '/settings/adjacency-matrix'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::store
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:57
* @route '/settings/adjacency-matrix'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::store
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:57
* @route '/settings/adjacency-matrix'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::update
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:66
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
export const update = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/adjacency-matrix/{adjacencyRule}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::update
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:66
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
update.url = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { adjacencyRule: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { adjacencyRule: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            adjacencyRule: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        adjacencyRule: typeof args.adjacencyRule === 'object'
        ? args.adjacencyRule.id
        : args.adjacencyRule,
    }

    return update.definition.url
            .replace('{adjacencyRule}', parsedArgs.adjacencyRule.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::update
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:66
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
update.put = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::update
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:66
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
const updateForm = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::update
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:66
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
updateForm.put = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::destroy
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:75
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
export const destroy = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/adjacency-matrix/{adjacencyRule}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::destroy
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:75
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
destroy.url = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { adjacencyRule: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { adjacencyRule: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            adjacencyRule: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        adjacencyRule: typeof args.adjacencyRule === 'object'
        ? args.adjacencyRule.id
        : args.adjacencyRule,
    }

    return destroy.definition.url
            .replace('{adjacencyRule}', parsedArgs.adjacencyRule.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::destroy
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:75
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
destroy.delete = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::destroy
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:75
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
const destroyForm = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\AdjacencyMatrixController::destroy
* @see app/Http/Controllers/Settings/AdjacencyMatrixController.php:75
* @route '/settings/adjacency-matrix/{adjacencyRule}'
*/
destroyForm.delete = (args: { adjacencyRule: string | number | { id: string | number } } | [adjacencyRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const adjacencyMatrix = {
    edit: Object.assign(edit, edit),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default adjacencyMatrix