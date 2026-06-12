import ScoringWeightsController from './ScoringWeightsController'
import AdjacencyMatrixController from './AdjacencyMatrixController'
import PlanogramSettingsController from './PlanogramSettingsController'
import ShelfLevelPreferencesController from './ShelfLevelPreferencesController'
import ProfileController from './ProfileController'
import SecurityController from './SecurityController'

const Settings = {
    ScoringWeightsController: Object.assign(ScoringWeightsController, ScoringWeightsController),
    AdjacencyMatrixController: Object.assign(AdjacencyMatrixController, AdjacencyMatrixController),
    PlanogramSettingsController: Object.assign(PlanogramSettingsController, PlanogramSettingsController),
    ShelfLevelPreferencesController: Object.assign(ShelfLevelPreferencesController, ShelfLevelPreferencesController),
    ProfileController: Object.assign(ProfileController, ProfileController),
    SecurityController: Object.assign(SecurityController, SecurityController),
}

export default Settings