import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:35
* @route '/whatsapp/cloud/sandbox'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/whatsapp/cloud/sandbox',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:35
* @route '/whatsapp/cloud/sandbox'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:35
* @route '/whatsapp/cloud/sandbox'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:35
* @route '/whatsapp/cloud/sandbox'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:35
* @route '/whatsapp/cloud/sandbox'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:35
* @route '/whatsapp/cloud/sandbox'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::index
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:35
* @route '/whatsapp/cloud/sandbox'
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
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::state
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:43
* @route '/whatsapp/cloud/sandbox/state'
*/
export const state = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: state.url(options),
    method: 'get',
})

state.definition = {
    methods: ["get","head"],
    url: '/whatsapp/cloud/sandbox/state',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::state
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:43
* @route '/whatsapp/cloud/sandbox/state'
*/
state.url = (options?: RouteQueryOptions) => {
    return state.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::state
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:43
* @route '/whatsapp/cloud/sandbox/state'
*/
state.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: state.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::state
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:43
* @route '/whatsapp/cloud/sandbox/state'
*/
state.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: state.url(options),
    method: 'head',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::state
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:43
* @route '/whatsapp/cloud/sandbox/state'
*/
const stateForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: state.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::state
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:43
* @route '/whatsapp/cloud/sandbox/state'
*/
stateForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: state.url(options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::state
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:43
* @route '/whatsapp/cloud/sandbox/state'
*/
stateForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: state.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

state.form = stateForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::message
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:52
* @route '/whatsapp/cloud/sandbox/messages/{message}'
*/
export const message = (args: { message: number | { id: number } } | [message: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: message.url(args, options),
    method: 'get',
})

message.definition = {
    methods: ["get","head"],
    url: '/whatsapp/cloud/sandbox/messages/{message}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::message
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:52
* @route '/whatsapp/cloud/sandbox/messages/{message}'
*/
message.url = (args: { message: number | { id: number } } | [message: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { message: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { message: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            message: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        message: typeof args.message === 'object'
        ? args.message.id
        : args.message,
    }

    return message.definition.url
            .replace('{message}', parsedArgs.message.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::message
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:52
* @route '/whatsapp/cloud/sandbox/messages/{message}'
*/
message.get = (args: { message: number | { id: number } } | [message: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: message.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::message
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:52
* @route '/whatsapp/cloud/sandbox/messages/{message}'
*/
message.head = (args: { message: number | { id: number } } | [message: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: message.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::message
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:52
* @route '/whatsapp/cloud/sandbox/messages/{message}'
*/
const messageForm = (args: { message: number | { id: number } } | [message: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: message.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::message
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:52
* @route '/whatsapp/cloud/sandbox/messages/{message}'
*/
messageForm.get = (args: { message: number | { id: number } } | [message: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: message.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::message
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:52
* @route '/whatsapp/cloud/sandbox/messages/{message}'
*/
messageForm.head = (args: { message: number | { id: number } } | [message: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: message.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

message.form = messageForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::storeParticipant
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
export const storeParticipant = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeParticipant.url(options),
    method: 'post',
})

storeParticipant.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/participants',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::storeParticipant
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
storeParticipant.url = (options?: RouteQueryOptions) => {
    return storeParticipant.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::storeParticipant
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
storeParticipant.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: storeParticipant.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::storeParticipant
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
const storeParticipantForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeParticipant.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::storeParticipant
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:62
* @route '/whatsapp/cloud/sandbox/participants'
*/
storeParticipantForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: storeParticipant.url(options),
    method: 'post',
})

storeParticipant.form = storeParticipantForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reply
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:79
* @route '/whatsapp/cloud/sandbox/reply'
*/
export const reply = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(options),
    method: 'post',
})

reply.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/reply',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reply
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:79
* @route '/whatsapp/cloud/sandbox/reply'
*/
reply.url = (options?: RouteQueryOptions) => {
    return reply.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reply
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:79
* @route '/whatsapp/cloud/sandbox/reply'
*/
reply.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reply.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reply
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:79
* @route '/whatsapp/cloud/sandbox/reply'
*/
const replyForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reply.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reply
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:79
* @route '/whatsapp/cloud/sandbox/reply'
*/
replyForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reply.url(options),
    method: 'post',
})

reply.form = replyForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::tap
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:93
* @route '/whatsapp/cloud/sandbox/tap'
*/
export const tap = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: tap.url(options),
    method: 'post',
})

tap.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/tap',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::tap
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:93
* @route '/whatsapp/cloud/sandbox/tap'
*/
tap.url = (options?: RouteQueryOptions) => {
    return tap.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::tap
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:93
* @route '/whatsapp/cloud/sandbox/tap'
*/
tap.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: tap.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::tap
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:93
* @route '/whatsapp/cloud/sandbox/tap'
*/
const tapForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: tap.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::tap
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:93
* @route '/whatsapp/cloud/sandbox/tap'
*/
tapForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: tap.url(options),
    method: 'post',
})

tap.form = tapForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendTemplate
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:112
* @route '/whatsapp/cloud/sandbox/send-template'
*/
export const sendTemplate = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendTemplate.url(options),
    method: 'post',
})

sendTemplate.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/send-template',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendTemplate
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:112
* @route '/whatsapp/cloud/sandbox/send-template'
*/
sendTemplate.url = (options?: RouteQueryOptions) => {
    return sendTemplate.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendTemplate
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:112
* @route '/whatsapp/cloud/sandbox/send-template'
*/
sendTemplate.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendTemplate.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendTemplate
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:112
* @route '/whatsapp/cloud/sandbox/send-template'
*/
const sendTemplateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sendTemplate.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendTemplate
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:112
* @route '/whatsapp/cloud/sandbox/send-template'
*/
sendTemplateForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sendTemplate.url(options),
    method: 'post',
})

sendTemplate.form = sendTemplateForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendText
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:129
* @route '/whatsapp/cloud/sandbox/send-text'
*/
export const sendText = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendText.url(options),
    method: 'post',
})

sendText.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/send-text',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendText
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:129
* @route '/whatsapp/cloud/sandbox/send-text'
*/
sendText.url = (options?: RouteQueryOptions) => {
    return sendText.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendText
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:129
* @route '/whatsapp/cloud/sandbox/send-text'
*/
sendText.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: sendText.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendText
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:129
* @route '/whatsapp/cloud/sandbox/send-text'
*/
const sendTextForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sendText.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::sendText
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:129
* @route '/whatsapp/cloud/sandbox/send-text'
*/
sendTextForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: sendText.url(options),
    method: 'post',
})

sendText.form = sendTextForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::status
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:139
* @route '/whatsapp/cloud/sandbox/status'
*/
export const status = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(options),
    method: 'post',
})

status.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/status',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::status
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:139
* @route '/whatsapp/cloud/sandbox/status'
*/
status.url = (options?: RouteQueryOptions) => {
    return status.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::status
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:139
* @route '/whatsapp/cloud/sandbox/status'
*/
status.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: status.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::status
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:139
* @route '/whatsapp/cloud/sandbox/status'
*/
const statusForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: status.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::status
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:139
* @route '/whatsapp/cloud/sandbox/status'
*/
statusForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: status.url(options),
    method: 'post',
})

status.form = statusForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::arm
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:150
* @route '/whatsapp/cloud/sandbox/faults'
*/
export const arm = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: arm.url(options),
    method: 'post',
})

arm.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/faults',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::arm
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:150
* @route '/whatsapp/cloud/sandbox/faults'
*/
arm.url = (options?: RouteQueryOptions) => {
    return arm.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::arm
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:150
* @route '/whatsapp/cloud/sandbox/faults'
*/
arm.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: arm.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::arm
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:150
* @route '/whatsapp/cloud/sandbox/faults'
*/
const armForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: arm.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::arm
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:150
* @route '/whatsapp/cloud/sandbox/faults'
*/
armForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: arm.url(options),
    method: 'post',
})

arm.form = armForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::closeWindow
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:157
* @route '/whatsapp/cloud/sandbox/close-window'
*/
export const closeWindow = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: closeWindow.url(options),
    method: 'post',
})

closeWindow.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/close-window',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::closeWindow
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:157
* @route '/whatsapp/cloud/sandbox/close-window'
*/
closeWindow.url = (options?: RouteQueryOptions) => {
    return closeWindow.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::closeWindow
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:157
* @route '/whatsapp/cloud/sandbox/close-window'
*/
closeWindow.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: closeWindow.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::closeWindow
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:157
* @route '/whatsapp/cloud/sandbox/close-window'
*/
const closeWindowForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: closeWindow.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::closeWindow
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:157
* @route '/whatsapp/cloud/sandbox/close-window'
*/
closeWindowForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: closeWindow.url(options),
    method: 'post',
})

closeWindow.form = closeWindowForm

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reset
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:164
* @route '/whatsapp/cloud/sandbox/reset'
*/
export const reset = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(options),
    method: 'post',
})

reset.definition = {
    methods: ["post"],
    url: '/whatsapp/cloud/sandbox/reset',
} satisfies RouteDefinition<["post"]>

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reset
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:164
* @route '/whatsapp/cloud/sandbox/reset'
*/
reset.url = (options?: RouteQueryOptions) => {
    return reset.definition.url + queryParams(options)
}

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reset
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:164
* @route '/whatsapp/cloud/sandbox/reset'
*/
reset.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: reset.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reset
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:164
* @route '/whatsapp/cloud/sandbox/reset'
*/
const resetForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reset.url(options),
    method: 'post',
})

/**
* @see \Callcocam\WhatsAppCloud\Http\Controllers\SandboxController::reset
* @see vendor/callcocam/laravel-whatsapp-cloud/src/Http/Controllers/SandboxController.php:164
* @route '/whatsapp/cloud/sandbox/reset'
*/
resetForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: reset.url(options),
    method: 'post',
})

reset.form = resetForm

const SandboxController = { index, state, message, storeParticipant, reply, tap, sendTemplate, sendText, status, arm, closeWindow, reset }

export default SandboxController