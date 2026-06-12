import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:112
* @route '/api/editor/planograms/{planogram}/gondolas'
*/
export const store = (args: { planogram: string | number } | [planogram: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/api/editor/planograms/{planogram}/gondolas',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:112
* @route '/api/editor/planograms/{planogram}/gondolas'
*/
store.url = (args: { planogram: string | number } | [planogram: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: args.planogram,
    }

    return store.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:112
* @route '/api/editor/planograms/{planogram}/gondolas'
*/
store.post = (args: { planogram: string | number } | [planogram: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:112
* @route '/api/editor/planograms/{planogram}/gondolas'
*/
const storeForm = (args: { planogram: string | number } | [planogram: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::store
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:112
* @route '/api/editor/planograms/{planogram}/gondolas'
*/
storeForm.post = (args: { planogram: string | number } | [planogram: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:196
* @route '/api/editor/gondolas/{gondola}'
*/
export const update = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/editor/gondolas/{gondola}',
} satisfies RouteDefinition<["put"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:196
* @route '/api/editor/gondolas/{gondola}'
*/
update.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return update.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:196
* @route '/api/editor/gondolas/{gondola}'
*/
update.put = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:196
* @route '/api/editor/gondolas/{gondola}'
*/
const updateForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:196
* @route '/api/editor/gondolas/{gondola}'
*/
updateForm.put = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:212
* @route '/api/editor/gondolas/{gondola}'
*/
export const destroy = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/editor/gondolas/{gondola}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:212
* @route '/api/editor/gondolas/{gondola}'
*/
destroy.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return destroy.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:212
* @route '/api/editor/gondolas/{gondola}'
*/
destroy.delete = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:212
* @route '/api/editor/gondolas/{gondola}'
*/
const destroyForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:212
* @route '/api/editor/gondolas/{gondola}'
*/
destroyForm.delete = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::sections
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:226
* @route '/api/editor/gondolas/{gondola}/sections'
*/
export const sections = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sections.url(args, options),
    method: 'get',
})

sections.definition = {
    methods: ["get","head"],
    url: '/api/editor/gondolas/{gondola}/sections',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::sections
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:226
* @route '/api/editor/gondolas/{gondola}/sections'
*/
sections.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return sections.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::sections
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:226
* @route '/api/editor/gondolas/{gondola}/sections'
*/
sections.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: sections.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::sections
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:226
* @route '/api/editor/gondolas/{gondola}/sections'
*/
sections.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: sections.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::sections
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:226
* @route '/api/editor/gondolas/{gondola}/sections'
*/
const sectionsForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sections.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::sections
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:226
* @route '/api/editor/gondolas/{gondola}/sections'
*/
sectionsForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sections.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::sections
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:226
* @route '/api/editor/gondolas/{gondola}/sections'
*/
sectionsForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: sections.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

sections.form = sectionsForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:272
* @route '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products'
*/
export const products = (args: { planogram: string | number, gondola: string | number } | [planogram: string | number, gondola: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

products.definition = {
    methods: ["get","head"],
    url: '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:272
* @route '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products'
*/
products.url = (args: { planogram: string | number, gondola: string | number } | [planogram: string | number, gondola: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
            gondola: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: args.planogram,
        gondola: args.gondola,
    }

    return products.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:272
* @route '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products'
*/
products.get = (args: { planogram: string | number, gondola: string | number } | [planogram: string | number, gondola: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: products.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:272
* @route '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products'
*/
products.head = (args: { planogram: string | number, gondola: string | number } | [planogram: string | number, gondola: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: products.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:272
* @route '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products'
*/
const productsForm = (args: { planogram: string | number, gondola: string | number } | [planogram: string | number, gondola: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:272
* @route '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products'
*/
productsForm.get = (args: { planogram: string | number, gondola: string | number } | [planogram: string | number, gondola: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::products
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:272
* @route '/api/plannograma/{planogram}/editor/gondolas/{gondola}/products'
*/
productsForm.head = (args: { planogram: string | number, gondola: string | number } | [planogram: string | number, gondola: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: products.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

products.form = productsForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::updateImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:426
* @route '/api/editor/gondolas/{gondola}/update-images'
*/
export const updateImages = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateImages.url(args, options),
    method: 'post',
})

updateImages.definition = {
    methods: ["post"],
    url: '/api/editor/gondolas/{gondola}/update-images',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::updateImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:426
* @route '/api/editor/gondolas/{gondola}/update-images'
*/
updateImages.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return updateImages.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::updateImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:426
* @route '/api/editor/gondolas/{gondola}/update-images'
*/
updateImages.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: updateImages.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::updateImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:426
* @route '/api/editor/gondolas/{gondola}/update-images'
*/
const updateImagesForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateImages.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\GondolaController::updateImages
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/GondolaController.php:426
* @route '/api/editor/gondolas/{gondola}/update-images'
*/
updateImagesForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: updateImages.url(args, options),
    method: 'post',
})

updateImages.form = updateImagesForm

const GondolaController = { store, update, destroy, sections, products, updateImages }

export default GondolaController