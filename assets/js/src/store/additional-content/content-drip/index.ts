/**
 * Content Drip Store Module
 * Exports all content drip functionality for the additional content store
 */

// Export action types and creators
export {
  CONTENT_DRIP_ACTION_TYPES,
  setContentDripSettings,
  setContentDripLoading,
  setContentDripError,
  setContentDripSaving,
  setContentDripSaveError,
  setPrerequisites,
  setPrerequisitesLoading,
  setPrerequisitesError,
  type ContentDripAction,
} from "./actions";

// Export async action creators (different from resolvers)
export {
  updateContentDripSettings as updateContentDripSettingsAction,
  duplicateContentDripSettings as duplicateContentDripSettingsAction,
} from "./actions";

// Export selectors and state interface
export {
  type AdditionalContentStoreStateWithContentDrip,
  getContentDripSettings,
  isContentDripLoading,
  getContentDripError,
  isContentDripSaving,
  getContentDripSaveError,
  getPrerequisites,
  isPrerequisitesLoading,
  getPrerequisitesError,
  hasContentDripSettings,
  isContentDripEnabled,
  getContentDripType,
  hasPrerequisites,
  getContentDripInfo,
  getPrerequisitesInfo,
} from "./selectors";

// Export resolvers
export {
  contentDripResolvers,
  getContentDripSettings as getContentDripSettingsResolver,
  updateContentDripSettings as updateContentDripSettingsResolver,
  getPrerequisites as getPrerequisitesResolver,
  duplicateContentDripSettings as duplicateContentDripSettingsResolver,
} from "./resolvers";
