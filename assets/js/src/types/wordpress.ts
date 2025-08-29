/**
 * WordPress package type declarations and global interface augmentations
 */

// ============================================================================
// WordPress Data Store Types
// ============================================================================

/**
 * WordPress API Fetch options interface
 */
export interface ApiFetchOptions {
  path: string;
  method?: string;
  data?: Record<string, any>;
  headers?: Record<string, string>;
  parse?: boolean;
}

/**
 * WordPress Post object interface
 */
export interface WP_Post {
  id: number;
  title: { rendered: string };
  content: { rendered: string };
  status: string;
  type: string;
  parent: number;
  meta: Record<string, any>;
  [key: string]: any;
}

/**
 * WordPress Notice interface
 */
export interface WP_Notice {
  id: string;
  content: string;
  status: "success" | "error" | "warning" | "info";
  isDismissible: boolean;
  type: "default" | "snackbar";
}

// ============================================================================
// WordPress Data Store Selectors
// ============================================================================

/**
 * Core Editor Store Selectors
 */
export interface CoreEditorSelectors {
  getCurrentPost(): WP_Post | null;
  getCurrentPostId(): number | null;
  getCurrentPostType(): string | null;
  getCurrentPostAttribute(attributeName: string): any;
  getEditedPostAttribute(attributeName: string): any;
  getEditedPostContent(): string;
  isEditedPostDirty(): boolean;
  isCurrentPostPublished(): boolean;
  isCurrentPostScheduled(): boolean;
  isSavingPost(): boolean;
  isAutosavingPost(): boolean;
  isPublishingPost(): boolean;
  hasCurrentPostChanged(): boolean;
  isEditedPostSaveable(): boolean;
  isEditedPostPublishable(): boolean;
  getCurrentPostLastRevisionId(): number | null;
  getCurrentPostRevisionsCount(): number;
}

/**
 * Core Editor Store Actions
 */
export interface CoreEditorActions {
  editPost(edits: Partial<WP_Post>): void;
  savePost(): void;
  autosave(): void;
  redo(): void;
  undo(): void;
  createUndoLevel(): void;
  updatePost(edits: Partial<WP_Post>): void;
  setupEditor(post: WP_Post, edits?: Partial<WP_Post>): void;
  resetPost(post: WP_Post): void;
  setupEditorState(post: WP_Post): void;
}

/**
 * Core Notices Store Selectors
 */
export interface CoreNoticesSelectors {
  getNotices(): WP_Notice[];
  getNotice(id: string): WP_Notice | undefined;
}

/**
 * Core Notices Store Actions
 */
export interface CoreNoticesActions {
  createNotice(
    status: WP_Notice["status"],
    content: string,
    options?: {
      id?: string;
      isDismissible?: boolean;
      type?: WP_Notice["type"];
      actions?: Array<{
        label: string;
        onClick: () => void;
      }>;
    }
  ): void;
  createSuccessNotice(content: string, options?: any): void;
  createErrorNotice(content: string, options?: any): void;
  createWarningNotice(content: string, options?: any): void;
  createInfoNotice(content: string, options?: any): void;
  removeNotice(id: string): void;
}

/**
 * WordPress Data Store Dispatch Function Type
 */
export type WordPressDispatch<T = any> = (storeKey: string) => T;

/**
 * WordPress Data Store Select Function Type
 */
export type WordPressSelect<T = any> = (storeKey: string) => T;

// ============================================================================
// WordPress Data Controls (for API_FETCH)
// ============================================================================

/**
 * API_FETCH control type for WordPress Data resolvers
 */
export interface API_FETCH_Control {
  type: "API_FETCH";
  request: ApiFetchOptions;
}

/**
 * DISPATCH control type for WordPress Data resolvers
 */
export interface DISPATCH_Control {
  type: "DISPATCH";
  storeKey: string;
  actionName: string;
  args: any[];
}

/**
 * SELECT control type for WordPress Data resolvers
 */
export interface SELECT_Control {
  type: "SELECT";
  storeKey: string;
  selectorName: string;
  args: any[];
}

/**
 * Union type for all WordPress Data controls
 */
export type WordPressDataControl = API_FETCH_Control | DISPATCH_Control | SELECT_Control;

// ============================================================================
// TutorPress API Interface
// ============================================================================

/**
 * TutorPress API interface for window.tutorpress.api
 */
export interface TutorPressApi {
  getTopics: (courseId: number) => Promise<any>;
  reorderTopics: (courseId: number, topicIds: number[]) => Promise<any>;
  duplicateTopic: (topicId: number, courseId: number) => Promise<any>;
}

/**
 * TutorPress Quiz utilities interface for window.tutorpress.quiz
 */
export interface TutorPressQuizUtils {
  getDefaultQuizSettings: () => any;
  getDefaultQuestionSettings: (questionType: any) => any;
  isValidQuizQuestion: (question: unknown) => boolean;
  isValidQuizDetails: (quiz: unknown) => boolean;
  createQuizError: (code: any, message: string, operation: any, context?: any) => any;
  // Quiz service methods
  saveQuiz: (quizData: any, courseId: number, topicId: number) => Promise<any>;
  getQuizDetails: (quizId: number) => Promise<any>;
  deleteQuiz: (quizId: number) => Promise<any>;
  duplicateQuiz: (sourceQuizId: number, topicId: number, courseId: number) => Promise<any>;
  // Service instance
  service: any;
}

/**
 * TutorPress Content Drip utilities interface for window.tutorpress.contentDrip
 */
export interface TutorPressContentDripUtils {
  getDefaultContentDripItemSettings: () => any;
  getEmptyContentDripInfo: (courseId: number) => any;
  isContentDripSettingsEmpty: (settings: any) => boolean;
  validateContentDripSettings: (settings: any, dripType: string) => { isValid: boolean; errors: string[] };
  isContentDripItemSettings: (value: any) => boolean;
  isContentDripInfo: (value: any) => boolean;
}

// ============================================================================
// Global Window Interface Augmentations
// ============================================================================

declare global {
  interface Window {
    /**
     * WordPress global object
     */
    wp: {
      apiFetch: (options: ApiFetchOptions) => Promise<any>;
      data?: {
        select: WordPressSelect;
        dispatch: WordPressDispatch;
        subscribe: (listener: () => void) => () => void;
        use: (plugin: any, options?: any) => void;
      };
      element?: {
        render: (element: JSX.Element, container: Element | null) => void;
        createElement: (type: any, props?: any, ...children: any[]) => JSX.Element;
      };
      hooks?: {
        addFilter: (hookName: string, namespace: string, callback: Function, priority?: number) => void;
        removeFilter: (hookName: string, namespace: string) => void;
        applyFilters: (hookName: string, value: any, ...args: any[]) => any;
        addAction: (hookName: string, namespace: string, callback: Function, priority?: number) => void;
        removeAction: (hookName: string, namespace: string) => void;
        doAction: (hookName: string, ...args: any[]) => void;
      };
    };

    /**
     * TutorPress curriculum configuration
     */
    tutorPressCurriculum?: {
      adminUrl: string;
      restUrl?: string;
      nonce?: string;
      courseId?: number;
    };

    /**
     * TutorPress API object for testing and debugging
     */
    tutorpress: {
      api: TutorPressApi;
      quiz: TutorPressQuizUtils;
      utils: any; // Quiz form utilities
      contentDrip?: TutorPressContentDripUtils; // Content drip utilities
      wc?: any; // WooCommerce service
      edd?: any; // EDD service
    };

    /**
     * TutorPress Freemius integration data
     */
    tutorpress_fs?: {
      canUsePremium: boolean;
      upgradeUrl: string;
      promo: {
        title: string;
        message: string;
        button: string;
      };
    };
  }
}

// ============================================================================
// Type Guards
// ============================================================================

/**
 * Type guard for WordPress Post objects
 */
export const isWPPost = (obj: unknown): obj is WP_Post => {
  return (
    typeof obj === "object" &&
    obj !== null &&
    "id" in obj &&
    "status" in obj &&
    "type" in obj &&
    typeof (obj as any).id === "number"
  );
};

/**
 * Type guard for Core Editor Store selectors
 */
export const isCoreEditorSelectors = (obj: unknown): obj is CoreEditorSelectors => {
  if (!obj || typeof obj !== "object") return false;

  const selectors = obj as Record<string, unknown>;
  return (
    typeof selectors.getCurrentPost === "function" &&
    typeof selectors.getCurrentPostId === "function" &&
    typeof selectors.isSavingPost === "function" &&
    typeof selectors.isAutosavingPost === "function" &&
    typeof selectors.isPublishingPost === "function"
  );
};

/**
 * Type guard for API_FETCH control
 */
export const isApiFetchControl = (control: unknown): control is API_FETCH_Control => {
  return (
    typeof control === "object" &&
    control !== null &&
    "type" in control &&
    (control as any).type === "API_FETCH" &&
    "request" in control
  );
};

// ============================================================================
// Utility Types
// ============================================================================

/**
 * WordPress Data Store configuration
 */
export interface WPDataStoreConfig<State = any, Actions = any, Selectors = any> {
  reducer: (state: State, action: any) => State;
  actions?: Actions;
  selectors?: Selectors;
  controls?: Record<string, (action: any) => any>;
  resolvers?: Record<string, any>;
  initialState?: State;
}

/**
 * WordPress Data Store registration options
 */
export interface WPDataStoreOptions {
  persist?: boolean | string[];
}
