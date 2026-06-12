import GondolaTenantController from './GondolaTenantController'
import GondolaAnalysisController from './GondolaAnalysisController'
import GondolaPdfPreviewController from './GondolaPdfPreviewController'
import GondolaExportController from './GondolaExportController'
import GondolaShareController from './GondolaShareController'
import AnalysisExportController from './AnalysisExportController'
import Editor from './Editor'
import Api from './Api'
import Generation from './Generation'
import Templates from './Templates'

const Controllers = {
    GondolaTenantController: Object.assign(GondolaTenantController, GondolaTenantController),
    GondolaAnalysisController: Object.assign(GondolaAnalysisController, GondolaAnalysisController),
    GondolaPdfPreviewController: Object.assign(GondolaPdfPreviewController, GondolaPdfPreviewController),
    GondolaExportController: Object.assign(GondolaExportController, GondolaExportController),
    GondolaShareController: Object.assign(GondolaShareController, GondolaShareController),
    AnalysisExportController: Object.assign(AnalysisExportController, AnalysisExportController),
    Editor: Object.assign(Editor, Editor),
    Api: Object.assign(Api, Api),
    Generation: Object.assign(Generation, Generation),
    Templates: Object.assign(Templates, Templates),
}

export default Controllers