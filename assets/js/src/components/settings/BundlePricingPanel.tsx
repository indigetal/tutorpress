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
import { useSelect } from "@wordpress/data";
import { PanelRow, Notice, Spinner } from "@wordpress/components";

const BundlePricingPanel: React.FC = () => {
  // Get post type and ID from Gutenberg store
  const { postType, postId } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      postId: select("core/editor").getCurrentPostId(),
    }),
    []
  );

  // Only show for course-bundle post type
  if (postType !== "course-bundle") {
    return null;
  }

  return (
    <PluginDocumentSettingPanel
      name="bundle-pricing-settings"
      title={__("Bundle Pricing", "tutorpress")}
      className="tutorpress-bundle-pricing-panel"
    >
      <PanelRow>
        <div style={{ width: "100%", textAlign: "center", padding: "20px 0" }}>
          <p>{__("Bundle pricing settings will be implemented in the next steps.", "tutorpress")}</p>
        </div>
      </PanelRow>
    </PluginDocumentSettingPanel>
  );
};

export default BundlePricingPanel;
