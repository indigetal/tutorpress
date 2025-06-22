/**
 * Certificate Template Card Component
 *
 * Individual certificate template card with hover states, preview/select functionality,
 * and visual feedback for selection state. Follows established TutorPress component patterns.
 *
 * @package TutorPress
 * @subpackage Components/Certificate
 * @since 1.0.0
 */
import React, { useState } from "react";
import { Button, Icon } from "@wordpress/components";
import { check } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";

// Types
import type { CertificateTemplate } from "../../../types/certificate";

// ============================================================================
// CertificateCard Component
// ============================================================================

interface CertificateCardProps {
  /** Template data */
  template: CertificateTemplate;
  /** Whether this template is currently selected */
  isSelected: boolean;
  /** Selection handler */
  onSelect: (template: CertificateTemplate) => void;
  /** Preview handler */
  onPreview: (template: CertificateTemplate) => void;
  /** Whether the card is disabled */
  disabled?: boolean;
  /** Loading state for the card */
  isLoading?: boolean;
}

/**
 * Certificate Template Card
 *
 * Features:
 * - Hover states with Preview/Select button overlay
 * - Click-to-select functionality
 * - Visual feedback for selected state
 * - Image loading states and error handling
 * - Accessibility support
 */
export const CertificateCard: React.FC<CertificateCardProps> = ({
  template,
  isSelected,
  onSelect,
  onPreview,
  disabled = false,
  isLoading = false,
}) => {
  const [imageError, setImageError] = useState(false);
  const [imageLoading, setImageLoading] = useState(true);

  const handleSelect = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!disabled && !isLoading) {
      onSelect(template);
    }
  };

  const handlePreview = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!disabled && !isLoading) {
      onPreview(template);
    }
  };

  const handleImageLoad = () => {
    setImageLoading(false);
  };

  const handleImageError = () => {
    setImageError(true);
    setImageLoading(false);
  };

  const imageSrc = template.preview_src || template.background_src;
  const isNoneTemplate = template.key === "none";
  const cardClasses = [
    "certificate-card",
    isSelected && "certificate-card--selected",
    disabled && "certificate-card--disabled",
    isLoading && "certificate-card--loading",
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <div className={cardClasses}>
      <div className="certificate-card__image">
        {imageLoading && !imageError && (
          <div className="certificate-card__image-loading">
            <div className="certificate-card__spinner"></div>
          </div>
        )}

        {imageError ? (
          <div className="certificate-card__image-error">
            <span>{__("Preview not available", "tutorpress")}</span>
          </div>
        ) : (
          <img
            src={imageSrc}
            alt={template.name}
            onLoad={handleImageLoad}
            onError={handleImageError}
            style={{ display: imageLoading ? "none" : "block" }}
          />
        )}
      </div>

      {/* White hover bar - only visible on hover */}
      <div className="certificate-card__hover-info">
        <h4 className="certificate-card__title">{template.name}</h4>
        <div className="certificate-card__actions">
          {!isNoneTemplate && (
            <Button variant="secondary" size="small" onClick={handlePreview} disabled={disabled || isLoading}>
              {__("Preview", "tutorpress")}
            </Button>
          )}
          <Button variant="primary" size="small" onClick={handleSelect} disabled={disabled || isLoading}>
            {isSelected ? __("Selected", "tutorpress") : __("Select", "tutorpress")}
          </Button>
        </div>
      </div>

      {/* Selection indicator - always visible when selected */}
      {isSelected && (
        <div className="certificate-card__selected-badge">
          <Icon icon={check} />
        </div>
      )}

      {/* Loading overlay */}
      {isLoading && (
        <div className="certificate-card__loading-overlay">
          <div className="certificate-card__spinner"></div>
        </div>
      )}
    </div>
  );
};
