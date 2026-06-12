import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\ModuleController::index
* @see app/Http/Controllers/Landlord/ModuleController.php:23
* @route '//plannerate.localhost/modules'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/modules',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\ModuleController::index
* @see app/Http/Controllers/Landlord/ModuleController.php:23
* @route '//plannerate.localhost/modules'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\ModuleController::index
* @see app/Http/Controllers/Landlord/ModuleController.php:23
* @route '//plannerate.localhost/modules'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::index
* @see app/Http/Controllers/Landlord/ModuleController.php:23
* @route '//plannerate.localhost/modules'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::index
* @see app/Http/Controllers/Landlord/ModuleController.php:23
* @route '//plannerate.localhost/modules'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::index
* @see app/Http/Controllers/Landlord/ModuleController.php:23
* @route '//plannerate.localhost/modules'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::index
* @see app/Http/Controllers/Landlord/ModuleController.php:23
* @route '//plannerate.localhost/modules'
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
* @see \App\Http\Controllers\Landlord\ModuleController::create
* @see app/Http/Controllers/Landlord/ModuleController.php:71
* @route '//plannerate.localhost/modules/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/modules/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\ModuleController::create
* @see app/Http/Controllers/Landlord/ModuleController.php:71
* @route '//plannerate.localhost/modules/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\ModuleController::create
* @see app/Http/Controllers/Landlord/ModuleController.php:71
* @route '//plannerate.localhost/modules/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::create
* @see app/Http/Controllers/Landlord/ModuleController.php:71
* @route '//plannerate.localhost/modules/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::create
* @see app/Http/Controllers/Landlord/ModuleController.php:71
* @route '//plannerate.localhost/modules/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::create
* @see app/Http/Controllers/Landlord/ModuleController.php:71
* @route '//plannerate.localhost/modules/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::create
* @see app/Http/Controllers/Landlord/ModuleController.php:71
* @route '//plannerate.localhost/modules/create'
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
* @see \App\Http\Controllers\Landlord\ModuleController::store
* @see app/Http/Controllers/Landlord/ModuleController.php:83
* @route '//plannerate.localhost/modules'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/modules',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\ModuleController::store
* @see app/Http/Controllers/Landlord/ModuleController.php:83
* @route '//plannerate.localhost/modules'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\ModuleController::store
* @see app/Http/Controllers/Landlord/ModuleController.php:83
* @route '//plannerate.localhost/modules'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::store
* @see app/Http/Controllers/Landlord/ModuleController.php:83
* @route '//plannerate.localhost/modules'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::store
* @see app/Http/Controllers/Landlord/ModuleController.php:83
* @route '//plannerate.localhost/modules'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\ModuleController::edit
* @see app/Http/Controllers/Landlord/ModuleController.php:103
* @route '//plannerate.localhost/modules/{module}/edit'
*/
export const edit = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/modules/{module}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\ModuleController::edit
* @see app/Http/Controllers/Landlord/ModuleController.php:103
* @route '//plannerate.localhost/modules/{module}/edit'
*/
edit.url = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { module: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { module: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            module: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        module: typeof args.module === 'object'
        ? args.module.id
        : args.module,
    }

    return edit.definition.url
            .replace('{module}', parsedArgs.module.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\ModuleController::edit
* @see app/Http/Controllers/Landlord/ModuleController.php:103
* @route '//plannerate.localhost/modules/{module}/edit'
*/
edit.get = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::edit
* @see app/Http/Controllers/Landlord/ModuleController.php:103
* @route '//plannerate.localhost/modules/{module}/edit'
*/
edit.head = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::edit
* @see app/Http/Controllers/Landlord/ModuleController.php:103
* @route '//plannerate.localhost/modules/{module}/edit'
*/
const editForm = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::edit
* @see app/Http/Controllers/Landlord/ModuleController.php:103
* @route '//plannerate.localhost/modules/{module}/edit'
*/
editForm.get = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::edit
* @see app/Http/Controllers/Landlord/ModuleController.php:103
* @route '//plannerate.localhost/modules/{module}/edit'
*/
editForm.head = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\ModuleController::update
* @see app/Http/Controllers/Landlord/ModuleController.php:121
* @route '//plannerate.localhost/modules/{module}'
*/
export const update = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '//plannerate.localhost/modules/{module}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Landlord\ModuleController::update
* @see app/Http/Controllers/Landlord/ModuleController.php:121
* @route '//plannerate.localhost/modules/{module}'
*/
update.url = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { module: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { module: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            module: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        module: typeof args.module === 'object'
        ? args.module.id
        : args.module,
    }

    return update.definition.url
            .replace('{module}', parsedArgs.module.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\ModuleController::update
* @see app/Http/Controllers/Landlord/ModuleController.php:121
* @route '//plannerate.localhost/modules/{module}'
*/
update.put = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::update
* @see app/Http/Controllers/Landlord/ModuleController.php:121
* @route '//plannerate.localhost/modules/{module}'
*/
update.patch = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::update
* @see app/Http/Controllers/Landlord/ModuleController.php:121
* @route '//plannerate.localhost/modules/{module}'
*/
const updateForm = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::update
* @see app/Http/Controllers/Landlord/ModuleController.php:121
* @route '//plannerate.localhost/modules/{module}'
*/
updateForm.put = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::update
* @see app/Http/Controllers/Landlord/ModuleController.php:121
* @route '//plannerate.localhost/modules/{module}'
*/
updateForm.patch = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\ModuleController::destroy
* @see app/Http/Controllers/Landlord/ModuleController.php:141
* @route '//plannerate.localhost/modules/{module}'
*/
export const destroy = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/modules/{module}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\ModuleController::destroy
* @see app/Http/Controllers/Landlord/ModuleController.php:141
* @route '//plannerate.localhost/modules/{module}'
*/
destroy.url = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { module: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { module: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            module: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        module: typeof args.module === 'object'
        ? args.module.id
        : args.module,
    }

    return destroy.definition.url
            .replace('{module}', parsedArgs.module.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\ModuleController::destroy
* @see app/Http/Controllers/Landlord/ModuleController.php:141
* @route '//plannerate.localhost/modules/{module}'
*/
destroy.delete = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::destroy
* @see app/Http/Controllers/Landlord/ModuleController.php:141
* @route '//plannerate.localhost/modules/{module}'
*/
const destroyForm = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\ModuleController::destroy
* @see app/Http/Controllers/Landlord/ModuleController.php:141
* @route '//plannerate.localhost/modules/{module}'
*/
destroyForm.delete = (args: { module: string | { id: string } } | [module: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const ModuleController = { index, create, store, edit, update, destroy }

export default ModuleController