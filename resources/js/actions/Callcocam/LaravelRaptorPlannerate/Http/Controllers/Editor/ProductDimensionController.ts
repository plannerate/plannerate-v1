import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductDimensionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductDimensionController.php:15
* @route '/api/plannograma/{planogram}/products/{product}/dimensions'
*/
export const update = (args: { planogram: string | number, product: string | number } | [planogram: string | number, product: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

update.definition = {
    methods: ["post"],
    url: '/api/plannograma/{planogram}/products/{product}/dimensions',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductDimensionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductDimensionController.php:15
* @route '/api/plannograma/{planogram}/products/{product}/dimensions'
*/
update.url = (args: { planogram: string | number, product: string | number } | [planogram: string | number, product: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
            product: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: args.planogram,
        product: args.product,
    }

    return update.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace('{product}', parsedArgs.product.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductDimensionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductDimensionController.php:15
* @route '/api/plannograma/{planogram}/products/{product}/dimensions'
*/
update.post = (args: { planogram: string | number, product: string | number } | [planogram: string | number, product: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: update.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductDimensionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductDimensionController.php:15
* @route '/api/plannograma/{planogram}/products/{product}/dimensions'
*/
const updateForm = (args: { planogram: string | number, product: string | number } | [planogram: string | number, product: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\ProductDimensionController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/ProductDimensionController.php:15
* @route '/api/plannograma/{planogram}/products/{product}/dimensions'
*/
updateForm.post = (args: { planogram: string | number, product: string | number } | [planogram: string | number, product: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, options),
    method: 'post',
})

update.form = updateForm

const ProductDimensionController = { update }

export default ProductDimensionController