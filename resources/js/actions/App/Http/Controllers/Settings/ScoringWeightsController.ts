import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::edit
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:18
* @route '/settings/scoring-weights'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/scoring-weights',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::edit
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:18
* @route '/settings/scoring-weights'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::edit
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:18
* @route '/settings/scoring-weights'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::edit
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:18
* @route '/settings/scoring-weights'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::edit
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:18
* @route '/settings/scoring-weights'
*/
const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::edit
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:18
* @route '/settings/scoring-weights'
*/
editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::edit
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:18
* @route '/settings/scoring-weights'
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
* @see \App\Http\Controllers\Settings\ScoringWeightsController::update
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:37
* @route '/settings/scoring-weights'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/scoring-weights',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::update
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:37
* @route '/settings/scoring-weights'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::update
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:37
* @route '/settings/scoring-weights'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\ScoringWeightsController::update
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:37
* @route '/settings/scoring-weights'
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
* @see \App\Http\Controllers\Settings\ScoringWeightsController::update
* @see app/Http/Controllers/Settings/ScoringWeightsController.php:37
* @route '/settings/scoring-weights'
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

const ScoringWeightsController = { edit, update }

export default ScoringWeightsController