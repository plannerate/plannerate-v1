import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::index
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:18
* @route '/products/dimensions'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/products/dimensions',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::index
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:18
* @route '/products/dimensions'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::index
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:18
* @route '/products/dimensions'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::index
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:18
* @route '/products/dimensions'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::index
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:18
* @route '/products/dimensions'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::index
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:18
* @route '/products/dimensions'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::index
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:18
* @route '/products/dimensions'
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
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approve
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:88
* @route '/products/dimensions/{product}/approve'
*/
export const approve = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

approve.definition = {
    methods: ["post"],
    url: '/products/dimensions/{product}/approve',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approve
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:88
* @route '/products/dimensions/{product}/approve'
*/
approve.url = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { product: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        product: typeof args.product === 'object'
        ? args.product.id
        : args.product,
    }

    return approve.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approve
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:88
* @route '/products/dimensions/{product}/approve'
*/
approve.post = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approve.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approve
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:88
* @route '/products/dimensions/{product}/approve'
*/
const approveForm = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approve.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approve
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:88
* @route '/products/dimensions/{product}/approve'
*/
approveForm.post = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approve.url(args, options),
    method: 'post',
})

approve.form = approveForm

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::reject
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:99
* @route '/products/dimensions/{product}/reject'
*/
export const reject = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

reject.definition = {
    methods: ["post"],
    url: '/products/dimensions/{product}/reject',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::reject
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:99
* @route '/products/dimensions/{product}/reject'
*/
reject.url = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { product: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        product: typeof args.product === 'object'
        ? args.product.id
        : args.product,
    }

    return reject.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::reject
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:99
* @route '/products/dimensions/{product}/reject'
*/
reject.post = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reject.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::reject
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:99
* @route '/products/dimensions/{product}/reject'
*/
const rejectForm = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reject.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::reject
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:99
* @route '/products/dimensions/{product}/reject'
*/
rejectForm.post = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reject.url(args, options),
    method: 'post',
})

reject.form = rejectForm

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::research
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:114
* @route '/products/dimensions/{product}/research'
*/
export const research = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: research.url(args, options),
    method: 'post',
})

research.definition = {
    methods: ["post"],
    url: '/products/dimensions/{product}/research',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::research
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:114
* @route '/products/dimensions/{product}/research'
*/
research.url = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { product: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { product: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            product: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        product: typeof args.product === 'object'
        ? args.product.id
        : args.product,
    }

    return research.definition.url
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::research
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:114
* @route '/products/dimensions/{product}/research'
*/
research.post = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: research.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::research
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:114
* @route '/products/dimensions/{product}/research'
*/
const researchForm = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: research.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::research
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:114
* @route '/products/dimensions/{product}/research'
*/
researchForm.post = (args: { product: string | { id: string } } | [product: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: research.url(args, options),
    method: 'post',
})

research.form = researchForm

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approveAll
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:125
* @route '/products/dimensions/approve-all'
*/
export const approveAll = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approveAll.url(options),
    method: 'post',
})

approveAll.definition = {
    methods: ["post"],
    url: '/products/dimensions/approve-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approveAll
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:125
* @route '/products/dimensions/approve-all'
*/
approveAll.url = (options?: RouteQueryOptions) => {
    return approveAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approveAll
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:125
* @route '/products/dimensions/approve-all'
*/
approveAll.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: approveAll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approveAll
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:125
* @route '/products/dimensions/approve-all'
*/
const approveAllForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approveAll.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\Products\DimensionApprovalController::approveAll
* @see app/Http/Controllers/Tenant/Products/DimensionApprovalController.php:125
* @route '/products/dimensions/approve-all'
*/
approveAllForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: approveAll.url(options),
    method: 'post',
})

approveAll.form = approveAllForm

const DimensionApprovalController = { index, approve, reject, research, approveAll }

export default DimensionApprovalController