import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:19
* @route '/api/editor/segments/{segment}/notes'
*/
export const index = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/editor/segments/{segment}/notes',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:19
* @route '/api/editor/segments/{segment}/notes'
*/
index.url = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { segment: args }
    }

    if (Array.isArray(args)) {
        args = {
            segment: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        segment: args.segment,
    }

    return index.definition.url
            .replace('{segment}', parsedArgs.segment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:19
* @route '/api/editor/segments/{segment}/notes'
*/
index.get = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:19
* @route '/api/editor/segments/{segment}/notes'
*/
index.head = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:19
* @route '/api/editor/segments/{segment}/notes'
*/
const indexForm = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:19
* @route '/api/editor/segments/{segment}/notes'
*/
indexForm.get = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:19
* @route '/api/editor/segments/{segment}/notes'
*/
indexForm.head = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:36
* @route '/api/editor/segments/{segment}/notes'
*/
export const store = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/editor/segments/{segment}/notes',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:36
* @route '/api/editor/segments/{segment}/notes'
*/
store.url = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { segment: args }
    }

    if (Array.isArray(args)) {
        args = {
            segment: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        segment: args.segment,
    }

    return store.definition.url
            .replace('{segment}', parsedArgs.segment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:36
* @route '/api/editor/segments/{segment}/notes'
*/
store.post = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:36
* @route '/api/editor/segments/{segment}/notes'
*/
const storeForm = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentNoteController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentNoteController.php:36
* @route '/api/editor/segments/{segment}/notes'
*/
storeForm.post = (args: { segment: string | number } | [segment: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

const SegmentNoteController = { index, store }

export default SegmentNoteController