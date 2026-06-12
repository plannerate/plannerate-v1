import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
import slotDefaults from './slot-defaults'
/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::store
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
export const store = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::store
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
store.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return store.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::store
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
store.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::store
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
const storeForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::store
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:96
* @route '/planogram-templates/{planogramTemplate}/subtemplates'
*/
storeForm.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::clone
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
export const clone = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: clone.url(args, options),
    method: 'post',
})

clone.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::clone
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
clone.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return clone.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::clone
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
clone.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: clone.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::clone
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
const cloneForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clone.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::clone
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:115
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}/clone'
*/
cloneForm.post = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: clone.url(args, options),
    method: 'post',
})

clone.form = cloneForm

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroy
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
export const destroy = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroy
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
destroy.url = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace('{planogramSubtemplate}', parsedArgs.planogramSubtemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroy
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
destroy.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroy
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
const destroyForm = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\TemplateSlotController::destroy
* @see app/Http/Controllers/Tenant/TemplateSlotController.php:134
* @route '/planogram-templates/{planogramTemplate}/subtemplates/{planogramSubtemplate}'
*/
destroyForm.delete = (args: { planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number }, planogramSubtemplate: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const subtemplates = {
    store: Object.assign(store, store),
    clone: Object.assign(clone, clone),
    slotDefaults: Object.assign(slotDefaults, slotDefaults),
    destroy: Object.assign(destroy, destroy),
}

export default subtemplates