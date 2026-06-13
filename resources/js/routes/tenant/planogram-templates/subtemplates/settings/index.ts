import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:165
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/settings'
*/
export const update = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/settings',
} satisfies RouteDefinition<["put"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:165
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/settings'
*/
update.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
            planogramSubtemplate: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
        planogramSubtemplate: typeof args.planogramSubtemplate === 'object'
        ? args.planogramSubtemplate.id
        : args.planogramSubtemplate,
    }

    return update.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:165
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/settings'
*/
update.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:165
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/settings'
*/
const updateForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Templates\TemplateSlotController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Templates/TemplateSlotController.php:165
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/settings'
*/
updateForm.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

const settings = {
    update: Object.assign(update, update),
}

export default settings