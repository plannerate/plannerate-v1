import plans from './plans'
import tenants from './tenants'
import roles from './roles'
import modules from './modules'
import integrationApis from './integration-apis'
import users from './users'
import permissions from './permissions'
import eanReferences from './ean-references'
import usefulLinks from './useful-links'
import notifications from './notifications'

const landlord = {
    plans: Object.assign(plans, plans),
    tenants: Object.assign(tenants, tenants),
    roles: Object.assign(roles, roles),
    modules: Object.assign(modules, modules),
    integrationApis: Object.assign(integrationApis, integrationApis),
    users: Object.assign(users, users),
    permissions: Object.assign(permissions, permissions),
    eanReferences: Object.assign(eanReferences, eanReferences),
    usefulLinks: Object.assign(usefulLinks, usefulLinks),
    notifications: Object.assign(notifications, notifications),
}

export default landlord