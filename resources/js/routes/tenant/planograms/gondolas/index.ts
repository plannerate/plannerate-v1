import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::editor
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
export const editor = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: editor.url(args, options),
    method: 'get',
})

editor.definition = {
    methods: ["get","head"],
    url: '/editor/planograms/{record}/gondolas/editor',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::editor
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
editor.url = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return editor.definition.url
            .replace('{record}', parsedArgs.record.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::editor
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
editor.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: editor.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::editor
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
editor.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: editor.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::editor
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
const editorForm = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: editor.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::editor
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
editorForm.get = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: editor.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\EditorPlanogramController::editor
* @see app/Http/Controllers/Tenant/Editor/EditorPlanogramController.php:24
* @route '/editor/planograms/{record}/gondolas/editor'
*/
editorForm.head = (args: { record: string | number } | [record: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: editor.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

editor.form = editorForm

const gondolas = {
    editor: Object.assign(editor, editor),
}

export default gondolas