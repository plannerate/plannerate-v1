import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../wayfinder'
/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
const authenticatec3e63a8940727de72fe5f5f6492b621c = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authenticatec3e63a8940727de72fe5f5f6492b621c.url(options),
    method: 'get',
})

authenticatec3e63a8940727de72fe5f5f6492b621c.definition = {
    methods: ["get","post","head"],
    url: '//plannerate.localhost/broadcasting/auth',
} satisfies RouteDefinition<["get","post","head"]>

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
authenticatec3e63a8940727de72fe5f5f6492b621c.url = (options?: RouteQueryOptions) => {
    return authenticatec3e63a8940727de72fe5f5f6492b621c.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
authenticatec3e63a8940727de72fe5f5f6492b621c.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authenticatec3e63a8940727de72fe5f5f6492b621c.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
authenticatec3e63a8940727de72fe5f5f6492b621c.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authenticatec3e63a8940727de72fe5f5f6492b621c.url(options),
    method: 'post',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
authenticatec3e63a8940727de72fe5f5f6492b621c.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: authenticatec3e63a8940727de72fe5f5f6492b621c.url(options),
    method: 'head',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
const authenticatec3e63a8940727de72fe5f5f6492b621cForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: authenticatec3e63a8940727de72fe5f5f6492b621c.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
authenticatec3e63a8940727de72fe5f5f6492b621cForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: authenticatec3e63a8940727de72fe5f5f6492b621c.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
authenticatec3e63a8940727de72fe5f5f6492b621cForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: authenticatec3e63a8940727de72fe5f5f6492b621c.url(options),
    method: 'post',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '//plannerate.localhost/broadcasting/auth'
*/
authenticatec3e63a8940727de72fe5f5f6492b621cForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: authenticatec3e63a8940727de72fe5f5f6492b621c.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

authenticatec3e63a8940727de72fe5f5f6492b621c.form = authenticatec3e63a8940727de72fe5f5f6492b621cForm
/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
const authenticate95142b6115a9d019b8204096de0eb7b5 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authenticate95142b6115a9d019b8204096de0eb7b5.url(options),
    method: 'get',
})

authenticate95142b6115a9d019b8204096de0eb7b5.definition = {
    methods: ["get","post","head"],
    url: '/broadcasting/auth',
} satisfies RouteDefinition<["get","post","head"]>

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
authenticate95142b6115a9d019b8204096de0eb7b5.url = (options?: RouteQueryOptions) => {
    return authenticate95142b6115a9d019b8204096de0eb7b5.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
authenticate95142b6115a9d019b8204096de0eb7b5.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: authenticate95142b6115a9d019b8204096de0eb7b5.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
authenticate95142b6115a9d019b8204096de0eb7b5.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: authenticate95142b6115a9d019b8204096de0eb7b5.url(options),
    method: 'post',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
authenticate95142b6115a9d019b8204096de0eb7b5.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: authenticate95142b6115a9d019b8204096de0eb7b5.url(options),
    method: 'head',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
const authenticate95142b6115a9d019b8204096de0eb7b5Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: authenticate95142b6115a9d019b8204096de0eb7b5.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
authenticate95142b6115a9d019b8204096de0eb7b5Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: authenticate95142b6115a9d019b8204096de0eb7b5.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
authenticate95142b6115a9d019b8204096de0eb7b5Form.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: authenticate95142b6115a9d019b8204096de0eb7b5.url(options),
    method: 'post',
})

/**
* @see \Illuminate\Broadcasting\BroadcastController::authenticate
* @see vendor/laravel/framework/src/Illuminate/Broadcasting/BroadcastController.php:18
* @route '/broadcasting/auth'
*/
authenticate95142b6115a9d019b8204096de0eb7b5Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: authenticate95142b6115a9d019b8204096de0eb7b5.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

authenticate95142b6115a9d019b8204096de0eb7b5.form = authenticate95142b6115a9d019b8204096de0eb7b5Form

export const authenticate = {
    '//plannerate.localhost/broadcasting/auth': authenticatec3e63a8940727de72fe5f5f6492b621c,
    '/broadcasting/auth': authenticate95142b6115a9d019b8204096de0eb7b5,
}

const BroadcastController = { authenticate }

export default BroadcastController