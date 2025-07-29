/**
 * Bundle Benefits Metabox Component
 *
 * Skeleton component for bundle benefits ("What Will I Learn") functionality.
 * Will be expanded in Setting 2: "What Will I Learn" Text Area.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React from "react";
import { __ } from "@wordpress/i18n";
import { TextareaControl, Notice } from "@wordpress/components";

// Import types (will be expanded as needed)
import type { Bundle } from "../../../types/bundle";

/**
 * Bundle Benefits Metabox Component
 *
 * Features (to be implemented):
 * - Benefits text area input
 * - Benefits validation
 * - Benefits saving/loading
 * - Benefits preview
 */
const Benefits: React.FC = () => {
  // Placeholder state (will be expanded)
  const [benefits, setBenefits] = React.useState("");
  const [isLoading, setIsLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [isSaving, setIsSaving] = React.useState(false);

  // Placeholder handlers (will be expanded)
  const handleBenefitsChange = (value: string) => {
    // TODO: Implement benefits change handling
    setBenefits(value);
    console.log("Benefits changed - to be implemented", value);
  };

  const handleSaveBenefits = async () => {
    // TODO: Implement benefits saving
    setIsSaving(true);
    console.log("Save benefits functionality - to be implemented", benefits);

    // Simulate async operation
    setTimeout(() => {
      setIsSaving(false);
    }, 1000);
  };

  const handleLoadBenefits = async () => {
    // TODO: Implement benefits loading
    setIsLoading(true);
    console.log("Load benefits functionality - to be implemented");

    // Simulate async operation
    setTimeout(() => {
      setIsLoading(false);
    }, 1000);
  };

  return (
    <div className="tutorpress-bundle-benefits">
      <div className="tutorpress-metabox-header">
        <h3>{__("What Will I Learn?", "tutorpress")}</h3>
        <p className="description">{__("Describe what students will learn from this bundle.", "tutorpress")}</p>
      </div>

      <div className="tutorpress-metabox-content">
        {/* Error display */}
        {error && (
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        )}

        {/* Loading state */}
        {isLoading && <div className="tutorpress-loading">{__("Loading benefits...", "tutorpress")}</div>}

        {/* Benefits text area */}
        <div className="tutorpress-benefits-input">
          <TextareaControl
            label={__("Bundle Benefits", "tutorpress")}
            value={benefits}
            onChange={handleBenefitsChange}
            placeholder={__("Enter what students will learn from this bundle...", "tutorpress")}
            disabled={isLoading}
            rows={8}
            help={__("Use bullet points or paragraphs to describe the learning outcomes.", "tutorpress")}
          />
        </div>

        {/* Save button */}
        <div className="tutorpress-metabox-actions">
          <button
            type="button"
            className="button button-primary"
            onClick={handleSaveBenefits}
            disabled={isLoading || isSaving}
          >
            {isSaving ? __("Saving...", "tutorpress") : __("Save Benefits", "tutorpress")}
          </button>
        </div>
      </div>
    </div>
  );
};

export default Benefits;
