import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::index
* @see app/Http/Controllers/Landlord/EanReferenceController.php:24
* @route '//plannerate.localhost/ean-references'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/ean-references',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::index
* @see app/Http/Controllers/Landlord/EanReferenceController.php:24
* @route '//plannerate.localhost/ean-references'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::index
* @see app/Http/Controllers/Landlord/EanReferenceController.php:24
* @route '//plannerate.localhost/ean-references'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::index
* @see app/Http/Controllers/Landlord/EanReferenceController.php:24
* @route '//plannerate.localhost/ean-references'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::index
* @see app/Http/Controllers/Landlord/EanReferenceController.php:24
* @route '//plannerate.localhost/ean-references'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::index
* @see app/Http/Controllers/Landlord/EanReferenceController.php:24
* @route '//plannerate.localhost/ean-references'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::index
* @see app/Http/Controllers/Landlord/EanReferenceController.php:24
* @route '//plannerate.localhost/ean-references'
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
* @see \App\Http\Controllers\Landlord\EanReferenceController::create
* @see app/Http/Controllers/Landlord/EanReferenceController.php:95
* @route '//plannerate.localhost/ean-references/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/ean-references/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::create
* @see app/Http/Controllers/Landlord/EanReferenceController.php:95
* @route '//plannerate.localhost/ean-references/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::create
* @see app/Http/Controllers/Landlord/EanReferenceController.php:95
* @route '//plannerate.localhost/ean-references/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::create
* @see app/Http/Controllers/Landlord/EanReferenceController.php:95
* @route '//plannerate.localhost/ean-references/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::create
* @see app/Http/Controllers/Landlord/EanReferenceController.php:95
* @route '//plannerate.localhost/ean-references/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::create
* @see app/Http/Controllers/Landlord/EanReferenceController.php:95
* @route '//plannerate.localhost/ean-references/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::create
* @see app/Http/Controllers/Landlord/EanReferenceController.php:95
* @route '//plannerate.localhost/ean-references/create'
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
* @see \App\Http\Controllers\Landlord\EanReferenceController::store
* @see app/Http/Controllers/Landlord/EanReferenceController.php:104
* @route '//plannerate.localhost/ean-references'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/ean-references',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::store
* @see app/Http/Controllers/Landlord/EanReferenceController.php:104
* @route '//plannerate.localhost/ean-references'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::store
* @see app/Http/Controllers/Landlord/EanReferenceController.php:104
* @route '//plannerate.localhost/ean-references'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::store
* @see app/Http/Controllers/Landlord/EanReferenceController.php:104
* @route '//plannerate.localhost/ean-references'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::store
* @see app/Http/Controllers/Landlord/EanReferenceController.php:104
* @route '//plannerate.localhost/ean-references'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::edit
* @see app/Http/Controllers/Landlord/EanReferenceController.php:118
* @route '//plannerate.localhost/ean-references/{ean_reference}/edit'
*/
export const edit = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '//plannerate.localhost/ean-references/{ean_reference}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::edit
* @see app/Http/Controllers/Landlord/EanReferenceController.php:118
* @route '//plannerate.localhost/ean-references/{ean_reference}/edit'
*/
edit.url = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ean_reference: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { ean_reference: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            ean_reference: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ean_reference: typeof args.ean_reference === 'object'
        ? args.ean_reference.id
        : args.ean_reference,
    }

    return edit.definition.url
            .replace('{ean_reference}', parsedArgs.ean_reference.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::edit
* @see app/Http/Controllers/Landlord/EanReferenceController.php:118
* @route '//plannerate.localhost/ean-references/{ean_reference}/edit'
*/
edit.get = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::edit
* @see app/Http/Controllers/Landlord/EanReferenceController.php:118
* @route '//plannerate.localhost/ean-references/{ean_reference}/edit'
*/
edit.head = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::edit
* @see app/Http/Controllers/Landlord/EanReferenceController.php:118
* @route '//plannerate.localhost/ean-references/{ean_reference}/edit'
*/
const editForm = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::edit
* @see app/Http/Controllers/Landlord/EanReferenceController.php:118
* @route '//plannerate.localhost/ean-references/{ean_reference}/edit'
*/
editForm.get = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::edit
* @see app/Http/Controllers/Landlord/EanReferenceController.php:118
* @route '//plannerate.localhost/ean-references/{ean_reference}/edit'
*/
editForm.head = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Landlord\EanReferenceController::update
* @see app/Http/Controllers/Landlord/EanReferenceController.php:149
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
export const update = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '//plannerate.localhost/ean-references/{ean_reference}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::update
* @see app/Http/Controllers/Landlord/EanReferenceController.php:149
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
update.url = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ean_reference: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { ean_reference: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            ean_reference: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ean_reference: typeof args.ean_reference === 'object'
        ? args.ean_reference.id
        : args.ean_reference,
    }

    return update.definition.url
            .replace('{ean_reference}', parsedArgs.ean_reference.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::update
* @see app/Http/Controllers/Landlord/EanReferenceController.php:149
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
update.put = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::update
* @see app/Http/Controllers/Landlord/EanReferenceController.php:149
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
update.patch = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::update
* @see app/Http/Controllers/Landlord/EanReferenceController.php:149
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
const updateForm = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::update
* @see app/Http/Controllers/Landlord/EanReferenceController.php:149
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
updateForm.put = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::update
* @see app/Http/Controllers/Landlord/EanReferenceController.php:149
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
updateForm.patch = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\EanReferenceController::destroy
* @see app/Http/Controllers/Landlord/EanReferenceController.php:197
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
export const destroy = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '//plannerate.localhost/ean-references/{ean_reference}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::destroy
* @see app/Http/Controllers/Landlord/EanReferenceController.php:197
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
destroy.url = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ean_reference: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { ean_reference: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            ean_reference: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ean_reference: typeof args.ean_reference === 'object'
        ? args.ean_reference.id
        : args.ean_reference,
    }

    return destroy.definition.url
            .replace('{ean_reference}', parsedArgs.ean_reference.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::destroy
* @see app/Http/Controllers/Landlord/EanReferenceController.php:197
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
destroy.delete = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::destroy
* @see app/Http/Controllers/Landlord/EanReferenceController.php:197
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
const destroyForm = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::destroy
* @see app/Http/Controllers/Landlord/EanReferenceController.php:197
* @route '//plannerate.localhost/ean-references/{ean_reference}'
*/
destroyForm.delete = (args: { ean_reference: string | { id: string } } | [ean_reference: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Landlord\EanReferenceController::uploadImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
export const uploadImage = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadImage.url(options),
    method: 'post',
})

uploadImage.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/ean-references/image/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::uploadImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
uploadImage.url = (options?: RouteQueryOptions) => {
    return uploadImage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::uploadImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
uploadImage.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadImage.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::uploadImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
const uploadImageForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: uploadImage.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::uploadImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
uploadImageForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: uploadImage.url(options),
    method: 'post',
})

uploadImage.form = uploadImageForm

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::fetchImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:163
* @route '//plannerate.localhost/ean-references/{ean_reference}/fetch-image'
*/
export const fetchImage = (args: { ean_reference: string | number } | [ean_reference: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchImage.url(args, options),
    method: 'post',
})

fetchImage.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/ean-references/{ean_reference}/fetch-image',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::fetchImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:163
* @route '//plannerate.localhost/ean-references/{ean_reference}/fetch-image'
*/
fetchImage.url = (args: { ean_reference: string | number } | [ean_reference: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { ean_reference: args }
    }

    if (Array.isArray(args)) {
        args = {
            ean_reference: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        ean_reference: args.ean_reference,
    }

    return fetchImage.definition.url
            .replace('{ean_reference}', parsedArgs.ean_reference.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::fetchImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:163
* @route '//plannerate.localhost/ean-references/{ean_reference}/fetch-image'
*/
fetchImage.post = (args: { ean_reference: string | number } | [ean_reference: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fetchImage.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::fetchImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:163
* @route '//plannerate.localhost/ean-references/{ean_reference}/fetch-image'
*/
const fetchImageForm = (args: { ean_reference: string | number } | [ean_reference: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: fetchImage.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::fetchImage
* @see app/Http/Controllers/Landlord/EanReferenceController.php:163
* @route '//plannerate.localhost/ean-references/{ean_reference}/fetch-image'
*/
fetchImageForm.post = (args: { ean_reference: string | number } | [ean_reference: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: fetchImage.url(args, options),
    method: 'post',
})

fetchImage.form = fetchImageForm

const EanReferenceController = { index, create, store, edit, update, destroy, uploadImage, fetchImage }

export default EanReferenceController