import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::upload
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
export const upload = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(options),
    method: 'post',
})

upload.definition = {
    methods: ["post"],
    url: '//plannerate.localhost/ean-references/image/upload',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::upload
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
upload.url = (options?: RouteQueryOptions) => {
    return upload.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::upload
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
upload.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: upload.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::upload
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
const uploadForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upload.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Landlord\EanReferenceController::upload
* @see app/Http/Controllers/Landlord/EanReferenceController.php:181
* @route '//plannerate.localhost/ean-references/image/upload'
*/
uploadForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: upload.url(options),
    method: 'post',
})

upload.form = uploadForm

const image = {
    upload: Object.assign(upload, upload),
}

export default image