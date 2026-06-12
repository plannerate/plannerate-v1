import AutoPlanogramController from './AutoPlanogramController'
import GondolaSlotOverrideController from './GondolaSlotOverrideController'
import PlanogramProductRuleController from './PlanogramProductRuleController'

const Generation = {
    AutoPlanogramController: Object.assign(AutoPlanogramController, AutoPlanogramController),
    GondolaSlotOverrideController: Object.assign(GondolaSlotOverrideController, GondolaSlotOverrideController),
    PlanogramProductRuleController: Object.assign(PlanogramProductRuleController, PlanogramProductRuleController),
}

export default Generation
