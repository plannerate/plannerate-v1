import WebhookController from './WebhookController'
import TemplatePanelController from './TemplatePanelController'
import SandboxController from './SandboxController'

const Controllers = {
    WebhookController: Object.assign(WebhookController, WebhookController),
    TemplatePanelController: Object.assign(TemplatePanelController, TemplatePanelController),
    SandboxController: Object.assign(SandboxController, SandboxController),
}

export default Controllers