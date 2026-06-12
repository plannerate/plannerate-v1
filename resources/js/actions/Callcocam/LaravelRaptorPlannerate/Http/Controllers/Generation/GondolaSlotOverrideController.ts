import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::upsert
* @see app/Http/Controllers/GondolaSlotOverrideController.php:22
* @route '/api/gondolas/{gondola}/generation-overrides'
*/
export const upsert = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: upsert.url(args, options),
    method: 'put',
})

upsert.definition = {
    methods: ["put"],
    url: '/api/gondolas/{gondola}/generation-overrides',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::upsert
* @see app/Http/Controllers/GondolaSlotOverrideController.php:22
* @route '/api/gondolas/{gondola}/generation-overrides'
*/
upsert.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return upsert.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::upsert
* @see app/Http/Controllers/GondolaSlotOverrideController.php:22
* @route '/api/gondolas/{gondola}/generation-overrides'
*/
upsert.put = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: upsert.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::upsert
* @see app/Http/Controllers/GondolaSlotOverrideController.php:22
* @route '/api/gondolas/{gondola}/generation-overrides'
*/
const upsertForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upsert.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::upsert
* @see app/Http/Controllers/GondolaSlotOverrideController.php:22
* @route '/api/gondolas/{gondola}/generation-overrides'
*/
upsertForm.put = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upsert.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

upsert.form = upsertForm

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::destroy
* @see app/Http/Controllers/GondolaSlotOverrideController.php:65
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}'
*/
export const destroy = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/gondolas/{gondola}/generation-overrides/{categoryId}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::destroy
* @see app/Http/Controllers/GondolaSlotOverrideController.php:65
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}'
*/
destroy.url = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
            categoryId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
        categoryId: args.categoryId,
    }

    return destroy.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace('{categoryId}', parsedArgs.categoryId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::destroy
* @see app/Http/Controllers/GondolaSlotOverrideController.php:65
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}'
*/
destroy.delete = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::destroy
* @see app/Http/Controllers/GondolaSlotOverrideController.php:65
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}'
*/
const destroyForm = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::destroy
* @see app/Http/Controllers/GondolaSlotOverrideController.php:65
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}'
*/
destroyForm.delete = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\GondolaSlotOverrideController::applyToTemplate
* @see app/Http/Controllers/GondolaSlotOverrideController.php:81
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}/apply-to-template'
*/
export const applyToTemplate = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyToTemplate.url(args, options),
    method: 'post',
})

applyToTemplate.definition = {
    methods: ["post"],
    url: '/api/gondolas/{gondola}/generation-overrides/{categoryId}/apply-to-template',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::applyToTemplate
* @see app/Http/Controllers/GondolaSlotOverrideController.php:81
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}/apply-to-template'
*/
applyToTemplate.url = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
            categoryId: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
        categoryId: args.categoryId,
    }

    return applyToTemplate.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace('{categoryId}', parsedArgs.categoryId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::applyToTemplate
* @see app/Http/Controllers/GondolaSlotOverrideController.php:81
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}/apply-to-template'
*/
applyToTemplate.post = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyToTemplate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::applyToTemplate
* @see app/Http/Controllers/GondolaSlotOverrideController.php:81
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}/apply-to-template'
*/
const applyToTemplateForm = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: applyToTemplate.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\GondolaSlotOverrideController::applyToTemplate
* @see app/Http/Controllers/GondolaSlotOverrideController.php:81
* @route '/api/gondolas/{gondola}/generation-overrides/{categoryId}/apply-to-template'
*/
applyToTemplateForm.post = (args: { gondola: string | number, categoryId: string | number } | [gondola: string | number, categoryId: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: applyToTemplate.url(args, options),
    method: 'post',
})

applyToTemplate.form = applyToTemplateForm

const GondolaSlotOverrideController = { upsert, destroy, applyToTemplate }

export default GondolaSlotOverrideController