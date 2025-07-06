import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner } from "@wordpress/components";

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

      {/* Basic panel structure - UI components will be added in Phase 2 */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <p
            style={{
              fontSize: "14px",
              color: "#757575",
              margin: "0",
              fontStyle: "italic",
            }}
          >
            {__("Pricing configuration will be available in the next phase.", "tutorpress")}
          </p>
        </div>
      </PanelRow>

      {/* Debug information - will be removed in production */}
      {process.env.NODE_ENV === "development" && (
        <PanelRow>
          <div style={{ width: "100%" }}>
            <details style={{ fontSize: "12px", color: "#666" }}>
              <summary>Debug: Current Pricing Settings</summary>
              <pre style={{ margin: "8px 0", fontSize: "11px" }}>
                {JSON.stringify(
                  {
                    pricing_model: settings.pricing_model,
                    price: settings.price,
                    sale_price: settings.sale_price,
                    subscription_enabled: settings.subscription_enabled,
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
