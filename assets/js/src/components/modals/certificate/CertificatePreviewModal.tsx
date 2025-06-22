import React from "react";
import { Modal } from "@wordpress/components";
import { Icon, close, arrowLeft, arrowRight, check } from "@wordpress/icons";
import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { CertificateTemplate } from "../../../types/certificate";

interface CertificatePreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  template: CertificateTemplate | null;
  onSelect?: (template: CertificateTemplate) => void;
  onNavigate?: (direction: "prev" | "next") => void;
  canNavigate?: boolean;
}

const CertificatePreviewModal: React.FC<CertificatePreviewModalProps> = ({
  isOpen,
  onClose,
  template,
  onSelect,
  onNavigate,
  canNavigate = false,
}) => {
  const { selectedTemplate, isSelectionSaving } = useSelect((select: any) => {
    const store = select("tutorpress/certificate");
    return {
      selectedTemplate: store.getCertificateSelection().selectedTemplate,
      isSelectionSaving: store.isCertificateSelectionSaving(),
    };
  }, []);

  const isCurrentlySelected = template && selectedTemplate === template.key;

  const handleSelect = () => {
    if (template && onSelect) {
      onSelect(template);
    }
  };

  const handleKeyDown = (event: React.KeyboardEvent) => {
    if (!canNavigate || !onNavigate) return;

    switch (event.key) {
      case "ArrowLeft":
        event.preventDefault();
        onNavigate("prev");
        break;
      case "ArrowRight":
        event.preventDefault();
        onNavigate("next");
        break;
      case "Escape":
        event.preventDefault();
        onClose();
        break;
    }
  };

  if (!isOpen || !template) {
    return null;
  }

  const previewImage = template.preview_src || template.background_src;

  return (
    <Modal
      className="certificate-preview-modal"
      onRequestClose={onClose}
      shouldCloseOnClickOutside={true}
      shouldCloseOnEsc={true}
      onKeyDown={handleKeyDown}
      style={{
        maxWidth: "95vw",
        maxHeight: "95vh",
      }}
    >
      <div className="certificate-preview-modal__container">
        {/* Navigation arrows */}
        {canNavigate && onNavigate && (
          <>
            <Button
              className="certificate-preview-modal__nav certificate-preview-modal__nav--prev"
              icon={arrowLeft}
              onClick={() => onNavigate("prev")}
              label={__("Previous template", "tutorpress")}
            />
            <Button
              className="certificate-preview-modal__nav certificate-preview-modal__nav--next"
              icon={arrowRight}
              onClick={() => onNavigate("next")}
              label={__("Next template", "tutorpress")}
            />
          </>
        )}

        {/* Preview content */}
        <div className="certificate-preview-modal__content">
          <div className="certificate-preview-modal__image">
            {previewImage ? (
              <img
                src={previewImage}
                alt={template.name}
                onError={(e) => {
                  // Fallback to background image if preview fails
                  if (template.background_src && previewImage !== template.background_src) {
                    (e.target as HTMLImageElement).src = template.background_src;
                  }
                }}
              />
            ) : (
              <div className="certificate-preview-modal__no-image">
                <p>{__("Preview not available", "tutorpress")}</p>
              </div>
            )}
          </div>

          {/* Template info and actions */}
          <div className="certificate-preview-modal__info">
            <div className="certificate-preview-modal__details">
              <h3 className="certificate-preview-modal__title">{template.name}</h3>
            </div>

            {/* Action buttons */}
            <div className="certificate-preview-modal__actions">
              {onSelect && (
                <Button
                  variant={isCurrentlySelected ? "secondary" : "primary"}
                  onClick={handleSelect}
                  disabled={isSelectionSaving}
                  icon={isCurrentlySelected ? check : undefined}
                >
                  {isSelectionSaving
                    ? __("Selecting...", "tutorpress")
                    : isCurrentlySelected
                      ? __("Selected", "tutorpress")
                      : __("Select Template", "tutorpress")}
                </Button>
              )}
            </div>
          </div>
        </div>
      </div>
    </Modal>
  );
};

export default CertificatePreviewModal;
