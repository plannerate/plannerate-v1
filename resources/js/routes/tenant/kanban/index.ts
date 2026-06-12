import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
import executions from './executions'
import histories from './histories'
/**
* @see \App\Http\Controllers\Tenant\WorkflowKanbanController::index
* @see app/Http/Controllers/Tenant/WorkflowKanbanController.php:28
* @route '/kanban'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/kanban',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\WorkflowKanbanController::index
* @see app/Http/Controllers/Tenant/WorkflowKanbanController.php:28
* @route '/kanban'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\WorkflowKanbanController::index
* @see app/Http/Controllers/Tenant/WorkflowKanbanController.php:28
* @route '/kanban'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowKanbanController::index
* @see app/Http/Controllers/Tenant/WorkflowKanbanController.php:28
* @route '/kanban'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowKanbanController::index
* @see app/Http/Controllers/Tenant/WorkflowKanbanController.php:28
* @route '/kanban'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowKanbanController::index
* @see app/Http/Controllers/Tenant/WorkflowKanbanController.php:28
* @route '/kanban'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\WorkflowKanbanController::index
* @see app/Http/Controllers/Tenant/WorkflowKanbanController.php:28
* @route '/kanban'
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

const kanban = {
    index: Object.assign(index, index),
    executions: Object.assign(executions, executions),
    histories: Object.assign(histories, histories),
}

export default kanban