import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::edit
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:18
* @route '/settings/shelf-level-preferences'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/shelf-level-preferences',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::edit
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:18
* @route '/settings/shelf-level-preferences'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::edit
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:18
* @route '/settings/shelf-level-preferences'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::edit
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:18
* @route '/settings/shelf-level-preferences'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::edit
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:18
* @route '/settings/shelf-level-preferences'
*/
const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::edit
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:18
* @route '/settings/shelf-level-preferences'
*/
editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::edit
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:18
* @route '/settings/shelf-level-preferences'
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
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::store
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:45
* @route '/settings/shelf-level-preferences'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/shelf-level-preferences',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::store
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:45
* @route '/settings/shelf-level-preferences'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::store
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:45
* @route '/settings/shelf-level-preferences'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::store
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:45
* @route '/settings/shelf-level-preferences'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::store
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:45
* @route '/settings/shelf-level-preferences'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::update
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:54
* @route '/settings/shelf-level-preferences/{preference}'
*/
export const update = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/shelf-level-preferences/{preference}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::update
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:54
* @route '/settings/shelf-level-preferences/{preference}'
*/
update.url = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { preference: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { preference: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            preference: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        preference: typeof args.preference === 'object'
        ? args.preference.id
        : args.preference,
    }

    return update.definition.url
            .replace('{preference}', parsedArgs.preference.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::update
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:54
* @route '/settings/shelf-level-preferences/{preference}'
*/
update.put = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::update
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:54
* @route '/settings/shelf-level-preferences/{preference}'
*/
const updateForm = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::update
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:54
* @route '/settings/shelf-level-preferences/{preference}'
*/
updateForm.put = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::destroy
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:63
* @route '/settings/shelf-level-preferences/{preference}'
*/
export const destroy = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/shelf-level-preferences/{preference}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::destroy
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:63
* @route '/settings/shelf-level-preferences/{preference}'
*/
destroy.url = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { preference: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { preference: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            preference: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        preference: typeof args.preference === 'object'
        ? args.preference.id
        : args.preference,
    }

    return destroy.definition.url
            .replace('{preference}', parsedArgs.preference.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::destroy
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:63
* @route '/settings/shelf-level-preferences/{preference}'
*/
destroy.delete = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::destroy
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:63
* @route '/settings/shelf-level-preferences/{preference}'
*/
const destroyForm = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\ShelfLevelPreferencesController::destroy
* @see app/Http/Controllers/Settings/ShelfLevelPreferencesController.php:63
* @route '/settings/shelf-level-preferences/{preference}'
*/
destroyForm.delete = (args: { preference: string | number | { id: string | number } } | [preference: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const shelfLevelPreferences = {
    edit: Object.assign(edit, edit),
    store: Object.assign(store, store),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default shelfLevelPreferences