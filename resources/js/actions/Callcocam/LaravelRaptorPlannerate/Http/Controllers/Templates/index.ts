import PlanogramTemplateController from './PlanogramTemplateController'
import TemplateSlotController from './TemplateSlotController'

const Templates = {
    PlanogramTemplateController: Object.assign(PlanogramTemplateController, PlanogramTemplateController),
    TemplateSlotController: Object.assign(TemplateSlotController, TemplateSlotController),
}

export default Templates