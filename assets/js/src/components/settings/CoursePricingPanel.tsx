import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, RadioControl, TextControl, Button, SelectControl } from "@wordpress/components";
import { plus } from "@wordpress/icons";

// Import course settings types
import type { CourseSettings } from "../../types/courses";
import { isMonetizationEnabled, isSubscriptionEnabled } from "../../utils/addonChecker";

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
        // Set defaults when switching to paid (matching Tutor LMS defaults)
        ...(value === "paid" && {
          price: 10,
          sale_price: 0,
        }),
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

  // Handle purchase option change
  const handlePurchaseOptionChange = (value: string) => {
    if (settings) {
      updateSettings({
        ...settings,
        selling_option: value,
        // Let the backend derive subscription_enabled from selling_option
        subscription_enabled: value === "subscription" || value === "both" || value === "all",
      });
    }
  };

  // Check if purchase options should be shown
  const shouldShowPurchaseOptions =
    settings?.pricing_model === "paid" && isMonetizationEnabled() && isSubscriptionEnabled();

  // Get purchase options based on available payment engines
  const getPurchaseOptions = () => {
    const options = [
      {
        label: __("One-time purchase only", "tutorpress"),
        value: "one_time",
      },
      {
        label: __("Subscription only", "tutorpress"),
        value: "subscription",
      },
      {
        label: __("Subscription & one-time purchase", "tutorpress"),
        value: "both",
      },
      {
        label: __("Membership only", "tutorpress"),
        value: "membership",
      },
      {
        label: __("All", "tutorpress"),
        value: "all",
      },
    ];
    return options;
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
            // Only show "Paid" option if monetization is enabled
            ...(isMonetizationEnabled()
              ? [
                  {
                    label: __("Paid", "tutorpress"),
                    value: "paid",
                  },
                ]
              : []),
          ]}
          onChange={handlePricingModelChange}
        />
      </PanelRow>

      {/* Purchase Options Dropdown - Only show when conditions are met */}
      {shouldShowPurchaseOptions && (
        <PanelRow>
          <SelectControl
            label={__("Purchase Options", "tutorpress")}
            help={__("Choose how this course can be purchased.", "tutorpress")}
            value={settings?.selling_option || "one_time"}
            options={getPurchaseOptions()}
            onChange={handlePurchaseOptionChange}
          />
        </PanelRow>
      )}

      {/* Price Fields - Show based on purchase option selection */}
      {settings?.pricing_model === "paid" &&
        isMonetizationEnabled() &&
        (settings?.selling_option === "one_time" ||
          settings?.selling_option === "both" ||
          settings?.selling_option === "all") && (
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

      {/* Subscription Button - Show based on purchase option selection */}
      {settings?.pricing_model === "paid" &&
        isMonetizationEnabled() &&
        isSubscriptionEnabled() &&
        (settings?.selling_option === "subscription" ||
          settings?.selling_option === "both" ||
          settings?.selling_option === "all") && (
          <PanelRow>
            <div className="subscription-section">
              <Button
                icon={plus}
                variant="secondary"
                onClick={() => {
                  // TODO: Open subscription modal in Step 1.4
                  console.log("TutorPress: Add Subscription button clicked");
                }}
              >
                {__("Add Subscription", "tutorpress")}
              </Button>
              <p className="description">{__("Create subscription plans for this course.", "tutorpress")}</p>
            </div>
          </PanelRow>
        )}
    </PluginDocumentSettingPanel>
  );
};

export default CoursePricingPanel;
