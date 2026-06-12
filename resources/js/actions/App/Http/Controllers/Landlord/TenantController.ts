import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\TenantController::index
* @see app/Http/Controllers/Landlord/TenantController.php:47
* @route '//plannerate.localhost/tenants'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::index
* @see app/Http/Controllers/Landlord/TenantController.php:47
* @route '//plannerate.localhost/tenants'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::index
* @see app/Http/Controllers/Landlord/TenantController.php:47
* @route '//plannerate.localhost/tenants'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::index
* @see app/Http/Controllers/Landlord/TenantController.php:47
* @route '//plannerate.localhost/tenants'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::index
* @see app/Http/Controllers/Landlord/TenantController.php:47
* @route '//plannerate.localhost/tenants'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::index
* @see app/Http/Controllers/Landlord/TenantController.php:47
* @route '//plannerate.localhost/tenants'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::index
* @see app/Http/Controllers/Landlord/TenantController.php:47
* @route '//plannerate.localhost/tenants'
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
* @see \App\Http\Controllers\Landlord\TenantController::create
* @see app/Http/Controllers/Landlord/TenantController.php:145
* @route '//plannerate.localhost/tenants/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::create
* @see app/Http/Controllers/Landlord/TenantController.php:145
* @route '//plannerate.localhost/tenants/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::create
* @see app/Http/Controllers/Landlord/TenantController.php:145
* @route '//plannerate.localhost/tenants/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::create
* @see app/Http/Controllers/Landlord/TenantController.php:145
* @route '//plannerate.localhost/tenants/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::create
* @see app/Http/Controllers/Landlord/TenantController.php:145
* @route '//plannerate.localhost/tenants/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::create
* @see app/Http/Controllers/Landlord/TenantController.php:145
* @route '//plannerate.localhost/tenants/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::create
* @see app/Http/Controllers/Landlord/TenantController.php:145
* @route '//plannerate.localhost/tenants/create'
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
* @see \App\Http\Controllers\Landlord\TenantController::store
* @see app/Http/Controllers/Landlord/TenantController.php:160
* @route '//plannerate.localhost/tenants'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::store
* @see app/Http/Controllers/Landlord/TenantController.php:160
* @route '//plannerate.localhost/tenants'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::store
* @see app/Http/Controllers/Landlord/TenantController.php:160
* @route '//plannerate.localhost/tenants'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::store
* @see app/Http/Controllers/Landlord/TenantController.php:160
* @route '//plannerate.localhost/tenants'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::store
* @see app/Http/Controllers/Landlord/TenantController.php:160
* @route '//plannerate.localhost/tenants'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\TenantController::edit
* @see app/Http/Controllers/Landlord/TenantController.php:194
* @route '//plannerate.localhost/tenants/{tenant}/edit'
*/
export const edit = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::edit
* @see app/Http/Controllers/Landlord/TenantController.php:194
* @route '//plannerate.localhost/tenants/{tenant}/edit'
*/
edit.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return edit.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::edit
* @see app/Http/Controllers/Landlord/TenantController.php:194
* @route '//plannerate.localhost/tenants/{tenant}/edit'
*/
edit.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::edit
* @see app/Http/Controllers/Landlord/TenantController.php:194
* @route '//plannerate.localhost/tenants/{tenant}/edit'
*/
edit.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::edit
* @see app/Http/Controllers/Landlord/TenantController.php:194
* @route '//plannerate.localhost/tenants/{tenant}/edit'
*/
const editForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::edit
* @see app/Http/Controllers/Landlord/TenantController.php:194
* @route '//plannerate.localhost/tenants/{tenant}/edit'
*/
editForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::edit
* @see app/Http/Controllers/Landlord/TenantController.php:194
* @route '//plannerate.localhost/tenants/{tenant}/edit'
*/
editForm.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\TenantController::update
* @see app/Http/Controllers/Landlord/TenantController.php:262
* @route '//plannerate.localhost/tenants/{tenant}'
*/
export const update = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '//plannerate.localhost/tenants/{tenant}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::update
* @see app/Http/Controllers/Landlord/TenantController.php:262
* @route '//plannerate.localhost/tenants/{tenant}'
*/
update.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return update.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::update
* @see app/Http/Controllers/Landlord/TenantController.php:262
* @route '//plannerate.localhost/tenants/{tenant}'
*/
update.put = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::update
* @see app/Http/Controllers/Landlord/TenantController.php:262
* @route '//plannerate.localhost/tenants/{tenant}'
*/
update.patch = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::update
* @see app/Http/Controllers/Landlord/TenantController.php:262
* @route '//plannerate.localhost/tenants/{tenant}'
*/
const updateForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::update
* @see app/Http/Controllers/Landlord/TenantController.php:262
* @route '//plannerate.localhost/tenants/{tenant}'
*/
updateForm.put = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::update
* @see app/Http/Controllers/Landlord/TenantController.php:262
* @route '//plannerate.localhost/tenants/{tenant}'
*/
updateForm.patch = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\TenantController::destroy
* @see app/Http/Controllers/Landlord/TenantController.php:585
* @route '//plannerate.localhost/tenants/{tenant}'
*/
export const destroy = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/tenants/{tenant}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::destroy
* @see app/Http/Controllers/Landlord/TenantController.php:585
* @route '//plannerate.localhost/tenants/{tenant}'
*/
destroy.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return destroy.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::destroy
* @see app/Http/Controllers/Landlord/TenantController.php:585
* @route '//plannerate.localhost/tenants/{tenant}'
*/
destroy.delete = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::destroy
* @see app/Http/Controllers/Landlord/TenantController.php:585
* @route '//plannerate.localhost/tenants/{tenant}'
*/
const destroyForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::destroy
* @see app/Http/Controllers/Landlord/TenantController.php:585
* @route '//plannerate.localhost/tenants/{tenant}'
*/
destroyForm.delete = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\TenantController::exportConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:293
* @route '//plannerate.localhost/tenants/export'
*/
export const exportConfigurations = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportConfigurations.url(options),
    method: 'get',
})

exportConfigurations.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::exportConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:293
* @route '//plannerate.localhost/tenants/export'
*/
exportConfigurations.url = (options?: RouteQueryOptions) => {
    return exportConfigurations.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::exportConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:293
* @route '//plannerate.localhost/tenants/export'
*/
exportConfigurations.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportConfigurations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::exportConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:293
* @route '//plannerate.localhost/tenants/export'
*/
exportConfigurations.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportConfigurations.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::exportConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:293
* @route '//plannerate.localhost/tenants/export'
*/
const exportConfigurationsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportConfigurations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::exportConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:293
* @route '//plannerate.localhost/tenants/export'
*/
exportConfigurationsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportConfigurations.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::exportConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:293
* @route '//plannerate.localhost/tenants/export'
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
* @see \App\Http\Controllers\Landlord\TenantController::importConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:366
* @route '//plannerate.localhost/tenants/import'
*/
export const importConfigurations = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importConfigurations.url(options),
    method: 'post',
})

importConfigurations.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::importConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:366
* @route '//plannerate.localhost/tenants/import'
*/
importConfigurations.url = (options?: RouteQueryOptions) => {
    return importConfigurations.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::importConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:366
* @route '//plannerate.localhost/tenants/import'
*/
importConfigurations.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importConfigurations.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::importConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:366
* @route '//plannerate.localhost/tenants/import'
*/
const importConfigurationsForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importConfigurations.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::importConfigurations
* @see app/Http/Controllers/Landlord/TenantController.php:366
* @route '//plannerate.localhost/tenants/import'
*/
importConfigurationsForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importConfigurations.url(options),
    method: 'post',
})

importConfigurations.form = importConfigurationsForm

/**
* @see \App\Http\Controllers\Landlord\TenantController::setup
* @see app/Http/Controllers/Landlord/TenantController.php:535
* @route '//plannerate.localhost/tenants/{tenant}/setup'
*/
export const setup = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setup.url(args, options),
    method: 'get',
})

setup.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/setup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::setup
* @see app/Http/Controllers/Landlord/TenantController.php:535
* @route '//plannerate.localhost/tenants/{tenant}/setup'
*/
setup.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return setup.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::setup
* @see app/Http/Controllers/Landlord/TenantController.php:535
* @route '//plannerate.localhost/tenants/{tenant}/setup'
*/
setup.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: setup.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::setup
* @see app/Http/Controllers/Landlord/TenantController.php:535
* @route '//plannerate.localhost/tenants/{tenant}/setup'
*/
setup.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: setup.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::setup
* @see app/Http/Controllers/Landlord/TenantController.php:535
* @route '//plannerate.localhost/tenants/{tenant}/setup'
*/
const setupForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: setup.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::setup
* @see app/Http/Controllers/Landlord/TenantController.php:535
* @route '//plannerate.localhost/tenants/{tenant}/setup'
*/
setupForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: setup.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::setup
* @see app/Http/Controllers/Landlord/TenantController.php:535
* @route '//plannerate.localhost/tenants/{tenant}/setup'
*/
setupForm.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: setup.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

setup.form = setupForm

/**
* @see \App\Http\Controllers\Landlord\TenantController::provision
* @see app/Http/Controllers/Landlord/TenantController.php:563
* @route '//plannerate.localhost/tenants/{tenant}/provision'
*/
export const provision = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: provision.url(args, options),
    method: 'post',
})

provision.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/provision',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\TenantController::provision
* @see app/Http/Controllers/Landlord/TenantController.php:563
* @route '//plannerate.localhost/tenants/{tenant}/provision'
*/
provision.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tenant: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { tenant: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
    }

    return provision.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\TenantController::provision
* @see app/Http/Controllers/Landlord/TenantController.php:563
* @route '//plannerate.localhost/tenants/{tenant}/provision'
*/
provision.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: provision.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::provision
* @see app/Http/Controllers/Landlord/TenantController.php:563
* @route '//plannerate.localhost/tenants/{tenant}/provision'
*/
const provisionForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: provision.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\TenantController::provision
* @see app/Http/Controllers/Landlord/TenantController.php:563
* @route '//plannerate.localhost/tenants/{tenant}/provision'
*/
provisionForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: provision.url(args, options),
    method: 'post',
})

provision.form = provisionForm

const TenantController = { index, create, store, edit, update, destroy, exportConfigurations, importConfigurations, setup, provision }

export default TenantController