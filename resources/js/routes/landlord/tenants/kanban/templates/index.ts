import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::index
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:26
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
export const index = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::index
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:26
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
index.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::index
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:26
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
index.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::index
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:26
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
index.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::index
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:26
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
const indexForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::index
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:26
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
indexForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::index
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:26
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
indexForm.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::create
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:93
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/create'
*/
export const create = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::create
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:93
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/create'
*/
create.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return create.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::create
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:93
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/create'
*/
create.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::create
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:93
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/create'
*/
create.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::create
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:93
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/create'
*/
const createForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::create
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:93
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/create'
*/
createForm.get = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::create
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:93
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/create'
*/
createForm.head = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

create.form = createForm

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::store
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:116
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
export const store = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::store
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:116
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
store.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return store.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::store
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:116
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
store.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::store
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:116
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
const storeForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::store
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:116
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates'
*/
storeForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::seedDefaults
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:244
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/seed-defaults'
*/
export const seedDefaults = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: seedDefaults.url(args, options),
    method: 'post',
})

seedDefaults.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates/seed-defaults',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::seedDefaults
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:244
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/seed-defaults'
*/
seedDefaults.url = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return seedDefaults.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::seedDefaults
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:244
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/seed-defaults'
*/
seedDefaults.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: seedDefaults.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::seedDefaults
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:244
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/seed-defaults'
*/
const seedDefaultsForm = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: seedDefaults.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::seedDefaults
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:244
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/seed-defaults'
*/
seedDefaultsForm.post = (args: { tenant: string | { id: string } } | [tenant: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: seedDefaults.url(args, options),
    method: 'post',
})

seedDefaults.form = seedDefaultsForm

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::edit
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:142
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit'
*/
export const edit = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::edit
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:142
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit'
*/
edit.url = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            template: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        template: args.template,
    }

    return edit.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::edit
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:142
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit'
*/
edit.get = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::edit
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:142
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit'
*/
edit.head = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::edit
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:142
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit'
*/
const editForm = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::edit
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:142
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit'
*/
editForm.get = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::edit
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:142
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/edit'
*/
editForm.head = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::update
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:183
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
export const update = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::update
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:183
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
update.url = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            template: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        template: args.template,
    }

    return update.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::update
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:183
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
update.put = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::update
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:183
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
const updateForm = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::update
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:183
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
updateForm.put = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::syncUsers
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:204
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/sync-users'
*/
export const syncUsers = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: syncUsers.url(args, options),
    method: 'patch',
})

syncUsers.definition = {
    methods: ["patch"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/sync-users',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::syncUsers
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:204
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/sync-users'
*/
syncUsers.url = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            template: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        template: args.template,
    }

    return syncUsers.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::syncUsers
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:204
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/sync-users'
*/
syncUsers.patch = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: syncUsers.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::syncUsers
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:204
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/sync-users'
*/
const syncUsersForm = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncUsers.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::syncUsers
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:204
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}/sync-users'
*/
syncUsersForm.patch = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: syncUsers.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

syncUsers.form = syncUsersForm

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::destroy
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:228
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
export const destroy = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::destroy
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:228
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
destroy.url = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            tenant: args[0],
            template: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tenant: typeof args.tenant === 'object'
        ? args.tenant.id
        : args.tenant,
        template: args.template,
    }

    return destroy.definition.url
            .replace('{tenant}', parsedArgs.tenant.toString())
            .replace('{template}', parsedArgs.template.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::destroy
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:228
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
destroy.delete = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::destroy
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:228
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
const destroyForm = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\WorkflowTemplateController::destroy
* @see app/Http/Controllers/Landlord/WorkflowTemplateController.php:228
* @route '//plannerate.localhost/tenants/{tenant}/kanban/templates/{template}'
*/
destroyForm.delete = (args: { tenant: string | { id: string }, template: string | number } | [tenant: string | { id: string }, template: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const templates = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    seedDefaults: Object.assign(seedDefaults, seedDefaults),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    syncUsers: Object.assign(syncUsers, syncUsers),
    destroy: Object.assign(destroy, destroy),
}

export default templates