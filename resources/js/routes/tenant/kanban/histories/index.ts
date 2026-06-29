import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::restore
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:353
* @route '/kanban/histories/{history}/restore'
*/
export const restore = (args: { history: string | { id: string } } | [history: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

restore.definition = {
    methods: ["post"],
    url: '/kanban/histories/{history}/restore',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::restore
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:353
* @route '/kanban/histories/{history}/restore'
*/
restore.url = (args: { history: string | { id: string } } | [history: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { history: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { history: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            history: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        history: typeof args.history === 'object'
        ? args.history.id
        : args.history,
    }

    return restore.definition.url
            .replace('{history}', parsedArgs.history.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::restore
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:353
* @route '/kanban/histories/{history}/restore'
*/
restore.post = (args: { history: string | { id: string } } | [history: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::restore
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:353
* @route '/kanban/histories/{history}/restore'
*/
const restoreForm = (args: { history: string | { id: string } } | [history: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowExecutionController::restore
* @see app/Http/Controllers/Tenant/WorkflowExecutionController.php:353
* @route '/kanban/histories/{history}/restore'
*/
restoreForm.post = (args: { history: string | { id: string } } | [history: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: restore.url(args, options),
    method: 'post',
})

restore.form = restoreForm

const histories = {
    restore: Object.assign(restore, restore),
}

export default histories