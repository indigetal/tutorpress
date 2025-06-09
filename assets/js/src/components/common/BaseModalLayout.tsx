/**
 * Base Modal Layout Component
 *
 * @description Reusable modal layout structure extracted from Quiz Modal to support DRY principles.
 *              Provides consistent modal container, loading states, error handling, and responsive
 *              layout that can be shared across different modal types (Quiz, Interactive Quiz, etc.).
 *              Maintains compatibility with existing quiz-modal CSS classes.
 *
 * @features
 * - Consistent modal size and responsive behavior
 * - Loading state with spinner and custom message
 * - Error state with retry functionality
 * - Content container with proper scrolling
 * - Support for custom CSS classes
 * - Generic header and main content areas
 *
 * @usage
 * <BaseModalLayout
 *   isOpen={isOpen}
 *   onClose={onClose}
 *   className="quiz-modal"
 *   isLoading={isLoading}
 *   loadingMessage="Loading quiz data..."
 *   loadError={loadError}
 *   onRetry={handleRetry}
 *   header={<QuizHeader ... />}
 * >
 *   <TabPanel>...</TabPanel>
 * </BaseModalLayout>
 *
 * @package TutorPress
 * @subpackage Components/Common
 * @since 1.0.0
 */

import React from "react";
import { Modal, Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export interface BaseModalLayoutProps {
  /** Whether the modal is open */
  isOpen: boolean;
  /** Function to call when modal should be closed */
  onClose: () => void;
  /** Additional CSS class name for the modal */
  className?: string;
  /** Whether the modal is in a loading state */
  isLoading?: boolean;
  /** Custom loading message */
  loadingMessage?: string;
  /** Error message to display if loading fails */
  loadError?: string | null;
  /** Function to call when retry button is clicked */
  onRetry?: () => void;
  /** Modal header component */
  header?: React.ReactNode;
  /** Main modal content */
  children: React.ReactNode;
}

export const BaseModalLayout: React.FC<BaseModalLayoutProps> = ({
  isOpen,
  onClose,
  className = "tutorpress-modal",
  isLoading = false,
  loadingMessage = __("Loading...", "tutorpress"),
  loadError = null,
  onRetry,
  header,
  children,
}) => {
  if (!isOpen) {
    return null;
  }

  return (
    <Modal onRequestClose={onClose} className={className} size="large">
      <div className={`${className}-content`}>
        {/* Modal Header */}
        {header}

        {/* Loading state */}
        {isLoading && (
          <div className={`${className}-loading`} style={{ padding: "40px", textAlign: "center" }}>
            <Spinner style={{ margin: "0 auto 16px" }} />
            <p>{loadingMessage}</p>
          </div>
        )}

        {/* Error state */}
        {loadError && (
          <div className={`${className}-error`} style={{ padding: "20px" }}>
            <Notice status="error" isDismissible={false}>
              <strong>{__("Error:", "tutorpress")}</strong> {loadError}
            </Notice>
            <div style={{ marginTop: "16px", textAlign: "center" }}>
              {onRetry && (
                <Button variant="primary" onClick={onRetry}>
                  {__("Try Again", "tutorpress")}
                </Button>
              )}
              <Button variant="secondary" onClick={onClose} style={{ marginLeft: onRetry ? "8px" : "0" }}>
                {__("Cancel", "tutorpress")}
              </Button>
            </div>
          </div>
        )}

        {/* Main content - only show when not loading and no error */}
        {!isLoading && !loadError && children}
      </div>
    </Modal>
  );
};
