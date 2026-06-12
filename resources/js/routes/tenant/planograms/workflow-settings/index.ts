import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::index
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:21
* @route '/planograms/{planogram}/workflow-settings'
*/
export const index = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/planograms/{planogram}/workflow-settings',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::index
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:21
* @route '/planograms/{planogram}/workflow-settings'
*/
index.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogram: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
    }

    return index.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::index
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:21
* @route '/planograms/{planogram}/workflow-settings'
*/
index.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::index
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:21
* @route '/planograms/{planogram}/workflow-settings'
*/
index.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::index
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:21
* @route '/planograms/{planogram}/workflow-settings'
*/
const indexForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::index
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:21
* @route '/planograms/{planogram}/workflow-settings'
*/
indexForm.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::index
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:21
* @route '/planograms/{planogram}/workflow-settings'
*/
indexForm.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::update
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:33
* @route '/planograms/{planogram}/workflow-settings'
*/
export const update = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/planograms/{planogram}/workflow-settings',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::update
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:33
* @route '/planograms/{planogram}/workflow-settings'
*/
update.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogram: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
    }

    return update.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::update
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:33
* @route '/planograms/{planogram}/workflow-settings'
*/
update.put = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::update
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:33
* @route '/planograms/{planogram}/workflow-settings'
*/
const updateForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::update
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:33
* @route '/planograms/{planogram}/workflow-settings'
*/
updateForm.put = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::loadDefaults
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:45
* @route '/planograms/{planogram}/workflow-settings/load-defaults'
*/
export const loadDefaults = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loadDefaults.url(args, options),
    method: 'post',
})

loadDefaults.definition = {
    methods: ["post"],
    url: '/planograms/{planogram}/workflow-settings/load-defaults',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::loadDefaults
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:45
* @route '/planograms/{planogram}/workflow-settings/load-defaults'
*/
loadDefaults.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogram: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
    }

    return loadDefaults.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::loadDefaults
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:45
* @route '/planograms/{planogram}/workflow-settings/load-defaults'
*/
loadDefaults.post = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: loadDefaults.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::loadDefaults
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:45
* @route '/planograms/{planogram}/workflow-settings/load-defaults'
*/
const loadDefaultsForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: loadDefaults.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowPlanogramStepController::loadDefaults
* @see app/Http/Controllers/Tenant/WorkflowPlanogramStepController.php:45
* @route '/planograms/{planogram}/workflow-settings/load-defaults'
*/
loadDefaultsForm.post = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: loadDefaults.url(args, options),
    method: 'post',
})

loadDefaults.form = loadDefaultsForm

const workflowSettings = {
    index: Object.assign(index, index),
    update: Object.assign(update, update),
    loadDefaults: Object.assign(loadDefaults, loadDefaults),
}

export default workflowSettings