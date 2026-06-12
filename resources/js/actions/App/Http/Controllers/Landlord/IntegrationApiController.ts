import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::index
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:24
* @route '//plannerate.localhost/integration-apis'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/integration-apis',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::index
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:24
* @route '//plannerate.localhost/integration-apis'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::index
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:24
* @route '//plannerate.localhost/integration-apis'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::index
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:24
* @route '//plannerate.localhost/integration-apis'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::index
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:24
* @route '//plannerate.localhost/integration-apis'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::index
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:24
* @route '//plannerate.localhost/integration-apis'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::index
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:24
* @route '//plannerate.localhost/integration-apis'
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

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::create
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:43
* @route '//plannerate.localhost/integration-apis/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/integration-apis/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::create
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:43
* @route '//plannerate.localhost/integration-apis/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::create
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:43
* @route '//plannerate.localhost/integration-apis/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::create
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:43
* @route '//plannerate.localhost/integration-apis/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::create
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:43
* @route '//plannerate.localhost/integration-apis/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::create
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:43
* @route '//plannerate.localhost/integration-apis/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::create
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:43
* @route '//plannerate.localhost/integration-apis/create'
*/
createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::store
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:54
* @route '//plannerate.localhost/integration-apis'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/integration-apis',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::store
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:54
* @route '//plannerate.localhost/integration-apis'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::store
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:54
* @route '//plannerate.localhost/integration-apis'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::store
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:54
* @route '//plannerate.localhost/integration-apis'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::store
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:54
* @route '//plannerate.localhost/integration-apis'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::edit
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:68
* @route '//plannerate.localhost/integration-apis/{integration_api}/edit'
*/
export const edit = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/integration-apis/{integration_api}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::edit
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:68
* @route '//plannerate.localhost/integration-apis/{integration_api}/edit'
*/
edit.url = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { integration_api: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { integration_api: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            integration_api: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        integration_api: typeof args.integration_api === 'object'
        ? args.integration_api.id
        : args.integration_api,
    }

    return edit.definition.url
            .replace('{integration_api}', parsedArgs.integration_api.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::edit
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:68
* @route '//plannerate.localhost/integration-apis/{integration_api}/edit'
*/
edit.get = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::edit
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:68
* @route '//plannerate.localhost/integration-apis/{integration_api}/edit'
*/
edit.head = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::edit
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:68
* @route '//plannerate.localhost/integration-apis/{integration_api}/edit'
*/
const editForm = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::edit
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:68
* @route '//plannerate.localhost/integration-apis/{integration_api}/edit'
*/
editForm.get = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::edit
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:68
* @route '//plannerate.localhost/integration-apis/{integration_api}/edit'
*/
editForm.head = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::update
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:79
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
export const update = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '//plannerate.localhost/integration-apis/{integration_api}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::update
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:79
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
update.url = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { integration_api: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { integration_api: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            integration_api: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        integration_api: typeof args.integration_api === 'object'
        ? args.integration_api.id
        : args.integration_api,
    }

    return update.definition.url
            .replace('{integration_api}', parsedArgs.integration_api.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::update
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:79
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
update.put = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::update
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:79
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
update.patch = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::update
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:79
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
const updateForm = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::update
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:79
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
updateForm.put = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::update
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:79
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
updateForm.patch = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::destroy
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:93
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
export const destroy = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/integration-apis/{integration_api}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::destroy
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:93
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
destroy.url = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { integration_api: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { integration_api: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            integration_api: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        integration_api: typeof args.integration_api === 'object'
        ? args.integration_api.id
        : args.integration_api,
    }

    return destroy.definition.url
            .replace('{integration_api}', parsedArgs.integration_api.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::destroy
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:93
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
destroy.delete = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::destroy
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:93
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
const destroyForm = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::destroy
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:93
* @route '//plannerate.localhost/integration-apis/{integration_api}'
*/
destroyForm.delete = (args: { integration_api: string | { id: string } } | [integration_api: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::exportConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:107
* @route '//plannerate.localhost/integration-apis/export'
*/
export const exportConfigurations = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportConfigurations.url(options),
    method: 'get',
})

exportConfigurations.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/integration-apis/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::exportConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:107
* @route '//plannerate.localhost/integration-apis/export'
*/
exportConfigurations.url = (options?: RouteQueryOptions) => {
    return exportConfigurations.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::exportConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:107
* @route '//plannerate.localhost/integration-apis/export'
*/
exportConfigurations.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportConfigurations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::exportConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:107
* @route '//plannerate.localhost/integration-apis/export'
*/
exportConfigurations.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportConfigurations.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::exportConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:107
* @route '//plannerate.localhost/integration-apis/export'
*/
const exportConfigurationsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportConfigurations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::exportConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:107
* @route '//plannerate.localhost/integration-apis/export'
*/
exportConfigurationsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportConfigurations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::exportConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:107
* @route '//plannerate.localhost/integration-apis/export'
*/
exportConfigurationsForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportConfigurations.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportConfigurations.form = exportConfigurationsForm

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::importConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:140
* @route '//plannerate.localhost/integration-apis/import'
*/
export const importConfigurations = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importConfigurations.url(options),
    method: 'post',
})

importConfigurations.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/integration-apis/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::importConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:140
* @route '//plannerate.localhost/integration-apis/import'
*/
importConfigurations.url = (options?: RouteQueryOptions) => {
    return importConfigurations.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::importConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:140
* @route '//plannerate.localhost/integration-apis/import'
*/
importConfigurations.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importConfigurations.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::importConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:140
* @route '//plannerate.localhost/integration-apis/import'
*/
const importConfigurationsForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importConfigurations.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\IntegrationApiController::importConfigurations
* @see app/Http/Controllers/Landlord/IntegrationApiController.php:140
* @route '//plannerate.localhost/integration-apis/import'
*/
importConfigurationsForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importConfigurations.url(options),
    method: 'post',
})

importConfigurations.form = importConfigurationsForm

const IntegrationApiController = { index, create, store, edit, update, destroy, exportConfigurations, importConfigurations }

export default IntegrationApiController