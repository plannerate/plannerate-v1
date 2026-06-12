import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::edit
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:20
* @route '/settings/planogram'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/planogram',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::edit
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:20
* @route '/settings/planogram'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::edit
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:20
* @route '/settings/planogram'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::edit
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:20
* @route '/settings/planogram'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::edit
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:20
* @route '/settings/planogram'
*/
const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::edit
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:20
* @route '/settings/planogram'
*/
editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::edit
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:20
* @route '/settings/planogram'
*/
editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::update
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:60
* @route '/settings/planogram'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/planogram',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::update
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:60
* @route '/settings/planogram'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::update
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:60
* @route '/settings/planogram'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::update
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:60
* @route '/settings/planogram'
*/
const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\PlanogramSettingsController::update
* @see app/Http/Controllers/Settings/PlanogramSettingsController.php:60
* @route '/settings/planogram'
*/
updateForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

const PlanogramSettingsController = { edit, update }

export default PlanogramSettingsController