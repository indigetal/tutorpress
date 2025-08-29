import React from "react";
import { __ } from "@wordpress/i18n";
import { PanelRow, Button } from "@wordpress/components";

interface PromoPanelProps {
  className?: string;
}

/**
 * Reusable promo component for Gutenberg panels when premium features are locked
 */
const PromoPanel: React.FC<PromoPanelProps> = ({ className = "" }) => {
  // Get Freemius data from window object
  const freemiusData = window.tutorpress_fs;

  // Fallback values if Freemius data is not available
  const title = freemiusData?.promo?.title || __("Unlock TutorPress Pro", "tutorpress");
  const message = freemiusData?.promo?.message || __("Activate to continue using this feature.", "tutorpress");
  const buttonText = freemiusData?.promo?.button || __("Upgrade", "tutorpress");
  const upgradeUrl = freemiusData?.upgradeUrl || "#";

  return (
    <PanelRow>
      <div className={`tutorpress-promo ${className}`} style={{ width: "100%", textAlign: "center", padding: "20px" }}>
        <h3 style={{ margin: "0 0 10px 0", fontSize: "16px", fontWeight: "600", textTransform: "none" }}>{title}</h3>
        <p style={{ margin: "0 0 15px 0", color: "#757575" }}>{message}</p>
        <Button variant="primary" href={upgradeUrl} target="_blank" rel="noopener noreferrer">
          {buttonText}
        </Button>
      </div>
    </PanelRow>
  );
};

export default PromoPanel;
