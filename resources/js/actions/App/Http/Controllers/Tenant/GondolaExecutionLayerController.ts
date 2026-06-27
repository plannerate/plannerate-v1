import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
export const storeEvidence = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeEvidence.url(args, options),
    method: 'post',
})

storeEvidence.definition = {
    methods: ["post"],
    url: '/executions/{execution}/evidences',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
storeEvidence.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { execution: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { execution: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            execution: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
    }

    return storeEvidence.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
storeEvidence.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeEvidence.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
const storeEvidenceForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeEvidence.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
storeEvidenceForm.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeEvidence.url(args, options),
    method: 'post',
})

storeEvidence.form = storeEvidenceForm

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroyEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
export const destroyEvidence = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyEvidence.url(args, options),
    method: 'delete',
})

destroyEvidence.definition = {
    methods: ["delete"],
    url: '/executions/{execution}/evidences/{evidence}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroyEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
destroyEvidence.url = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            execution: args[0],
            evidence: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
        evidence: typeof args.evidence === 'object'
        ? args.evidence.id
        : args.evidence,
    }

    return destroyEvidence.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace('{evidence}', parsedArgs.evidence.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroyEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
destroyEvidence.delete = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroyEvidence.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroyEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
const destroyEvidenceForm = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroyEvidence.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroyEvidence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
destroyEvidenceForm.delete = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroyEvidence.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroyEvidence.form = destroyEvidenceForm

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
export const storeDivergence = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeDivergence.url(args, options),
    method: 'post',
})

storeDivergence.definition = {
    methods: ["post"],
    url: '/executions/{execution}/divergences',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
storeDivergence.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { execution: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { execution: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            execution: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
    }

    return storeDivergence.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
storeDivergence.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeDivergence.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
const storeDivergenceForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeDivergence.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::storeDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
storeDivergenceForm.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeDivergence.url(args, options),
    method: 'post',
})

storeDivergence.form = storeDivergenceForm

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::updateDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
export const updateDivergence = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateDivergence.url(args, options),
    method: 'patch',
})

updateDivergence.definition = {
    methods: ["patch"],
    url: '/executions/{execution}/divergences/{divergence}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::updateDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
updateDivergence.url = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            execution: args[0],
            divergence: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
        divergence: typeof args.divergence === 'object'
        ? args.divergence.id
        : args.divergence,
    }

    return updateDivergence.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace('{divergence}', parsedArgs.divergence.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::updateDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
updateDivergence.patch = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: updateDivergence.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::updateDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
const updateDivergenceForm = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateDivergence.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::updateDivergence
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
updateDivergenceForm.patch = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateDivergence.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

updateDivergence.form = updateDivergenceForm

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::complete
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:130
* @route '/executions/{execution}/complete'
*/
export const complete = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

complete.definition = {
    methods: ["post"],
    url: '/executions/{execution}/complete',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::complete
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:130
* @route '/executions/{execution}/complete'
*/
complete.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { execution: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { execution: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            execution: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
    }

    return complete.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::complete
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:130
* @route '/executions/{execution}/complete'
*/
complete.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::complete
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:130
* @route '/executions/{execution}/complete'
*/
const completeForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::complete
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:130
* @route '/executions/{execution}/complete'
*/
completeForm.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(args, options),
    method: 'post',
})

complete.form = completeForm

const GondolaExecutionLayerController = { storeEvidence, destroyEvidence, storeDivergence, updateDivergence, complete }

export default GondolaExecutionLayerController