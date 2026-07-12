import AutoPlanogramController from './AutoPlanogramController'
import PlanogramGenerationRunController from './PlanogramGenerationRunController'
import GondolaSlotOverrideController from './GondolaSlotOverrideController'
import PlanogramProductRuleController from './PlanogramProductRuleController'

const Generation = {
    AutoPlanogramController: Object.assign(AutoPlanogramController, AutoPlanogramController),
    PlanogramGenerationRunController: Object.assign(PlanogramGenerationRunController, PlanogramGenerationRunController),
    GondolaSlotOverrideController: Object.assign(GondolaSlotOverrideController, GondolaSlotOverrideController),
    PlanogramProductRuleController: Object.assign(PlanogramProductRuleController, PlanogramProductRuleController),
}

export default Generation