import gondola from './gondola'
import gondolaReport from './gondola-report'

const exportMethod = {
    gondola: Object.assign(gondola, gondola),
    gondolaReport: Object.assign(gondolaReport, gondolaReport),
}

export default exportMethod