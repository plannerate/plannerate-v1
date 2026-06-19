import DashboardController from './DashboardController'
import PlanController from './PlanController'
import TenantController from './TenantController'
import RoleController from './RoleController'
import ModuleController from './ModuleController'
import IntegrationApiController from './IntegrationApiController'
import UserController from './UserController'
import PermissionController from './PermissionController'
import EanReferenceController from './EanReferenceController'
import UsefulLinkController from './UsefulLinkController'
import NotificationController from './NotificationController'
import TenantCloudflareController from './TenantCloudflareController'
import TenantUserAccessController from './TenantUserAccessController'
import TenantSocialiteProviderController from './TenantSocialiteProviderController'
import TenantIntegrationController from './TenantIntegrationController'
import WorkflowTemplateController from './WorkflowTemplateController'

const Landlord = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    PlanController: Object.assign(PlanController, PlanController),
    TenantController: Object.assign(TenantController, TenantController),
    RoleController: Object.assign(RoleController, RoleController),
    ModuleController: Object.assign(ModuleController, ModuleController),
    IntegrationApiController: Object.assign(IntegrationApiController, IntegrationApiController),
    UserController: Object.assign(UserController, UserController),
    PermissionController: Object.assign(PermissionController, PermissionController),
    EanReferenceController: Object.assign(EanReferenceController, EanReferenceController),
    UsefulLinkController: Object.assign(UsefulLinkController, UsefulLinkController),
    NotificationController: Object.assign(NotificationController, NotificationController),
    TenantCloudflareController: Object.assign(TenantCloudflareController, TenantCloudflareController),
    TenantUserAccessController: Object.assign(TenantUserAccessController, TenantUserAccessController),
    TenantSocialiteProviderController: Object.assign(TenantSocialiteProviderController, TenantSocialiteProviderController),
    TenantIntegrationController: Object.assign(TenantIntegrationController, TenantIntegrationController),
    WorkflowTemplateController: Object.assign(WorkflowTemplateController, WorkflowTemplateController),
}

export default Landlord