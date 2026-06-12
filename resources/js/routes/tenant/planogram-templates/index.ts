import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import slots from './slots'
import subtemplates from './subtemplates'
/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::index
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:25
* @route '/planogram-templates'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/planogram-templates',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::index
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:25
* @route '/planogram-templates'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::index
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:25
* @route '/planogram-templates'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::index
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:25
* @route '/planogram-templates'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::index
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:25
* @route '/planogram-templates'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::index
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:25
* @route '/planogram-templates'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::index
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:25
* @route '/planogram-templates'
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
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::options
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:52
* @route '/planogram-templates/options'
*/
export const options = (routeOptions?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: options.url(routeOptions),
    method: 'get',
})

options.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/options',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::options
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:52
* @route '/planogram-templates/options'
*/
options.url = (routeOptions?: RouteQueryOptions) => {
    return options.definition.url
    + queryParams(routeOptions)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::options
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:52
* @route '/planogram-templates/options'
*/
options.get = (routeOptions?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: options.url(routeOptions),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::options
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:52
* @route '/planogram-templates/options'
*/
options.head = (routeOptions?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: options.url(routeOptions),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::options
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:52
* @route '/planogram-templates/options'
*/
const optionsForm = (routeOptions?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: options.url(

    routeOptions
   ),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::options
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:52
* @route '/planogram-templates/options'
*/
optionsForm.get = (routeOptions?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: options.url(

    routeOptions
   ),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::options
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:52
* @route '/planogram-templates/options'
*/
optionsForm.head = (routeOptions?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: options.url({
        [routeOptions?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(routeOptions?.query ?? routeOptions?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

options.form = optionsForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importPage
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:108
* @route '/planogram-templates/import'
*/
export const importPage = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: importPage.url(options),
    method: 'get',
})

importPage.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/import',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importPage
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:108
* @route '/planogram-templates/import'
*/
importPage.url = (options?: RouteQueryOptions) => {
    return importPage.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importPage
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:108
* @route '/planogram-templates/import'
*/
importPage.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: importPage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importPage
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:108
* @route '/planogram-templates/import'
*/
importPage.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: importPage.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importPage
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:108
* @route '/planogram-templates/import'
*/
const importPageForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: importPage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importPage
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:108
* @route '/planogram-templates/import'
*/
importPageForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: importPage.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importPage
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:108
* @route '/planogram-templates/import'
*/
importPageForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: importPage.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

importPage.form = importPageForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:115
* @route '/planogram-templates/import'
*/
export const importMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

importMethod.definition = {
    methods: ["post"],
    url: '/planogram-templates/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:115
* @route '/planogram-templates/import'
*/
importMethod.url = (options?: RouteQueryOptions) => {
    return importMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:115
* @route '/planogram-templates/import'
*/
importMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:115
* @route '/planogram-templates/import'
*/
const importMethodForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importMethod.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::importMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:115
* @route '/planogram-templates/import'
*/
importMethodForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: importMethod.url(options),
    method: 'post',
})

importMethod.form = importMethodForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportAll
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:222
* @route '/planogram-templates/export'
*/
export const exportAll = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportAll.url(options),
    method: 'get',
})

exportAll.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportAll
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:222
* @route '/planogram-templates/export'
*/
exportAll.url = (options?: RouteQueryOptions) => {
    return exportAll.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportAll
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:222
* @route '/planogram-templates/export'
*/
exportAll.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportAll.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportAll
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:222
* @route '/planogram-templates/export'
*/
exportAll.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportAll.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportAll
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:222
* @route '/planogram-templates/export'
*/
const exportAllForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportAll.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportAll
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:222
* @route '/planogram-templates/export'
*/
exportAllForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportAll.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportAll
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:222
* @route '/planogram-templates/export'
*/
exportAllForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportAll.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportAll.form = exportAllForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::create
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:41
* @route '/planogram-templates/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::create
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:41
* @route '/planogram-templates/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::create
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:41
* @route '/planogram-templates/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::create
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:41
* @route '/planogram-templates/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::create
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:41
* @route '/planogram-templates/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::create
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:41
* @route '/planogram-templates/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::create
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:41
* @route '/planogram-templates/create'
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
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::store
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:82
* @route '/planogram-templates'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/planogram-templates',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::store
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:82
* @route '/planogram-templates'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::store
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:82
* @route '/planogram-templates'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::store
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:82
* @route '/planogram-templates'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::store
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:82
* @route '/planogram-templates'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::show
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:189
* @route '/planogram-templates/{planogramTemplate}'
*/
export const show = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::show
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:189
* @route '/planogram-templates/{planogramTemplate}'
*/
show.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return show.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::show
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:189
* @route '/planogram-templates/{planogramTemplate}'
*/
show.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::show
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:189
* @route '/planogram-templates/{planogramTemplate}'
*/
show.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::show
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:189
* @route '/planogram-templates/{planogramTemplate}'
*/
const showForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::show
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:189
* @route '/planogram-templates/{planogramTemplate}'
*/
showForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::show
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:189
* @route '/planogram-templates/{planogramTemplate}'
*/
showForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::edit
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:146
* @route '/planogram-templates/{planogramTemplate}/edit'
*/
export const edit = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::edit
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:146
* @route '/planogram-templates/{planogramTemplate}/edit'
*/
edit.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return edit.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::edit
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:146
* @route '/planogram-templates/{planogramTemplate}/edit'
*/
edit.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::edit
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:146
* @route '/planogram-templates/{planogramTemplate}/edit'
*/
edit.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::edit
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:146
* @route '/planogram-templates/{planogramTemplate}/edit'
*/
const editForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::edit
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:146
* @route '/planogram-templates/{planogramTemplate}/edit'
*/
editForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::edit
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:146
* @route '/planogram-templates/{planogramTemplate}/edit'
*/
editForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::update
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:166
* @route '/planogram-templates/{planogramTemplate}'
*/
export const update = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/planogram-templates/{planogramTemplate}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::update
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:166
* @route '/planogram-templates/{planogramTemplate}'
*/
update.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return update.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::update
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:166
* @route '/planogram-templates/{planogramTemplate}'
*/
update.put = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::update
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:166
* @route '/planogram-templates/{planogramTemplate}'
*/
const updateForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::update
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:166
* @route '/planogram-templates/{planogramTemplate}'
*/
updateForm.put = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::destroy
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:254
* @route '/planogram-templates/{planogramTemplate}'
*/
export const destroy = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/planogram-templates/{planogramTemplate}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::destroy
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:254
* @route '/planogram-templates/{planogramTemplate}'
*/
destroy.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return destroy.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::destroy
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:254
* @route '/planogram-templates/{planogramTemplate}'
*/
destroy.delete = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::destroy
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:254
* @route '/planogram-templates/{planogramTemplate}'
*/
const destroyForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::destroy
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:254
* @route '/planogram-templates/{planogramTemplate}'
*/
destroyForm.delete = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:215
* @route '/planogram-templates/{planogramTemplate}/export'
*/
export const exportMethod = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/planogram-templates/{planogramTemplate}/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:215
* @route '/planogram-templates/{planogramTemplate}/export'
*/
exportMethod.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return exportMethod.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:215
* @route '/planogram-templates/{planogramTemplate}/export'
*/
exportMethod.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:215
* @route '/planogram-templates/{planogramTemplate}/export'
*/
exportMethod.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:215
* @route '/planogram-templates/{planogramTemplate}/export'
*/
const exportMethodForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:215
* @route '/planogram-templates/{planogramTemplate}/export'
*/
exportMethodForm.get = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::exportMethod
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:215
* @route '/planogram-templates/{planogramTemplate}/export'
*/
exportMethodForm.head = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: exportMethod.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

exportMethod.form = exportMethodForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::promote
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:235
* @route '/planogram-templates/{planogramTemplate}/promote'
*/
export const promote = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: promote.url(args, options),
    method: 'post',
})

promote.definition = {
    methods: ["post"],
    url: '/planogram-templates/{planogramTemplate}/promote',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::promote
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:235
* @route '/planogram-templates/{planogramTemplate}/promote'
*/
promote.url = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogramTemplate: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogramTemplate: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogramTemplate: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogramTemplate: typeof args.planogramTemplate === 'object'
        ? args.planogramTemplate.id
        : args.planogramTemplate,
    }

    return promote.definition.url
            .replace('{planogramTemplate}', parsedArgs.planogramTemplate.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::promote
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:235
* @route '/planogram-templates/{planogramTemplate}/promote'
*/
promote.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: promote.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::promote
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:235
* @route '/planogram-templates/{planogramTemplate}/promote'
*/
const promoteForm = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: promote.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramTemplateController::promote
* @see app/Http/Controllers/Tenant/PlanogramTemplateController.php:235
* @route '/planogram-templates/{planogramTemplate}/promote'
*/
promoteForm.post = (args: { planogramTemplate: string | number | { id: string | number } } | [planogramTemplate: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: promote.url(args, options),
    method: 'post',
})

promote.form = promoteForm

const planogramTemplates = {
    index: Object.assign(index, index),
    options: Object.assign(options, options),
    importPage: Object.assign(importPage, importPage),
    import: Object.assign(importMethod, importMethod),
    exportAll: Object.assign(exportAll, exportAll),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    export: Object.assign(exportMethod, exportMethod),
    promote: Object.assign(promote, promote),
    slots: Object.assign(slots, slots),
    subtemplates: Object.assign(subtemplates, subtemplates),
}

export default planogramTemplates