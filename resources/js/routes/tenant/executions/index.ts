import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import evidences from './evidences'
import divergences from './divergences'
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

const executions = {
    evidences: Object.assign(evidences, evidences),
    divergences: Object.assign(divergences, divergences),
    complete: Object.assign(complete, complete),
}

export default executions