import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::update
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
*/
export const update = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::update
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
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
* @see \App\Http\Controllers\Tenant\TemplateSlotController::update
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
*/
update.put = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::update
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
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
* @see \App\Http\Controllers\Tenant\TemplateSlotController::update
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:148
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/slot-defaults'
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

const slotDefaults = {
    update: Object.assign(update, update),
}

export default slotDefaults