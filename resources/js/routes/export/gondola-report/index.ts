import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::excel
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
export const excel = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: excel.url(args, options),
    method: 'post',
})

excel.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/excel',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::excel
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
excel.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return excel.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::excel
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
excel.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: excel.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::excel
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
const excelForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: excel.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::excel
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:214
* @route '/export/gondola-report/{gondola}/excel'
*/
excelForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: excel.url(args, options),
    method: 'post',
})

excel.form = excelForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::pdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
export const pdf = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pdf.url(args, options),
    method: 'post',
})

pdf.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/pdf',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::pdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
pdf.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return pdf.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::pdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
pdf.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pdf.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::pdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
const pdfForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pdf.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::pdf
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:222
* @route '/export/gondola-report/{gondola}/pdf'
*/
pdfForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pdf.url(args, options),
    method: 'post',
})

pdf.form = pdfForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::compra
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
export const compra = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: compra.url(args, options),
    method: 'post',
})

compra.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/compra',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::compra
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
compra.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return compra.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::compra
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
compra.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: compra.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::compra
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
const compraForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: compra.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::compra
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:230
* @route '/export/gondola-report/{gondola}/compra'
*/
compraForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: compra.url(args, options),
    method: 'post',
})

compra.form = compraForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::dimensao
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
export const dimensao = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dimensao.url(args, options),
    method: 'post',
})

dimensao.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/dimensao',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::dimensao
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
dimensao.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return dimensao.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::dimensao
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
dimensao.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: dimensao.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::dimensao
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
const dimensaoForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: dimensao.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::dimensao
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:238
* @route '/export/gondola-report/{gondola}/dimensao'
*/
dimensaoForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: dimensao.url(args, options),
    method: 'post',
})

dimensao.form = dimensaoForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::image
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
export const image = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: image.url(args, options),
    method: 'post',
})

image.definition = {
    methods: ["post"],
    url: '/export/gondola-report/{gondola}/image',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::image
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
image.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return image.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::image
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
image.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: image.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::image
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
const imageForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: image.url(args, options),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::image
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:246
* @route '/export/gondola-report/{gondola}/image'
*/
imageForm.post = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: image.url(args, options),
    method: 'post',
})

image.form = imageForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::data
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
export const data = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: data.url(args, options),
    method: 'get',
})

data.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::data
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
data.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return data.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::data
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
data.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: data.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::data
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
data.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: data.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::data
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
const dataForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: data.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::data
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
dataForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: data.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::data
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:254
* @route '/export/gondola-report/{gondola}/data'
*/
dataForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: data.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

data.form = dataForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogram
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
export const planogram = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planogram.url(args, options),
    method: 'get',
})

planogram.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/planogram-pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogram
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
planogram.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return planogram.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogram
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
planogram.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planogram.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogram
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
planogram.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: planogram.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogram
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
const planogramForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planogram.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogram
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
planogramForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planogram.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogram
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:33
* @route '/export/gondola-report/{gondola}/planogram-pdf'
*/
planogramForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planogram.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

planogram.form = planogramForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogramModules
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
export const planogramModules = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planogramModules.url(args, options),
    method: 'get',
})

planogramModules.definition = {
    methods: ["get","head"],
    url: '/export/gondola-report/{gondola}/planogram-modules-pdf',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogramModules
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
planogramModules.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return planogramModules.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogramModules
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
planogramModules.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: planogramModules.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogramModules
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
planogramModules.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: planogramModules.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogramModules
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
const planogramModulesForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planogramModules.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogramModules
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
planogramModulesForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planogramModules.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController::planogramModules
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Export/GondolaReportController.php:51
* @route '/export/gondola-report/{gondola}/planogram-modules-pdf'
*/
planogramModulesForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: planogramModules.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

planogramModules.form = planogramModulesForm

const gondolaReport = {
    excel: Object.assign(excel, excel),
    pdf: Object.assign(pdf, pdf),
    compra: Object.assign(compra, compra),
    dimensao: Object.assign(dimensao, dimensao),
    image: Object.assign(image, image),
    data: Object.assign(data, data),
    planogram: Object.assign(planogram, planogram),
    planogramModules: Object.assign(planogramModules, planogramModules),
}

export default gondolaReport