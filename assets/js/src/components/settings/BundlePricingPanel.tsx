/**
 * Bundle Pricing Panel Component
 *
 * Skeleton component for bundle pricing and ribbon settings.
 * Will be expanded in Setting 3: Pricing & Ribbon Display.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { PanelRow, TextControl, SelectControl, Notice } from "@wordpress/components";

// Import types (will be expanded as needed)
import type { Bundle, BundlePricing } from "../../types/bundle";

/**
 * Bundle Pricing Panel Component
 *
 * Features (to be implemented):
 * - Regular price input
 * - Sale price input
 * - Ribbon type selection
 * - Pricing validation
 * - Pricing saving/loading
 */
const BundlePricingPanel: React.FC = () => {
  // Placeholder state (will be expanded)
  const [regularPrice, setRegularPrice] = React.useState("");
  const [salePrice, setSalePrice] = React.useState("");
  const [ribbonType, setRibbonType] = React.useState("none");
  const [isLoading, setIsLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  // Placeholder ribbon options (will be expanded)
  const ribbonOptions = [
    { label: __("No Ribbon", "tutorpress"), value: "none" },
    { label: __("Best Value", "tutorpress"), value: "best_value" },
    { label: __("Most Popular", "tutorpress"), value: "most_popular" },
    { label: __("Limited Time", "tutorpress"), value: "limited_time" },
  ];

  // Placeholder handlers (will be expanded)
  const handleRegularPriceChange = (value: string) => {
    // TODO: Implement regular price change handling
    setRegularPrice(value);
    console.log("Regular price changed - to be implemented", value);
  };

  const handleSalePriceChange = (value: string) => {
    // TODO: Implement sale price change handling
    setSalePrice(value);
    console.log("Sale price changed - to be implemented", value);
  };

  const handleRibbonTypeChange = (value: string) => {
    // TODO: Implement ribbon type change handling
    setRibbonType(value);
    console.log("Ribbon type changed - to be implemented", value);
  };

  const handleSavePricing = async () => {
    // TODO: Implement pricing saving
    console.log("Save pricing functionality - to be implemented", {
      regularPrice,
      salePrice,
      ribbonType,
    });
  };

  return (
    <PluginDocumentSettingPanel
      name="tutorpress-bundle-pricing"
      title={__("Bundle Pricing", "tutorpress")}
      className="tutorpress-bundle-pricing-panel"
    >
      {/* Error display */}
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      {/* Loading state */}
      {isLoading && <div className="tutorpress-loading">{__("Loading pricing...", "tutorpress")}</div>}

      {/* Regular Price */}
      <PanelRow>
        <TextControl
          label={__("Regular Price", "tutorpress")}
          value={regularPrice}
          onChange={handleRegularPriceChange}
          placeholder={__("0.00", "tutorpress")}
          disabled={isLoading}
          help={__("Enter the regular price for this bundle.", "tutorpress")}
        />
      </PanelRow>

      {/* Sale Price */}
      <PanelRow>
        <TextControl
          label={__("Sale Price", "tutorpress")}
          value={salePrice}
          onChange={handleSalePriceChange}
          placeholder={__("0.00", "tutorpress")}
          disabled={isLoading}
          help={__("Enter the sale price (optional).", "tutorpress")}
        />
      </PanelRow>

      {/* Ribbon Type */}
      <PanelRow>
        <SelectControl
          label={__("Ribbon Display", "tutorpress")}
          value={ribbonType}
          options={ribbonOptions}
          onChange={handleRibbonTypeChange}
          disabled={isLoading}
          help={__("Select a ribbon to display on this bundle.", "tutorpress")}
        />
      </PanelRow>

      {/* Save button */}
      <PanelRow>
        <button type="button" className="button button-primary" onClick={handleSavePricing} disabled={isLoading}>
          {__("Save Pricing", "tutorpress")}
        </button>
      </PanelRow>
    </PluginDocumentSettingPanel>
  );
};

export default BundlePricingPanel;
