import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PlanogramProductRuleController::index
* @see app/Http/Controllers/PlanogramProductRuleController.php:14
* @route '/planogram-product-rules'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/planogram-product-rules',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::index
* @see app/Http/Controllers/PlanogramProductRuleController.php:14
* @route '/planogram-product-rules'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::index
* @see app/Http/Controllers/PlanogramProductRuleController.php:14
* @route '/planogram-product-rules'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::index
* @see app/Http/Controllers/PlanogramProductRuleController.php:14
* @route '/planogram-product-rules'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::index
* @see app/Http/Controllers/PlanogramProductRuleController.php:14
* @route '/planogram-product-rules'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::index
* @see app/Http/Controllers/PlanogramProductRuleController.php:14
* @route '/planogram-product-rules'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::index
* @see app/Http/Controllers/PlanogramProductRuleController.php:14
* @route '/planogram-product-rules'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::store
* @see app/Http/Controllers/PlanogramProductRuleController.php:43
* @route '/planogram-product-rules'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/planogram-product-rules',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::store
* @see app/Http/Controllers/PlanogramProductRuleController.php:43
* @route '/planogram-product-rules'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::store
* @see app/Http/Controllers/PlanogramProductRuleController.php:43
* @route '/planogram-product-rules'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::store
* @see app/Http/Controllers/PlanogramProductRuleController.php:43
* @route '/planogram-product-rules'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::store
* @see app/Http/Controllers/PlanogramProductRuleController.php:43
* @route '/planogram-product-rules'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::destroy
* @see app/Http/Controllers/PlanogramProductRuleController.php:72
* @route '/planogram-product-rules/{planogramProductRule}'
*/
export const destroy = (args: { planogramProductRule: string | number | { id: string | number } } | [planogramProductRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/planogram-product-rules/{planogramProductRule}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::destroy
* @see app/Http/Controllers/PlanogramProductRuleController.php:72
* @route '/planogram-product-rules/{planogramProductRule}'
*/
destroy.url = (args: { planogramProductRule: string | number | { id: string | number } } | [planogramProductRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramProductRule: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramProductRule: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramProductRule: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramProductRule: typeof args.planogramProductRule === 'object'
        ? args.planogramProductRule.id
        : args.planogramProductRule,
    }

    return destroy.definition.url
            .replace('{planogramProductRule}', parsedArgs.planogramProductRule.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::destroy
* @see app/Http/Controllers/PlanogramProductRuleController.php:72
* @route '/planogram-product-rules/{planogramProductRule}'
*/
destroy.delete = (args: { planogramProductRule: string | number | { id: string | number } } | [planogramProductRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::destroy
* @see app/Http/Controllers/PlanogramProductRuleController.php:72
* @route '/planogram-product-rules/{planogramProductRule}'
*/
const destroyForm = (args: { planogramProductRule: string | number | { id: string | number } } | [planogramProductRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\PlanogramProductRuleController::destroy
* @see app/Http/Controllers/PlanogramProductRuleController.php:72
* @route '/planogram-product-rules/{planogramProductRule}'
*/
destroyForm.delete = (args: { planogramProductRule: string | number | { id: string | number } } | [planogramProductRule: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const planogramProductRules = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default planogramProductRules