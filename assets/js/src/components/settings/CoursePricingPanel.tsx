import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, RadioControl, TextControl } from "@wordpress/components";

// Import course settings types
import type { CourseSettings } from "../../types/courses";

const CoursePricingPanel: React.FC = () => {
  // Get settings from our store and Gutenberg store
  const { postType, settings, error, isLoading } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      settings: select("tutorpress/course-settings").getSettings(),
      error: select("tutorpress/course-settings").getError(),
      isLoading: select("tutorpress/course-settings").getFetchState().isLoading,
    }),
    []
  );

  // Get dispatch actions
  const { updateSettings } = useDispatch("tutorpress/course-settings");

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Show loading state while fetching settings
  if (isLoading) {
    return (
      <PluginDocumentSettingPanel
        name="course-pricing-settings"
        title={__("Pricing Model", "tutorpress")}
        className="tutorpress-course-pricing-panel"
      >
        <PanelRow>
          <div style={{ width: "100%", textAlign: "center", padding: "20px 0" }}>
            <Spinner />
          </div>
        </PanelRow>
      </PluginDocumentSettingPanel>
    );
  }

  // Handle pricing model change
  const handlePricingModelChange = (value: string) => {
    if (settings) {
      updateSettings({
        ...settings,
        pricing_model: value,
        is_free: value === "free",
        // Reset price fields when switching to free
        ...(value === "free" && {
          price: 0,
          sale_price: 0,
        }),
      });
    }
  };

  // Handle price change
  const handlePriceChange = (value: string) => {
    if (settings) {
      updateSettings({
        ...settings,
        price: parseFloat(value) || 0,
      });
    }
  };

  // Handle sale price change
  const handleSalePriceChange = (value: string) => {
    if (settings) {
      updateSettings({
        ...settings,
        sale_price: parseFloat(value) || 0,
      });
    }
  };

  return (
    <PluginDocumentSettingPanel
      name="course-pricing-settings"
      title={__("Pricing Model", "tutorpress")}
      className="tutorpress-course-pricing-panel"
    >
      {error && (
        <PanelRow>
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        </PanelRow>
      )}

      {/* Pricing Model Selection */}
      <PanelRow>
        <RadioControl
          label={__("Pricing Type", "tutorpress")}
          help={__("Choose whether this course is free or paid.", "tutorpress")}
          selected={settings?.pricing_model || "free"}
          options={[
            {
              label: __("Free", "tutorpress"),
              value: "free",
            },
            {
              label: __("Paid", "tutorpress"),
              value: "paid",
            },
          ]}
          onChange={handlePricingModelChange}
        />
      </PanelRow>

      {/* Price Fields - Only show when "Paid" is selected */}
      {settings?.pricing_model === "paid" && (
        <div className="price-fields">
          <PanelRow>
            <div className="price-field">
              <TextControl
                label={__("Regular Price", "tutorpress")}
                help={__("Enter the regular price for this course.", "tutorpress")}
                type="number"
                min="0"
                step="0.01"
                value={settings?.price?.toString() || "0"}
                onChange={handlePriceChange}
              />
            </div>
          </PanelRow>

          <PanelRow>
            <div className="price-field">
              <TextControl
                label={__("Sale Price", "tutorpress")}
                help={__("Enter the sale price (optional). Leave empty for no sale.", "tutorpress")}
                type="number"
                min="0"
                step="0.01"
                value={settings?.sale_price?.toString() || "0"}
                onChange={handleSalePriceChange}
              />
            </div>
          </PanelRow>
        </div>
      )}

      {/* Debug information - will be removed in production */}
      {process.env.NODE_ENV === "development" && (
        <PanelRow>
          <div style={{ width: "100%" }}>
            <details style={{ fontSize: "12px", color: "#666" }}>
              <summary>Debug: Current Pricing Settings</summary>
              <pre style={{ margin: "8px 0", fontSize: "11px" }}>
                {JSON.stringify(
                  {
                    pricing_model: settings?.pricing_model,
                    is_free: settings?.is_free,
                    price: settings?.price,
                    sale_price: settings?.sale_price,
                    subscription_enabled: settings?.subscription_enabled,
                  },
                  null,
                  2
                )}
              </pre>
            </details>
          </div>
        </PanelRow>
      )}
    </PluginDocumentSettingPanel>
  );
};

export default CoursePricingPanel;
