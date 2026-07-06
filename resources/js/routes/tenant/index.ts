import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
import gondola from './gondola'
import gondolas from './gondolas'
import api from './api'
import planogramTemplates from './planogram-templates'
import planogramProductRules from './planogram-product-rules'
import auth from './auth'
import impersonation from './impersonation'
import passwordSetup from './password-setup'
import editor from './editor'
import planograms from './planograms'
import categories from './categories'
import products from './products'
import dimensions from './dimensions'
import similarGroups from './similar-groups'
import stores from './stores'
import sales from './sales'
import clusters from './clusters'
import providers from './providers'
import users from './users'
import notifications from './notifications'
import systemLogs from './system-logs'
import scoringWeights from './scoring-weights'
import adjacencyMatrix from './adjacency-matrix'
import planogramSettings from './planogram-settings'
import shelfLevelPreferences from './shelf-level-preferences'
import kanban from './kanban'
import executions from './executions'
import reverbTest from './reverb-test'
/**
* @see \App\Http\Controllers\Tenant\DashboardController::dashboard
* @see app/Http/Controllers/Tenant/DashboardController.php:17
* @route '/'
*/
export const dashboard = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\DashboardController::dashboard
* @see app/Http/Controllers/Tenant/DashboardController.php:17
* @route '/'
*/
dashboard.url = (options?: RouteQueryOptions) => {
    return dashboard.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\DashboardController::dashboard
* @see app/Http/Controllers/Tenant/DashboardController.php:17
* @route '/'
*/
dashboard.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\DashboardController::dashboard
* @see app/Http/Controllers/Tenant/DashboardController.php:17
* @route '/'
*/
dashboard.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\DashboardController::dashboard
* @see app/Http/Controllers/Tenant/DashboardController.php:17
* @route '/'
*/
const dashboardForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\DashboardController::dashboard
* @see app/Http/Controllers/Tenant/DashboardController.php:17
* @route '/'
*/
dashboardForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\DashboardController::dashboard
* @see app/Http/Controllers/Tenant/DashboardController.php:17
* @route '/'
*/
dashboardForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: dashboard.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

dashboard.form = dashboardForm

const tenant = {
    gondola: Object.assign(gondola, gondola),
    gondolas: Object.assign(gondolas, gondolas),
    api: Object.assign(api, api),
    planogramTemplates: Object.assign(planogramTemplates, planogramTemplates),
    planogramProductRules: Object.assign(planogramProductRules, planogramProductRules),
    auth: Object.assign(auth, auth),
    impersonation: Object.assign(impersonation, impersonation),
    passwordSetup: Object.assign(passwordSetup, passwordSetup),
    editor: Object.assign(editor, editor),
    planograms: Object.assign(planograms, planograms),
    dashboard: Object.assign(dashboard, dashboard),
    categories: Object.assign(categories, categories),
    products: Object.assign(products, products),
    dimensions: Object.assign(dimensions, dimensions),
    similarGroups: Object.assign(similarGroups, similarGroups),
    stores: Object.assign(stores, stores),
    sales: Object.assign(sales, sales),
    clusters: Object.assign(clusters, clusters),
    providers: Object.assign(providers, providers),
    users: Object.assign(users, users),
    notifications: Object.assign(notifications, notifications),
    systemLogs: Object.assign(systemLogs, systemLogs),
    scoringWeights: Object.assign(scoringWeights, scoringWeights),
    adjacencyMatrix: Object.assign(adjacencyMatrix, adjacencyMatrix),
    planogramSettings: Object.assign(planogramSettings, planogramSettings),
    shelfLevelPreferences: Object.assign(shelfLevelPreferences, shelfLevelPreferences),
    kanban: Object.assign(kanban, kanban),
    executions: Object.assign(executions, executions),
    reverbTest: Object.assign(reverbTest, reverbTest),
}

export default tenant