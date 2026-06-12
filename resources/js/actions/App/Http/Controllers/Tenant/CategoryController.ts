import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadeChildren
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
export const cascadeChildren = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cascadeChildren.url(options),
    method: 'get',
})

cascadeChildren.definition = {
    methods: ["get","head"],
    url: '/categories/cascade/children',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadeChildren
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
cascadeChildren.url = (options?: RouteQueryOptions) => {
    return cascadeChildren.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadeChildren
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
cascadeChildren.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cascadeChildren.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadeChildren
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
cascadeChildren.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: cascadeChildren.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadeChildren
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
const cascadeChildrenForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: cascadeChildren.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadeChildren
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
cascadeChildrenForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: cascadeChildren.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadeChildren
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
cascadeChildrenForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: cascadeChildren.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

cascadeChildren.form = cascadeChildrenForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadePath
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
export const cascadePath = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cascadePath.url(options),
    method: 'get',
})

cascadePath.definition = {
    methods: ["get","head"],
    url: '/categories/cascade/path',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadePath
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
cascadePath.url = (options?: RouteQueryOptions) => {
    return cascadePath.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadePath
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
cascadePath.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: cascadePath.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadePath
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
cascadePath.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: cascadePath.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadePath
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
const cascadePathForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: cascadePath.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadePath
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
cascadePathForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: cascadePath.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::cascadePath
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
cascadePathForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: cascadePath.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

cascadePath.form = cascadePathForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::importMethod
* @see app/Http/Controllers/Tenant/CategoryController.php:268
* @route '/categories/import'
*/
export const importMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

importMethod.definition = {
    methods: ["post"],
    url: '/categories/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::importMethod
* @see app/Http/Controllers/Tenant/CategoryController.php:268
* @route '/categories/import'
*/
importMethod.url = (options?: RouteQueryOptions) => {
    return importMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::importMethod
* @see app/Http/Controllers/Tenant/CategoryController.php:268
* @route '/categories/import'
*/
importMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::importMethod
* @see app/Http/Controllers/Tenant/CategoryController.php:268
* @route '/categories/import'
*/
const importMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importMethod.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::importMethod
* @see app/Http/Controllers/Tenant/CategoryController.php:268
* @route '/categories/import'
*/
importMethodForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importMethod.url(options),
    method: 'post',
})

importMethod.form = importMethodForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportTemplate
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
export const exportTemplate = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportTemplate.url(options),
    method: 'get',
})

exportTemplate.definition = {
    methods: ["get","head"],
    url: '/categories/export/template',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportTemplate
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
exportTemplate.url = (options?: RouteQueryOptions) => {
    return exportTemplate.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportTemplate
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
exportTemplate.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportTemplate.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportTemplate
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
exportTemplate.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportTemplate.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportTemplate
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
const exportTemplateForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportTemplate.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportTemplate
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
exportTemplateForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportTemplate.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportTemplate
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
exportTemplateForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportTemplate.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportTemplate.form = exportTemplateForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportData
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
export const exportData = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportData.url(options),
    method: 'get',
})

exportData.definition = {
    methods: ["get","head"],
    url: '/categories/export/data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportData
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
exportData.url = (options?: RouteQueryOptions) => {
    return exportData.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportData
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
exportData.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportData.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportData
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
exportData.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportData.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportData
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
const exportDataForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportData.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportData
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
exportDataForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportData.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::exportData
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
exportDataForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportData.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportData.form = exportDataForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::index
* @see app/Http/Controllers/Tenant/CategoryController.php:98
* @route '/categories'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/categories',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::index
* @see app/Http/Controllers/Tenant/CategoryController.php:98
* @route '/categories'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::index
* @see app/Http/Controllers/Tenant/CategoryController.php:98
* @route '/categories'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::index
* @see app/Http/Controllers/Tenant/CategoryController.php:98
* @route '/categories'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::index
* @see app/Http/Controllers/Tenant/CategoryController.php:98
* @route '/categories'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::index
* @see app/Http/Controllers/Tenant/CategoryController.php:98
* @route '/categories'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::index
* @see app/Http/Controllers/Tenant/CategoryController.php:98
* @route '/categories'
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
* @see \App\Http\Controllers\Tenant\CategoryController::create
* @see app/Http/Controllers/Tenant/CategoryController.php:181
* @route '/categories/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/categories/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::create
* @see app/Http/Controllers/Tenant/CategoryController.php:181
* @route '/categories/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::create
* @see app/Http/Controllers/Tenant/CategoryController.php:181
* @route '/categories/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::create
* @see app/Http/Controllers/Tenant/CategoryController.php:181
* @route '/categories/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::create
* @see app/Http/Controllers/Tenant/CategoryController.php:181
* @route '/categories/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::create
* @see app/Http/Controllers/Tenant/CategoryController.php:181
* @route '/categories/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::create
* @see app/Http/Controllers/Tenant/CategoryController.php:181
* @route '/categories/create'
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
* @see \App\Http\Controllers\Tenant\CategoryController::store
* @see app/Http/Controllers/Tenant/CategoryController.php:191
* @route '/categories'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/categories',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::store
* @see app/Http/Controllers/Tenant/CategoryController.php:191
* @route '/categories'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::store
* @see app/Http/Controllers/Tenant/CategoryController.php:191
* @route '/categories'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::store
* @see app/Http/Controllers/Tenant/CategoryController.php:191
* @route '/categories'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::store
* @see app/Http/Controllers/Tenant/CategoryController.php:191
* @route '/categories'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::edit
* @see app/Http/Controllers/Tenant/CategoryController.php:211
* @route '/categories/{category}/edit'
*/
export const edit = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/categories/{category}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::edit
* @see app/Http/Controllers/Tenant/CategoryController.php:211
* @route '/categories/{category}/edit'
*/
edit.url = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { category: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: typeof args.category === 'object'
        ? args.category.id
        : args.category,
    }

    return edit.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::edit
* @see app/Http/Controllers/Tenant/CategoryController.php:211
* @route '/categories/{category}/edit'
*/
edit.get = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::edit
* @see app/Http/Controllers/Tenant/CategoryController.php:211
* @route '/categories/{category}/edit'
*/
edit.head = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::edit
* @see app/Http/Controllers/Tenant/CategoryController.php:211
* @route '/categories/{category}/edit'
*/
const editForm = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::edit
* @see app/Http/Controllers/Tenant/CategoryController.php:211
* @route '/categories/{category}/edit'
*/
editForm.get = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::edit
* @see app/Http/Controllers/Tenant/CategoryController.php:211
* @route '/categories/{category}/edit'
*/
editForm.head = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\CategoryController::update
* @see app/Http/Controllers/Tenant/CategoryController.php:235
* @route '/categories/{category}'
*/
export const update = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/categories/{category}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::update
* @see app/Http/Controllers/Tenant/CategoryController.php:235
* @route '/categories/{category}'
*/
update.url = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { category: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: typeof args.category === 'object'
        ? args.category.id
        : args.category,
    }

    return update.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::update
* @see app/Http/Controllers/Tenant/CategoryController.php:235
* @route '/categories/{category}'
*/
update.put = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::update
* @see app/Http/Controllers/Tenant/CategoryController.php:235
* @route '/categories/{category}'
*/
update.patch = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::update
* @see app/Http/Controllers/Tenant/CategoryController.php:235
* @route '/categories/{category}'
*/
const updateForm = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::update
* @see app/Http/Controllers/Tenant/CategoryController.php:235
* @route '/categories/{category}'
*/
updateForm.put = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::update
* @see app/Http/Controllers/Tenant/CategoryController.php:235
* @route '/categories/{category}'
*/
updateForm.patch = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\CategoryController::destroy
* @see app/Http/Controllers/Tenant/CategoryController.php:254
* @route '/categories/{category}'
*/
export const destroy = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/categories/{category}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::destroy
* @see app/Http/Controllers/Tenant/CategoryController.php:254
* @route '/categories/{category}'
*/
destroy.url = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { category: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { category: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            category: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        category: typeof args.category === 'object'
        ? args.category.id
        : args.category,
    }

    return destroy.definition.url
            .replace('{category}', parsedArgs.category.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::destroy
* @see app/Http/Controllers/Tenant/CategoryController.php:254
* @route '/categories/{category}'
*/
destroy.delete = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::destroy
* @see app/Http/Controllers/Tenant/CategoryController.php:254
* @route '/categories/{category}'
*/
const destroyForm = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::destroy
* @see app/Http/Controllers/Tenant/CategoryController.php:254
* @route '/categories/{category}'
*/
destroyForm.delete = (args: { category: string | { id: string } } | [category: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const CategoryController = { cascadeChildren, cascadePath, importMethod, exportTemplate, exportData, index, create, store, edit, update, destroy, import: importMethod }

export default CategoryController