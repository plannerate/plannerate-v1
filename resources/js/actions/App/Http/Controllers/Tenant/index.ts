import ImpersonationController from './ImpersonationController'
import Editor from './Editor'
import DashboardController from './DashboardController'
import CategoryController from './CategoryController'
import ProductController from './ProductController'
import ProductImageController from './ProductImageController'
import Products from './Products'
import ProductDimensionController from './ProductDimensionController'
import SimilarGroupController from './SimilarGroupController'
import StoreController from './StoreController'
import SaleController from './SaleController'
import ClusterController from './ClusterController'
import ProviderController from './ProviderController'
import PlanogramController from './PlanogramController'
import GondolaController from './GondolaController'
import UserController from './UserController'
import NotificationController from './NotificationController'
import SystemLogController from './SystemLogController'
import WorkflowKanbanController from './WorkflowKanbanController'
import WorkflowExecutionController from './WorkflowExecutionController'
import GondolaExecutionLayerController from './GondolaExecutionLayerController'
import WorkflowPlanogramStepController from './WorkflowPlanogramStepController'
import ReverbTestController from './ReverbTestController'

const Tenant = {
    ImpersonationController: Object.assign(ImpersonationController, ImpersonationController),
    Editor: Object.assign(Editor, Editor),
    DashboardController: Object.assign(DashboardController, DashboardController),
    CategoryController: Object.assign(CategoryController, CategoryController),
    ProductController: Object.assign(ProductController, ProductController),
    ProductImageController: Object.assign(ProductImageController, ProductImageController),
    Products: Object.assign(Products, Products),
    ProductDimensionController: Object.assign(ProductDimensionController, ProductDimensionController),
    SimilarGroupController: Object.assign(SimilarGroupController, SimilarGroupController),
    StoreController: Object.assign(StoreController, StoreController),
    SaleController: Object.assign(SaleController, SaleController),
    ClusterController: Object.assign(ClusterController, ClusterController),
    ProviderController: Object.assign(ProviderController, ProviderController),
    PlanogramController: Object.assign(PlanogramController, PlanogramController),
    GondolaController: Object.assign(GondolaController, GondolaController),
    UserController: Object.assign(UserController, UserController),
    NotificationController: Object.assign(NotificationController, NotificationController),
    SystemLogController: Object.assign(SystemLogController, SystemLogController),
    WorkflowKanbanController: Object.assign(WorkflowKanbanController, WorkflowKanbanController),
    WorkflowExecutionController: Object.assign(WorkflowExecutionController, WorkflowExecutionController),
    GondolaExecutionLayerController: Object.assign(GondolaExecutionLayerController, GondolaExecutionLayerController),
    WorkflowPlanogramStepController: Object.assign(WorkflowPlanogramStepController, WorkflowPlanogramStepController),
    ReverbTestController: Object.assign(ReverbTestController, ReverbTestController),
}

export default Tenant