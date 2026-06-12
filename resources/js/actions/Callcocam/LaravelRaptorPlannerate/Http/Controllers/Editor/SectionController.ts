import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:122
* @route '/api/editor/sections/{section}'
*/
export const show = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/editor/sections/{section}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:122
* @route '/api/editor/sections/{section}'
*/
show.url = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:122
* @route '/api/editor/sections/{section}'
*/
show.get = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:122
* @route '/api/editor/sections/{section}'
*/
show.head = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:122
* @route '/api/editor/sections/{section}'
*/
const showForm = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:122
* @route '/api/editor/sections/{section}'
*/
showForm.get = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:122
* @route '/api/editor/sections/{section}'
*/
showForm.head = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:24
* @route '/api/editor/gondolas/{gondola}/sections'
*/
export const store = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/sections',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:24
* @route '/api/editor/gondolas/{gondola}/sections'
*/
store.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return store.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:24
* @route '/api/editor/gondolas/{gondola}/sections'
*/
store.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:24
* @route '/api/editor/gondolas/{gondola}/sections'
*/
const storeForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:24
* @route '/api/editor/gondolas/{gondola}/sections'
*/
storeForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:93
* @route '/api/editor/sections/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/editor/sections/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:93
* @route '/api/editor/sections/{id}'
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:93
* @route '/api/editor/sections/{id}'
*/
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:93
* @route '/api/editor/sections/{id}'
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:93
* @route '/api/editor/sections/{id}'
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:145
* @route '/api/editor/sections/{section}'
*/
export const destroy = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/editor/sections/{section}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:145
* @route '/api/editor/sections/{section}'
*/
destroy.url = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:145
* @route '/api/editor/sections/{section}'
*/
destroy.delete = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:145
* @route '/api/editor/sections/{section}'
*/
const destroyForm = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:145
* @route '/api/editor/sections/{section}'
*/
destroyForm.delete = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::transfer
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:167
* @route '/api/editor/sections/{section}/transfer'
*/
export const transfer = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transfer.url(args, options),
    method: 'post',
})

transfer.definition = {
    methods: ["post"],
    url: '/api/editor/sections/{section}/transfer',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::transfer
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:167
* @route '/api/editor/sections/{section}/transfer'
*/
transfer.url = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return transfer.definition.url
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::transfer
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:167
* @route '/api/editor/sections/{section}/transfer'
*/
transfer.post = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transfer.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::transfer
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:167
* @route '/api/editor/sections/{section}/transfer'
*/
const transferForm = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: transfer.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SectionController::transfer
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SectionController.php:167
* @route '/api/editor/sections/{section}/transfer'
*/
transferForm.post = (args: { section: string | number } | [section: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: transfer.url(args, options),
    method: 'post',
})

transfer.form = transferForm

const SectionController = { show, store, update, destroy, transfer }

export default SectionController