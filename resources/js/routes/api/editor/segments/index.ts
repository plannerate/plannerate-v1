import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import notes from './notes'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentController.php:17
* @route '/api/editor/segments/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/editor/segments/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentController.php:17
* @route '/api/editor/segments/{id}'
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentController.php:17
* @route '/api/editor/segments/{id}'
*/
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentController.php:17
* @route '/api/editor/segments/{id}'
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SegmentController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SegmentController.php:17
* @route '/api/editor/segments/{id}'
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

const segments = {
    notes: Object.assign(notes, notes),
    update: Object.assign(update, update),
}

export default segments