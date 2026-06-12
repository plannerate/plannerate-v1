import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\PlanController::index
* @see app/Http/Controllers/Landlord/PlanController.php:25
* @route '//plannerate.localhost/plans'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/plans',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\PlanController::index
* @see app/Http/Controllers/Landlord/PlanController.php:25
* @route '//plannerate.localhost/plans'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PlanController::index
* @see app/Http/Controllers/Landlord/PlanController.php:25
* @route '//plannerate.localhost/plans'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::index
* @see app/Http/Controllers/Landlord/PlanController.php:25
* @route '//plannerate.localhost/plans'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::index
* @see app/Http/Controllers/Landlord/PlanController.php:25
* @route '//plannerate.localhost/plans'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::index
* @see app/Http/Controllers/Landlord/PlanController.php:25
* @route '//plannerate.localhost/plans'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::index
* @see app/Http/Controllers/Landlord/PlanController.php:25
* @route '//plannerate.localhost/plans'
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
* @see \App\Http\Controllers\Landlord\PlanController::create
* @see app/Http/Controllers/Landlord/PlanController.php:75
* @route '//plannerate.localhost/plans/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/plans/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\PlanController::create
* @see app/Http/Controllers/Landlord/PlanController.php:75
* @route '//plannerate.localhost/plans/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PlanController::create
* @see app/Http/Controllers/Landlord/PlanController.php:75
* @route '//plannerate.localhost/plans/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::create
* @see app/Http/Controllers/Landlord/PlanController.php:75
* @route '//plannerate.localhost/plans/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::create
* @see app/Http/Controllers/Landlord/PlanController.php:75
* @route '//plannerate.localhost/plans/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::create
* @see app/Http/Controllers/Landlord/PlanController.php:75
* @route '//plannerate.localhost/plans/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::create
* @see app/Http/Controllers/Landlord/PlanController.php:75
* @route '//plannerate.localhost/plans/create'
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
* @see \App\Http\Controllers\Landlord\PlanController::store
* @see app/Http/Controllers/Landlord/PlanController.php:87
* @route '//plannerate.localhost/plans'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/plans',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\PlanController::store
* @see app/Http/Controllers/Landlord/PlanController.php:87
* @route '//plannerate.localhost/plans'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PlanController::store
* @see app/Http/Controllers/Landlord/PlanController.php:87
* @route '//plannerate.localhost/plans'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::store
* @see app/Http/Controllers/Landlord/PlanController.php:87
* @route '//plannerate.localhost/plans'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::store
* @see app/Http/Controllers/Landlord/PlanController.php:87
* @route '//plannerate.localhost/plans'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\PlanController::edit
* @see app/Http/Controllers/Landlord/PlanController.php:112
* @route '//plannerate.localhost/plans/{plan}/edit'
*/
export const edit = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/plans/{plan}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\PlanController::edit
* @see app/Http/Controllers/Landlord/PlanController.php:112
* @route '//plannerate.localhost/plans/{plan}/edit'
*/
edit.url = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        plan: typeof args.plan === 'object'
        ? args.plan.id
        : args.plan,
    }

    return edit.definition.url
            .replace('{plan}', parsedArgs.plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PlanController::edit
* @see app/Http/Controllers/Landlord/PlanController.php:112
* @route '//plannerate.localhost/plans/{plan}/edit'
*/
edit.get = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::edit
* @see app/Http/Controllers/Landlord/PlanController.php:112
* @route '//plannerate.localhost/plans/{plan}/edit'
*/
edit.head = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::edit
* @see app/Http/Controllers/Landlord/PlanController.php:112
* @route '//plannerate.localhost/plans/{plan}/edit'
*/
const editForm = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::edit
* @see app/Http/Controllers/Landlord/PlanController.php:112
* @route '//plannerate.localhost/plans/{plan}/edit'
*/
editForm.get = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::edit
* @see app/Http/Controllers/Landlord/PlanController.php:112
* @route '//plannerate.localhost/plans/{plan}/edit'
*/
editForm.head = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\PlanController::update
* @see app/Http/Controllers/Landlord/PlanController.php:145
* @route '//plannerate.localhost/plans/{plan}'
*/
export const update = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '//plannerate.localhost/plans/{plan}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Landlord\PlanController::update
* @see app/Http/Controllers/Landlord/PlanController.php:145
* @route '//plannerate.localhost/plans/{plan}'
*/
update.url = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        plan: typeof args.plan === 'object'
        ? args.plan.id
        : args.plan,
    }

    return update.definition.url
            .replace('{plan}', parsedArgs.plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PlanController::update
* @see app/Http/Controllers/Landlord/PlanController.php:145
* @route '//plannerate.localhost/plans/{plan}'
*/
update.put = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::update
* @see app/Http/Controllers/Landlord/PlanController.php:145
* @route '//plannerate.localhost/plans/{plan}'
*/
update.patch = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::update
* @see app/Http/Controllers/Landlord/PlanController.php:145
* @route '//plannerate.localhost/plans/{plan}'
*/
const updateForm = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::update
* @see app/Http/Controllers/Landlord/PlanController.php:145
* @route '//plannerate.localhost/plans/{plan}'
*/
updateForm.put = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::update
* @see app/Http/Controllers/Landlord/PlanController.php:145
* @route '//plannerate.localhost/plans/{plan}'
*/
updateForm.patch = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\PlanController::destroy
* @see app/Http/Controllers/Landlord/PlanController.php:196
* @route '//plannerate.localhost/plans/{plan}'
*/
export const destroy = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/plans/{plan}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\PlanController::destroy
* @see app/Http/Controllers/Landlord/PlanController.php:196
* @route '//plannerate.localhost/plans/{plan}'
*/
destroy.url = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { plan: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { plan: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            plan: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        plan: typeof args.plan === 'object'
        ? args.plan.id
        : args.plan,
    }

    return destroy.definition.url
            .replace('{plan}', parsedArgs.plan.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\PlanController::destroy
* @see app/Http/Controllers/Landlord/PlanController.php:196
* @route '//plannerate.localhost/plans/{plan}'
*/
destroy.delete = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::destroy
* @see app/Http/Controllers/Landlord/PlanController.php:196
* @route '//plannerate.localhost/plans/{plan}'
*/
const destroyForm = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\PlanController::destroy
* @see app/Http/Controllers/Landlord/PlanController.php:196
* @route '//plannerate.localhost/plans/{plan}'
*/
destroyForm.delete = (args: { plan: string | { id: string } } | [plan: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const plans = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default plans