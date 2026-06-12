import Landlord from './Landlord'
import Auth from './Auth'
import Tenant from './Tenant'
import Settings from './Settings'

const Controllers = {
    Landlord: Object.assign(Landlord, Landlord),
    Auth: Object.assign(Auth, Auth),
    Tenant: Object.assign(Tenant, Tenant),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers