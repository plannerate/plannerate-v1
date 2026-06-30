import GondolaTenantController from './GondolaTenantController'
import GondolaAnalysisController from './GondolaAnalysisController'
import GondolaPdfPreviewController from './GondolaPdfPreviewController'
import GondolaExportController from './GondolaExportController'
import GondolaShareController from './GondolaShareController'
import Export from './Export'
import Editor from './Editor'
import AnalysisExportController from './AnalysisExportController'
import Api from './Api'
import Generation from './Generation'
import Templates from './Templates'

const Controllers = {
    GondolaTenantController: Object.assign(GondolaTenantController, GondolaTenantController),
    GondolaAnalysisController: Object.assign(GondolaAnalysisController, GondolaAnalysisController),
    GondolaPdfPreviewController: Object.assign(GondolaPdfPreviewController, GondolaPdfPreviewController),
    GondolaExportController: Object.assign(GondolaExportController, GondolaExportController),
    GondolaShareController: Object.assign(GondolaShareController, GondolaShareController),
    Export: Object.assign(Export, Export),
    Editor: Object.assign(Editor, Editor),
    AnalysisExportController: Object.assign(AnalysisExportController, AnalysisExportController),
    Api: Object.assign(Api, Api),
    Generation: Object.assign(Generation, Generation),
    Templates: Object.assign(Templates, Templates),
}

export default Controllers