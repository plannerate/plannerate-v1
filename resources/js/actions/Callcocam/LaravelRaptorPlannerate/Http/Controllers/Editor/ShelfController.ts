import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:38
* @route '/api/editor/sections/{section}/shelves'
*/
export const store = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/editor/sections/{section}/shelves',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:38
* @route '/api/editor/sections/{section}/shelves'
*/
store.url = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { section: args }
    }

    if (Array.isArray(args)) {
        args = {
            section: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        section: args.section,
    }

    return store.definition.url
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:38
* @route '/api/editor/sections/{section}/shelves'
*/
store.post = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:38
* @route '/api/editor/sections/{section}/shelves'
*/
const storeForm = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:38
* @route '/api/editor/sections/{section}/shelves'
*/
storeForm.post = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:18
* @route '/api/editor/shelves/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/editor/shelves/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:18
* @route '/api/editor/shelves/{id}'
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:18
* @route '/api/editor/shelves/{id}'
*/
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:18
* @route '/api/editor/shelves/{id}'
*/
const updateForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:18
* @route '/api/editor/shelves/{id}'
*/
updateForm.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:71
* @route '/api/editor/shelves/{shelf}'
*/
export const destroy = (args: { shelf: string | number } | [shelf: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/editor/shelves/{shelf}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:71
* @route '/api/editor/shelves/{shelf}'
*/
destroy.url = (args: { shelf: string | number } | [shelf: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { shelf: args }
    }

    if (Array.isArray(args)) {
        args = {
            shelf: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        shelf: args.shelf,
    }

    return destroy.definition.url
            .replace('{shelf}', parsedArgs.shelf.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:71
* @route '/api/editor/shelves/{shelf}'
*/
destroy.delete = (args: { shelf: string | number } | [shelf: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:71
* @route '/api/editor/shelves/{shelf}'
*/
const destroyForm = (args: { shelf: string | number } | [shelf: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ShelfController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ShelfController.php:71
* @route '/api/editor/shelves/{shelf}'
*/
destroyForm.delete = (args: { shelf: string | number } | [shelf: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const ShelfController = { store, update, destroy }

export default ShelfController