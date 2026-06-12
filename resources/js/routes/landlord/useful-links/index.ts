import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::index
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:20
* @route '//plannerate.localhost/useful-links'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/useful-links',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::index
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:20
* @route '//plannerate.localhost/useful-links'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::index
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:20
* @route '//plannerate.localhost/useful-links'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::index
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:20
* @route '//plannerate.localhost/useful-links'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::index
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:20
* @route '//plannerate.localhost/useful-links'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::index
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:20
* @route '//plannerate.localhost/useful-links'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::index
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:20
* @route '//plannerate.localhost/useful-links'
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
* @see \App\Http\Controllers\Landlord\UsefulLinkController::create
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:65
* @route '//plannerate.localhost/useful-links/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/useful-links/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::create
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:65
* @route '//plannerate.localhost/useful-links/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::create
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:65
* @route '//plannerate.localhost/useful-links/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::create
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:65
* @route '//plannerate.localhost/useful-links/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::create
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:65
* @route '//plannerate.localhost/useful-links/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::create
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:65
* @route '//plannerate.localhost/useful-links/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::create
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:65
* @route '//plannerate.localhost/useful-links/create'
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
* @see \App\Http\Controllers\Landlord\UsefulLinkController::store
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:74
* @route '//plannerate.localhost/useful-links'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/useful-links',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::store
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:74
* @route '//plannerate.localhost/useful-links'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::store
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:74
* @route '//plannerate.localhost/useful-links'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::store
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:74
* @route '//plannerate.localhost/useful-links'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::store
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:74
* @route '//plannerate.localhost/useful-links'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::edit
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:91
* @route '//plannerate.localhost/useful-links/{useful_link}/edit'
*/
export const edit = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/useful-links/{useful_link}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::edit
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:91
* @route '//plannerate.localhost/useful-links/{useful_link}/edit'
*/
edit.url = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { useful_link: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { useful_link: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            useful_link: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        useful_link: typeof args.useful_link === 'object'
        ? args.useful_link.id
        : args.useful_link,
    }

    return edit.definition.url
            .replace('{useful_link}', parsedArgs.useful_link.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::edit
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:91
* @route '//plannerate.localhost/useful-links/{useful_link}/edit'
*/
edit.get = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::edit
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:91
* @route '//plannerate.localhost/useful-links/{useful_link}/edit'
*/
edit.head = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::edit
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:91
* @route '//plannerate.localhost/useful-links/{useful_link}/edit'
*/
const editForm = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::edit
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:91
* @route '//plannerate.localhost/useful-links/{useful_link}/edit'
*/
editForm.get = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::edit
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:91
* @route '//plannerate.localhost/useful-links/{useful_link}/edit'
*/
editForm.head = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\UsefulLinkController::update
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:107
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
export const update = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '//plannerate.localhost/useful-links/{useful_link}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::update
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:107
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
update.url = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { useful_link: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { useful_link: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            useful_link: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        useful_link: typeof args.useful_link === 'object'
        ? args.useful_link.id
        : args.useful_link,
    }

    return update.definition.url
            .replace('{useful_link}', parsedArgs.useful_link.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::update
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:107
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
update.put = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::update
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:107
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
update.patch = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::update
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:107
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
const updateForm = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::update
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:107
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
updateForm.put = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::update
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:107
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
updateForm.patch = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\UsefulLinkController::destroy
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:124
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
export const destroy = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/useful-links/{useful_link}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::destroy
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:124
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
destroy.url = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { useful_link: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { useful_link: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            useful_link: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        useful_link: typeof args.useful_link === 'object'
        ? args.useful_link.id
        : args.useful_link,
    }

    return destroy.definition.url
            .replace('{useful_link}', parsedArgs.useful_link.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::destroy
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:124
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
destroy.delete = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::destroy
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:124
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
const destroyForm = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\UsefulLinkController::destroy
* @see app/Http/Controllers/Landlord/UsefulLinkController.php:124
* @route '//plannerate.localhost/useful-links/{useful_link}'
*/
destroyForm.delete = (args: { useful_link: string | { id: string } } | [useful_link: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const usefulLinks = {
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default usefulLinks