import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import gondolas from './gondolas'
import workflowSettings from './workflow-settings'
/**
* @see \App\Http\Controllers\Tenant\PlanogramController::index
* @see app/Http/Controllers/Tenant/PlanogramController.php:34
* @route '/planograms'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/planograms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::index
* @see app/Http/Controllers/Tenant/PlanogramController.php:34
* @route '/planograms'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::index
* @see app/Http/Controllers/Tenant/PlanogramController.php:34
* @route '/planograms'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::index
* @see app/Http/Controllers/Tenant/PlanogramController.php:34
* @route '/planograms'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::index
* @see app/Http/Controllers/Tenant/PlanogramController.php:34
* @route '/planograms'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::index
* @see app/Http/Controllers/Tenant/PlanogramController.php:34
* @route '/planograms'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::index
* @see app/Http/Controllers/Tenant/PlanogramController.php:34
* @route '/planograms'
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
* @see \App\Http\Controllers\Tenant\PlanogramController::create
* @see app/Http/Controllers/Tenant/PlanogramController.php:116
* @route '/planograms/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/planograms/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::create
* @see app/Http/Controllers/Tenant/PlanogramController.php:116
* @route '/planograms/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::create
* @see app/Http/Controllers/Tenant/PlanogramController.php:116
* @route '/planograms/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::create
* @see app/Http/Controllers/Tenant/PlanogramController.php:116
* @route '/planograms/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::create
* @see app/Http/Controllers/Tenant/PlanogramController.php:116
* @route '/planograms/create'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::create
* @see app/Http/Controllers/Tenant/PlanogramController.php:116
* @route '/planograms/create'
*/
createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::create
* @see app/Http/Controllers/Tenant/PlanogramController.php:116
* @route '/planograms/create'
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
* @see \App\Http\Controllers\Tenant\PlanogramController::store
* @see app/Http/Controllers/Tenant/PlanogramController.php:359
* @route '/planograms'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/planograms',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::store
* @see app/Http/Controllers/Tenant/PlanogramController.php:359
* @route '/planograms'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::store
* @see app/Http/Controllers/Tenant/PlanogramController.php:359
* @route '/planograms'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::store
* @see app/Http/Controllers/Tenant/PlanogramController.php:359
* @route '/planograms'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::store
* @see app/Http/Controllers/Tenant/PlanogramController.php:359
* @route '/planograms'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::edit
* @see app/Http/Controllers/Tenant/PlanogramController.php:376
* @route '/planograms/{planogram}/edit'
*/
export const edit = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/planograms/{planogram}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::edit
* @see app/Http/Controllers/Tenant/PlanogramController.php:376
* @route '/planograms/{planogram}/edit'
*/
edit.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::edit
* @see app/Http/Controllers/Tenant/PlanogramController.php:376
* @route '/planograms/{planogram}/edit'
*/
edit.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::edit
* @see app/Http/Controllers/Tenant/PlanogramController.php:376
* @route '/planograms/{planogram}/edit'
*/
edit.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::edit
* @see app/Http/Controllers/Tenant/PlanogramController.php:376
* @route '/planograms/{planogram}/edit'
*/
const editForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::edit
* @see app/Http/Controllers/Tenant/PlanogramController.php:376
* @route '/planograms/{planogram}/edit'
*/
editForm.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::edit
* @see app/Http/Controllers/Tenant/PlanogramController.php:376
* @route '/planograms/{planogram}/edit'
*/
editForm.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
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
* @see \App\Http\Controllers\Tenant\PlanogramController::update
* @see app/Http/Controllers/Tenant/PlanogramController.php:401
* @route '/planograms/{planogram}'
*/
export const update = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/planograms/{planogram}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::update
* @see app/Http/Controllers/Tenant/PlanogramController.php:401
* @route '/planograms/{planogram}'
*/
update.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::update
* @see app/Http/Controllers/Tenant/PlanogramController.php:401
* @route '/planograms/{planogram}'
*/
update.put = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::update
* @see app/Http/Controllers/Tenant/PlanogramController.php:401
* @route '/planograms/{planogram}'
*/
update.patch = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::update
* @see app/Http/Controllers/Tenant/PlanogramController.php:401
* @route '/planograms/{planogram}'
*/
const updateForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::update
* @see app/Http/Controllers/Tenant/PlanogramController.php:401
* @route '/planograms/{planogram}'
*/
updateForm.put = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::update
* @see app/Http/Controllers/Tenant/PlanogramController.php:401
* @route '/planograms/{planogram}'
*/
updateForm.patch = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\PlanogramController::destroy
* @see app/Http/Controllers/Tenant/PlanogramController.php:415
* @route '/planograms/{planogram}'
*/
export const destroy = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/planograms/{planogram}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::destroy
* @see app/Http/Controllers/Tenant/PlanogramController.php:415
* @route '/planograms/{planogram}'
*/
destroy.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::destroy
* @see app/Http/Controllers/Tenant/PlanogramController.php:415
* @route '/planograms/{planogram}'
*/
destroy.delete = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::destroy
* @see app/Http/Controllers/Tenant/PlanogramController.php:415
* @route '/planograms/{planogram}'
*/
const destroyForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::destroy
* @see app/Http/Controllers/Tenant/PlanogramController.php:415
* @route '/planograms/{planogram}'
*/
destroyForm.delete = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Tenant\PlanogramController::maps
* @see app/Http/Controllers/Tenant/PlanogramController.php:132
* @route '/planograms/maps'
*/
export const maps = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: maps.url(options),
    method: 'get',
})

maps.definition = {
    methods: ["get","head"],
    url: '/planograms/maps',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::maps
* @see app/Http/Controllers/Tenant/PlanogramController.php:132
* @route '/planograms/maps'
*/
maps.url = (options?: RouteQueryOptions) => {
    return maps.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::maps
* @see app/Http/Controllers/Tenant/PlanogramController.php:132
* @route '/planograms/maps'
*/
maps.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: maps.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::maps
* @see app/Http/Controllers/Tenant/PlanogramController.php:132
* @route '/planograms/maps'
*/
maps.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: maps.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::maps
* @see app/Http/Controllers/Tenant/PlanogramController.php:132
* @route '/planograms/maps'
*/
const mapsForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: maps.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::maps
* @see app/Http/Controllers/Tenant/PlanogramController.php:132
* @route '/planograms/maps'
*/
mapsForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: maps.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::maps
* @see app/Http/Controllers/Tenant/PlanogramController.php:132
* @route '/planograms/maps'
*/
mapsForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: maps.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

maps.form = mapsForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::orphanLayers
* @see app/Http/Controllers/Tenant/PlanogramController.php:155
* @route '/planograms/orphan-layers'
*/
export const orphanLayers = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orphanLayers.url(options),
    method: 'get',
})

orphanLayers.definition = {
    methods: ["get","head"],
    url: '/planograms/orphan-layers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::orphanLayers
* @see app/Http/Controllers/Tenant/PlanogramController.php:155
* @route '/planograms/orphan-layers'
*/
orphanLayers.url = (options?: RouteQueryOptions) => {
    return orphanLayers.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::orphanLayers
* @see app/Http/Controllers/Tenant/PlanogramController.php:155
* @route '/planograms/orphan-layers'
*/
orphanLayers.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: orphanLayers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::orphanLayers
* @see app/Http/Controllers/Tenant/PlanogramController.php:155
* @route '/planograms/orphan-layers'
*/
orphanLayers.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: orphanLayers.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::orphanLayers
* @see app/Http/Controllers/Tenant/PlanogramController.php:155
* @route '/planograms/orphan-layers'
*/
const orphanLayersForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: orphanLayers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::orphanLayers
* @see app/Http/Controllers/Tenant/PlanogramController.php:155
* @route '/planograms/orphan-layers'
*/
orphanLayersForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: orphanLayers.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::orphanLayers
* @see app/Http/Controllers/Tenant/PlanogramController.php:155
* @route '/planograms/orphan-layers'
*/
orphanLayersForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: orphanLayers.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

orphanLayers.form = orphanLayersForm

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::kanban
* @see app/Http/Controllers/Tenant/PlanogramController.php:127
* @route '/planograms/kanban'
*/
export const kanban = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: kanban.url(options),
    method: 'get',
})

kanban.definition = {
    methods: ["get","head"],
    url: '/planograms/kanban',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::kanban
* @see app/Http/Controllers/Tenant/PlanogramController.php:127
* @route '/planograms/kanban'
*/
kanban.url = (options?: RouteQueryOptions) => {
    return kanban.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::kanban
* @see app/Http/Controllers/Tenant/PlanogramController.php:127
* @route '/planograms/kanban'
*/
kanban.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: kanban.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::kanban
* @see app/Http/Controllers/Tenant/PlanogramController.php:127
* @route '/planograms/kanban'
*/
kanban.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: kanban.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::kanban
* @see app/Http/Controllers/Tenant/PlanogramController.php:127
* @route '/planograms/kanban'
*/
const kanbanForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: kanban.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::kanban
* @see app/Http/Controllers/Tenant/PlanogramController.php:127
* @route '/planograms/kanban'
*/
kanbanForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: kanban.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\PlanogramController::kanban
* @see app/Http/Controllers/Tenant/PlanogramController.php:127
* @route '/planograms/kanban'
*/
kanbanForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: kanban.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

kanban.form = kanbanForm

const planograms = {
    gondolas: Object.assign(gondolas, gondolas),
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    maps: Object.assign(maps, maps),
    orphanLayers: Object.assign(orphanLayers, orphanLayers),
    kanban: Object.assign(kanban, kanban),
    workflowSettings: Object.assign(workflowSettings, workflowSettings),
}

export default planograms