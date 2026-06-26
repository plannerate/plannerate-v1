import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::edit
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
export const edit = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/editor/planograms/{record}/gondolas/editor',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::edit
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
edit.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { record: args }
    }

    if (Array.isArray(args)) {
        args = {
            record: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        record: args.record,
    }

    return edit.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::edit
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
edit.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::edit
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
edit.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::edit
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
const editForm = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::edit
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
editForm.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::edit
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
editForm.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

const EditorPlanogramController = { edit }

export default EditorPlanogramController