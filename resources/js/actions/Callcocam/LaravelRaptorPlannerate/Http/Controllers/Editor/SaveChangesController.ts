import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SaveChangesController::__invoke
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SaveChangesController.php:57
* @route '/api/editor/gondolas/{gondola}/save-changes'
*/
const SaveChangesController = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: SaveChangesController.url(args, options),
    method: 'post',
})

SaveChangesController.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/save-changes',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SaveChangesController::__invoke
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SaveChangesController.php:57
* @route '/api/editor/gondolas/{gondola}/save-changes'
*/
SaveChangesController.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return SaveChangesController.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SaveChangesController::__invoke
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SaveChangesController.php:57
* @route '/api/editor/gondolas/{gondola}/save-changes'
*/
SaveChangesController.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: SaveChangesController.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SaveChangesController::__invoke
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SaveChangesController.php:57
* @route '/api/editor/gondolas/{gondola}/save-changes'
*/
const SaveChangesControllerForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: SaveChangesController.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\SaveChangesController::__invoke
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/SaveChangesController.php:57
* @route '/api/editor/gondolas/{gondola}/save-changes'
*/
SaveChangesControllerForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: SaveChangesController.url(args, options),
    method: 'post',
})

SaveChangesController.form = SaveChangesControllerForm

export default SaveChangesController