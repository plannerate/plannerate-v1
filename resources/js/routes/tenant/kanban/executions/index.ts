import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::store
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:29
* @route '/kanban/{planogram}/executions'
*/
export const store = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/kanban/{planogram}/executions',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::store
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:29
* @route '/kanban/{planogram}/executions'
*/
store.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::store
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:29
* @route '/kanban/{planogram}/executions'
*/
store.post = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::store
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:29
* @route '/kanban/{planogram}/executions'
*/
const storeForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::store
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:29
* @route '/kanban/{planogram}/executions'
*/
storeForm.post = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::details
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:236
* @route '/kanban/executions/{execution}/details'
*/
export const details = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: details.url(args, options),
    method: 'get',
})

details.definition = {
    methods: ["get","head"],
    url: '/kanban/executions/{execution}/details',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::details
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:236
* @route '/kanban/executions/{execution}/details'
*/
details.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return details.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::details
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:236
* @route '/kanban/executions/{execution}/details'
*/
details.get = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: details.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::details
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:236
* @route '/kanban/executions/{execution}/details'
*/
details.head = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: details.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::details
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:236
* @route '/kanban/executions/{execution}/details'
*/
const detailsForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: details.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::details
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:236
* @route '/kanban/executions/{execution}/details'
*/
detailsForm.get = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: details.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::details
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:236
* @route '/kanban/executions/{execution}/details'
*/
detailsForm.head = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: details.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

details.form = detailsForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::start
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:121
* @route '/kanban/executions/{execution}/start'
*/
export const start = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: start.url(args, options),
    method: 'patch',
})

start.definition = {
    methods: ["patch"],
    url: '/kanban/executions/{execution}/start',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::start
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:121
* @route '/kanban/executions/{execution}/start'
*/
start.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return start.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::start
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:121
* @route '/kanban/executions/{execution}/start'
*/
start.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: start.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::start
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:121
* @route '/kanban/executions/{execution}/start'
*/
const startForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: start.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::start
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:121
* @route '/kanban/executions/{execution}/start'
*/
startForm.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: start.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

start.form = startForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::move
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:56
* @route '/kanban/executions/{execution}/move'
*/
export const move = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: move.url(args, options),
    method: 'patch',
})

move.definition = {
    methods: ["patch"],
    url: '/kanban/executions/{execution}/move',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::move
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:56
* @route '/kanban/executions/{execution}/move'
*/
move.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return move.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::move
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:56
* @route '/kanban/executions/{execution}/move'
*/
move.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: move.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::move
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:56
* @route '/kanban/executions/{execution}/move'
*/
const moveForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: move.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::move
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:56
* @route '/kanban/executions/{execution}/move'
*/
moveForm.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: move.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

move.form = moveForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::pause
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:136
* @route '/kanban/executions/{execution}/pause'
*/
export const pause = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: pause.url(args, options),
    method: 'patch',
})

pause.definition = {
    methods: ["patch"],
    url: '/kanban/executions/{execution}/pause',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::pause
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:136
* @route '/kanban/executions/{execution}/pause'
*/
pause.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return pause.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::pause
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:136
* @route '/kanban/executions/{execution}/pause'
*/
pause.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: pause.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::pause
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:136
* @route '/kanban/executions/{execution}/pause'
*/
const pauseForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pause.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::pause
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:136
* @route '/kanban/executions/{execution}/pause'
*/
pauseForm.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pause.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

pause.form = pauseForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::resume
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:185
* @route '/kanban/executions/{execution}/resume'
*/
export const resume = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: resume.url(args, options),
    method: 'patch',
})

resume.definition = {
    methods: ["patch"],
    url: '/kanban/executions/{execution}/resume',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::resume
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:185
* @route '/kanban/executions/{execution}/resume'
*/
resume.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return resume.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::resume
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:185
* @route '/kanban/executions/{execution}/resume'
*/
resume.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: resume.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::resume
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:185
* @route '/kanban/executions/{execution}/resume'
*/
const resumeForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resume.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::resume
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:185
* @route '/kanban/executions/{execution}/resume'
*/
resumeForm.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: resume.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

resume.form = resumeForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::complete
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:200
* @route '/kanban/executions/{execution}/complete'
*/
export const complete = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: complete.url(args, options),
    method: 'patch',
})

complete.definition = {
    methods: ["patch"],
    url: '/kanban/executions/{execution}/complete',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::complete
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:200
* @route '/kanban/executions/{execution}/complete'
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
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::complete
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:200
* @route '/kanban/executions/{execution}/complete'
*/
complete.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: complete.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::complete
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:200
* @route '/kanban/executions/{execution}/complete'
*/
const completeForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::complete
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:200
* @route '/kanban/executions/{execution}/complete'
*/
completeForm.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: complete.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

complete.form = completeForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::abandon
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:151
* @route '/kanban/executions/{execution}/abandon'
*/
export const abandon = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: abandon.url(args, options),
    method: 'patch',
})

abandon.definition = {
    methods: ["patch"],
    url: '/kanban/executions/{execution}/abandon',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::abandon
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:151
* @route '/kanban/executions/{execution}/abandon'
*/
abandon.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return abandon.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::abandon
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:151
* @route '/kanban/executions/{execution}/abandon'
*/
abandon.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: abandon.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::abandon
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:151
* @route '/kanban/executions/{execution}/abandon'
*/
const abandonForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: abandon.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::abandon
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:151
* @route '/kanban/executions/{execution}/abandon'
*/
abandonForm.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: abandon.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

abandon.form = abandonForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::requestAbandonment
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:166
* @route '/kanban/executions/{execution}/request-abandonment'
*/
export const requestAbandonment = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAbandonment.url(args, options),
    method: 'post',
})

requestAbandonment.definition = {
    methods: ["post"],
    url: '/kanban/executions/{execution}/request-abandonment',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::requestAbandonment
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:166
* @route '/kanban/executions/{execution}/request-abandonment'
*/
requestAbandonment.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return requestAbandonment.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::requestAbandonment
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:166
* @route '/kanban/executions/{execution}/request-abandonment'
*/
requestAbandonment.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: requestAbandonment.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::requestAbandonment
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:166
* @route '/kanban/executions/{execution}/request-abandonment'
*/
const requestAbandonmentForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: requestAbandonment.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::requestAbandonment
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:166
* @route '/kanban/executions/{execution}/request-abandonment'
*/
requestAbandonmentForm.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: requestAbandonment.url(args, options),
    method: 'post',
})

requestAbandonment.form = requestAbandonmentForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::assign
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:215
* @route '/kanban/executions/{execution}/assign'
*/
export const assign = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

assign.definition = {
    methods: ["patch"],
    url: '/kanban/executions/{execution}/assign',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::assign
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:215
* @route '/kanban/executions/{execution}/assign'
*/
assign.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return assign.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::assign
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:215
* @route '/kanban/executions/{execution}/assign'
*/
assign.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: assign.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::assign
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:215
* @route '/kanban/executions/{execution}/assign'
*/
const assignForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assign.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::assign
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:215
* @route '/kanban/executions/{execution}/assign'
*/
assignForm.patch = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: assign.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

assign.form = assignForm

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::history
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:292
* @route '/kanban/executions/{execution}/history'
*/
export const history = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(args, options),
    method: 'get',
})

history.definition = {
    methods: ["get","head"],
    url: '/kanban/executions/{execution}/history',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::history
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:292
* @route '/kanban/executions/{execution}/history'
*/
history.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return history.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::history
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:292
* @route '/kanban/executions/{execution}/history'
*/
history.get = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: history.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::history
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:292
* @route '/kanban/executions/{execution}/history'
*/
history.head = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: history.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::history
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:292
* @route '/kanban/executions/{execution}/history'
*/
const historyForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: history.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::history
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:292
* @route '/kanban/executions/{execution}/history'
*/
historyForm.get = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: history.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::history
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:292
* @route '/kanban/executions/{execution}/history'
*/
historyForm.head = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: history.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

history.form = historyForm

const executions = {
    store: Object.assign(store, store),
    details: Object.assign(details, details),
    start: Object.assign(start, start),
    move: Object.assign(move, move),
    pause: Object.assign(pause, pause),
    resume: Object.assign(resume, resume),
    complete: Object.assign(complete, complete),
    abandon: Object.assign(abandon, abandon),
    requestAbandonment: Object.assign(requestAbandonment, requestAbandonment),
    assign: Object.assign(assign, assign),
    history: Object.assign(history, history),
}

export default executions