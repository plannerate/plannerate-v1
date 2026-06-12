import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import analysis from './analysis'
/**
* @see \App\Http\Controllers\Tenant\GondolaController::index
* @see app/Http/Controllers/Tenant/GondolaController.php:28
* @route '/planograms/{planogram}/gondolas'
*/
export const index = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/planograms/{planogram}/gondolas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaController::index
* @see app/Http/Controllers/Tenant/GondolaController.php:28
* @route '/planograms/{planogram}/gondolas'
*/
index.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogram: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
    }

    return index.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaController::index
* @see app/Http/Controllers/Tenant/GondolaController.php:28
* @route '/planograms/{planogram}/gondolas'
*/
index.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::index
* @see app/Http/Controllers/Tenant/GondolaController.php:28
* @route '/planograms/{planogram}/gondolas'
*/
index.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::index
* @see app/Http/Controllers/Tenant/GondolaController.php:28
* @route '/planograms/{planogram}/gondolas'
*/
const indexForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::index
* @see app/Http/Controllers/Tenant/GondolaController.php:28
* @route '/planograms/{planogram}/gondolas'
*/
indexForm.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::index
* @see app/Http/Controllers/Tenant/GondolaController.php:28
* @route '/planograms/{planogram}/gondolas'
*/
indexForm.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\GondolaController::create
* @see app/Http/Controllers/Tenant/GondolaController.php:90
* @route '/planograms/{planogram}/gondolas/create'
*/
export const create = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/planograms/{planogram}/gondolas/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaController::create
* @see app/Http/Controllers/Tenant/GondolaController.php:90
* @route '/planograms/{planogram}/gondolas/create'
*/
create.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogram: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
    }

    return create.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaController::create
* @see app/Http/Controllers/Tenant/GondolaController.php:90
* @route '/planograms/{planogram}/gondolas/create'
*/
create.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::create
* @see app/Http/Controllers/Tenant/GondolaController.php:90
* @route '/planograms/{planogram}/gondolas/create'
*/
create.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::create
* @see app/Http/Controllers/Tenant/GondolaController.php:90
* @route '/planograms/{planogram}/gondolas/create'
*/
const createForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::create
* @see app/Http/Controllers/Tenant/GondolaController.php:90
* @route '/planograms/{planogram}/gondolas/create'
*/
createForm.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::create
* @see app/Http/Controllers/Tenant/GondolaController.php:90
* @route '/planograms/{planogram}/gondolas/create'
*/
createForm.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\GondolaController::store
* @see app/Http/Controllers/Tenant/GondolaController.php:104
* @route '/planograms/{planogram}/gondolas'
*/
export const store = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/planograms/{planogram}/gondolas',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaController::store
* @see app/Http/Controllers/Tenant/GondolaController.php:104
* @route '/planograms/{planogram}/gondolas'
*/
store.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogram: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
    }

    return store.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaController::store
* @see app/Http/Controllers/Tenant/GondolaController.php:104
* @route '/planograms/{planogram}/gondolas'
*/
store.post = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::store
* @see app/Http/Controllers/Tenant/GondolaController.php:104
* @route '/planograms/{planogram}/gondolas'
*/
const storeForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::store
* @see app/Http/Controllers/Tenant/GondolaController.php:104
* @route '/planograms/{planogram}/gondolas'
*/
storeForm.post = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\GondolaController::edit
* @see app/Http/Controllers/Tenant/GondolaController.php:127
* @route '/planograms/{planogram}/gondolas/{gondola}/edit'
*/
export const edit = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/planograms/{planogram}/gondolas/{gondola}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaController::edit
* @see app/Http/Controllers/Tenant/GondolaController.php:127
* @route '/planograms/{planogram}/gondolas/{gondola}/edit'
*/
edit.url = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
            gondola: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
        gondola: typeof args.gondola === 'object'
        ? args.gondola.id
        : args.gondola,
    }

    return edit.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaController::edit
* @see app/Http/Controllers/Tenant/GondolaController.php:127
* @route '/planograms/{planogram}/gondolas/{gondola}/edit'
*/
edit.get = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::edit
* @see app/Http/Controllers/Tenant/GondolaController.php:127
* @route '/planograms/{planogram}/gondolas/{gondola}/edit'
*/
edit.head = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::edit
* @see app/Http/Controllers/Tenant/GondolaController.php:127
* @route '/planograms/{planogram}/gondolas/{gondola}/edit'
*/
const editForm = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::edit
* @see app/Http/Controllers/Tenant/GondolaController.php:127
* @route '/planograms/{planogram}/gondolas/{gondola}/edit'
*/
editForm.get = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::edit
* @see app/Http/Controllers/Tenant/GondolaController.php:127
* @route '/planograms/{planogram}/gondolas/{gondola}/edit'
*/
editForm.head = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\GondolaController::update
* @see app/Http/Controllers/Tenant/GondolaController.php:156
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
export const update = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/planograms/{planogram}/gondolas/{gondola}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaController::update
* @see app/Http/Controllers/Tenant/GondolaController.php:156
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
update.url = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
            gondola: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
        gondola: typeof args.gondola === 'object'
        ? args.gondola.id
        : args.gondola,
    }

    return update.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaController::update
* @see app/Http/Controllers/Tenant/GondolaController.php:156
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
update.put = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::update
* @see app/Http/Controllers/Tenant/GondolaController.php:156
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
update.patch = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::update
* @see app/Http/Controllers/Tenant/GondolaController.php:156
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
const updateForm = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::update
* @see app/Http/Controllers/Tenant/GondolaController.php:156
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
updateForm.put = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::update
* @see app/Http/Controllers/Tenant/GondolaController.php:156
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
updateForm.patch = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\GondolaController::destroy
* @see app/Http/Controllers/Tenant/GondolaController.php:179
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
export const destroy = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/planograms/{planogram}/gondolas/{gondola}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaController::destroy
* @see app/Http/Controllers/Tenant/GondolaController.php:179
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
destroy.url = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
            gondola: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
        gondola: typeof args.gondola === 'object'
        ? args.gondola.id
        : args.gondola,
    }

    return destroy.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaController::destroy
* @see app/Http/Controllers/Tenant/GondolaController.php:179
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
destroy.delete = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::destroy
* @see app/Http/Controllers/Tenant/GondolaController.php:179
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
const destroyForm = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaController::destroy
* @see app/Http/Controllers/Tenant/GondolaController.php:179
* @route '/planograms/{planogram}/gondolas/{gondola}'
*/
destroyForm.delete = (args: { planogram: string | { id: string }, gondola: string | { id: string } } | [planogram: string | { id: string }, gondola: string | { id: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const gondolas = {
    analysis: Object.assign(analysis, analysis),
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default gondolas