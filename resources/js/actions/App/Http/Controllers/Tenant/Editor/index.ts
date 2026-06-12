import ClientPlanogramController from './ClientPlanogramController'
import EditorPlanogramController from './EditorPlanogramController'

const Editor = {
    ClientPlanogramController: Object.assign(ClientPlanogramController, ClientPlanogramController),
    EditorPlanogramController: Object.assign(EditorPlanogramController, EditorPlanogramController),
}

export default Editor