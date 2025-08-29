/**
 * Bundle Pricing Panel Component
 *
 * Modern entity-based bundle pricing panel using useEntityProp pattern.
 * Follows Course Pricing Model architecture for simplified data flow.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React, { useEffect, useState } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, TextControl, SelectControl, RadioControl, Button } from "@wordpress/components";
import { plus, edit } from "@wordpress/icons";
import { store as noticesStore } from "@wordpress/notices";

// Import bundle types
import type { BundleRibbonType } from "../../types/bundle";
// Import subscription types
import type { SubscriptionPlan } from "../../types/subscriptions";
// Import the shared bundle meta hook
import { useBundleMeta } from "../../hooks/common";
// Import addon checker for subscription functionality
// Note: subscription status now comes from backend data
// Import subscription modal
import { SubscriptionModal } from "../modals/subscription/SubscriptionModal";
import PromoPanel from "../common/PromoPanel";

/**
 * Extract numeric price from course price string
 * Handles formats like "$99.99", "Free", "$0", and HTML formatted prices
 */
const extractNumericPrice = (priceString: string): number => {
  if (!priceString || priceString.toLowerCase() === "free") {
    return 0;
  }

  // Handle HTML formatted prices (from bundle courses API)
  if (priceString.includes("<span")) {
    // Extract regular price from HTML (always use regular price for bundle calculation)
    const regularPriceMatch = priceString.match(/tutor-course-price-regular[^>]*>\$([\d.]+)/);
    if (regularPriceMatch) {
      return parseFloat(regularPriceMatch[1]);
    }
  }

  // Extract numeric value from strings like "$99.99"
  const match = priceString.match(/[\d.]+/);
  return match ? parseFloat(match[0]) : 0;
};

/**
 * Calculate regular price from bundle courses
 */
const calculateBundleRegularPrice = async (bundleId: number): Promise<number> => {
  try {
    // Get bundle courses from the existing API endpoint using wp.apiFetch (multisite compatible)
    const data = await window.wp.apiFetch({
      path: `/tutorpress/v1/bundles/${bundleId}/courses`,
    });

    if (!data.success || !data.data) return 0;

    // Sum up all course prices
    const totalPrice = data.data.reduce((sum: number, course: any) => {
      const coursePrice = extractNumericPrice(course.price || "");
      return sum + coursePrice;
    }, 0);

    return totalPrice;
  } catch (error) {
    console.error("Error calculating bundle regular price:", error);
    return 0;
  }
};

const BundlePricingPanel: React.FC = () => {
  // Local state for calculated regular price
  const [calculatedRegularPrice, setCalculatedRegularPrice] = useState<number>(0);
  const [isCalculating, setIsCalculating] = useState<boolean>(false);

  // Modal state for subscription functionality
  const [isSubscriptionModalOpen, setSubscriptionModalOpen] = useState(false);
  const [editingPlan, setEditingPlan] = useState<SubscriptionPlan | null>(null);
  const [shouldShowForm, setShouldShowForm] = useState(false);

  // Modern entity-based approach using useBundleMeta hook
  const { meta, safeSet, ready } = useBundleMeta();
  const { postType, postId, subscriptionPlans, subscriptionPlansLoading } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      postId: select("core/editor").getCurrentPostId(),
      subscriptionPlans: select("tutorpress/subscriptions").getSubscriptionPlans() || [],
      subscriptionPlansLoading: select("tutorpress/subscriptions").getSubscriptionPlansLoading(),
    }),
    []
  );

  // Get dispatch actions
  const { editPost } = useDispatch("core/editor");
  const { createNotice } = useDispatch(noticesStore);
  const { getSubscriptionPlans } = useDispatch("tutorpress/subscriptions");

  // Extract pricing data from meta fields (entity-based)
  const pricingData = ready
    ? {
        regular_price: (meta?.tutor_course_price as number) || 0,
        sale_price: (meta?.tutor_course_sale_price as number) || 0,
        price_type: (meta?._tutor_course_price_type as string) || "free",
        ribbon_type: (meta?.tutor_bundle_ribbon_type as BundleRibbonType) || "none",
        selling_option: (meta?.tutor_course_selling_option as string) || "one_time",
        product_id: (meta?._tutor_course_product_id as number) || 0,
      }
    : null;

  // Get bundle course IDs from meta
  const bundleCourseIds = (meta?.["bundle-course-ids"] as string) || "";

  // Calculate regular price when bundle courses change (entity-based)
  useEffect(() => {
    const updateRegularPrice = async () => {
      if (!postId || !bundleCourseIds || !ready) {
        setCalculatedRegularPrice(0);
        return;
      }

      setIsCalculating(true);
      try {
        // Calculate regular price from bundle courses
        const regularPrice = await calculateBundleRegularPrice(postId);
        setCalculatedRegularPrice(regularPrice);

        // Update the pricing data with the calculated regular price using entity approach
        if (pricingData && regularPrice !== pricingData.regular_price) {
          // Check if sale price needs adjustment
          let adjustedSalePrice = pricingData.sale_price;
          if (pricingData.sale_price > regularPrice) {
            adjustedSalePrice = regularPrice;
            // Show notice about auto-adjustment
            createNotice(
              "warning",
              __("Bundle price has been automatically adjusted to match the new total value.", "tutorpress"),
              { type: "snackbar" }
            );
          }

          // Entity-based update (following Course Pricing pattern)
          const metaUpdates = {
            tutor_course_price: regularPrice,
            tutor_course_sale_price: adjustedSalePrice,
          };
          safeSet(metaUpdates);
          editPost({ meta: { ...meta, ...metaUpdates } });
        }
      } catch (error) {
        console.error("Error updating regular price:", error);
        setCalculatedRegularPrice(0);
      } finally {
        setIsCalculating(false);
      }
    };

    // Only calculate if we have pricing data and bundle courses have changed
    if (pricingData && bundleCourseIds && ready) {
      updateRegularPrice();
    }
  }, [postId, bundleCourseIds, ready]); // Removed pricingData to prevent infinite loops

  // Fetch subscription plans when component mounts and bundle ID is available
  useEffect(() => {
    if (postType === "course-bundle" && postId && (window.tutorpressAddons?.subscription ?? false)) {
      getSubscriptionPlans();
    }
  }, [postType, postId, getSubscriptionPlans]);

  // Listen for course changes via custom events (entity-based)
  useEffect(() => {
    const handleCourseChange = async (event: Event) => {
      const customEvent = event as CustomEvent;
      // Only respond to events for this bundle
      if (customEvent.detail?.bundleId !== postId) return;

      if (!postId || !pricingData || !ready) return;

      setIsCalculating(true);
      try {
        const regularPrice = await calculateBundleRegularPrice(postId);
        setCalculatedRegularPrice(regularPrice);

        if (regularPrice !== pricingData.regular_price) {
          // Check if sale price needs adjustment
          let adjustedSalePrice = pricingData.sale_price;
          if (pricingData.sale_price > regularPrice) {
            adjustedSalePrice = regularPrice;
            // Show notice about auto-adjustment
            createNotice(
              "warning",
              __("Bundle price has been automatically adjusted to match the new total value.", "tutorpress"),
              { type: "snackbar" }
            );
          }

          // Entity-based update (following Course Pricing pattern)
          const metaUpdates = {
            tutor_course_price: regularPrice,
            tutor_course_sale_price: adjustedSalePrice,
          };
          safeSet(metaUpdates);
          editPost({ meta: { ...meta, ...metaUpdates } });
        }
      } catch (error) {
        console.error("Error updating regular price:", error);
      } finally {
        setIsCalculating(false);
      }
    };

    // Listen for course changes from the Courses Metabox
    window.addEventListener("tutorpress-bundle-courses-updated", handleCourseChange);

    return () => {
      window.removeEventListener("tutorpress-bundle-courses-updated", handleCourseChange);
    };
  }, [postId, pricingData, ready]);

  // Handle sale price change (entity-based following Course Pricing pattern)
  const handleSalePriceChange = (value: string) => {
    if (!pricingData || !ready) return;

    const bundlePrice = parseFloat(value) || 0;
    const totalValue = calculatedRegularPrice || pricingData?.regular_price || 0;

    // Validate that bundle price cannot exceed total value
    if (bundlePrice > totalValue) {
      // Show error notice
      createNotice("error", __("Bundle price cannot exceed the total value of the bundled courses.", "tutorpress"), {
        type: "snackbar",
      });
      return; // Don't update if validation fails
    }

    // Entity-based update (following Course Pricing pattern)
    const metaUpdates = { tutor_course_sale_price: bundlePrice };
    safeSet(metaUpdates);
    editPost({ meta: { ...meta, ...metaUpdates } });
  };

  // Handle purchase option change (selling_option)
  const handlePurchaseOptionChange = (value: string) => {
    if (!pricingData || !ready) return;

    // Entity-based update (following Course Pricing pattern)
    const metaUpdates = { tutor_course_selling_option: value };
    safeSet(metaUpdates);
    editPost({ meta: { ...meta, ...metaUpdates } });
  };

  // Handle ribbon type change (entity-based following Course Pricing pattern)
  const handleRibbonTypeChange = (value: string) => {
    if (!pricingData || !ready) return;

    // Entity-based update (following Course Pricing pattern)
    const metaUpdates = { tutor_bundle_ribbon_type: value as BundleRibbonType };
    safeSet(metaUpdates);
    editPost({ meta: { ...meta, ...metaUpdates } });
  };

  // Subscription modal handlers
  const handleSubscriptionModalClose = () => {
    setSubscriptionModalOpen(false);
    setEditingPlan(null);
    setShouldShowForm(false);
  };

  const handleAddSubscription = () => {
    setEditingPlan(null);
    setShouldShowForm(true);
    setSubscriptionModalOpen(true);
  };

  const handleEditPlan = (plan: SubscriptionPlan) => {
    setEditingPlan(plan);
    setShouldShowForm(false);
    setSubscriptionModalOpen(true);
  };

  // Bundle-specific purchase options (only 3 options, unlike Course Pricing)
  const getPurchaseOptions = () => [
    { label: __("One-time purchase only", "tutorpress"), value: "one_time" },
    { label: __("Subscription only", "tutorpress"), value: "subscription" },
    { label: __("Subscription and one-time purchase", "tutorpress"), value: "both" },
  ];

  // Conditional display logic for Bundle pricing
  const shouldShowPurchaseOptions = window.tutorpressAddons?.subscription ?? false;

  // Bundle-specific conditional display logic based on purchase option selection
  const shouldShowPriceFields = () => {
    // Always show if subscriptions are disabled
    if (!(window.tutorpressAddons?.subscription ?? false)) return true;

    // If subscriptions are enabled, show price fields for one_time and both
    const sellingOption = pricingData?.selling_option || "one_time";
    return ["one_time", "both"].includes(sellingOption);
  };

  const shouldShowSubscriptionSection = () => {
    // Only show if subscriptions are enabled
    if (!(window.tutorpressAddons?.subscription ?? false)) return false;

    // Show subscriptions for subscription and both
    const sellingOption = pricingData?.selling_option || "one_time";
    return ["subscription", "both"].includes(sellingOption);
  };

  // Ribbon type options
  const ribbonOptions = [
    { label: __("Show Discount % Off", "tutorpress"), value: "in_percentage" },
    { label: __("Show Discount Amount ($)", "tutorpress"), value: "in_amount" },
    { label: __("Show None", "tutorpress"), value: "none" },
  ];

  // Panel loading state - includes subscription plans loading when applicable
  const panelLoading =
    !ready ||
    ((window.tutorpressAddons?.subscription ?? false) && shouldShowSubscriptionSection() && subscriptionPlansLoading);

  // Don't render if not on a course-bundle post
  if (postType !== "course-bundle") {
    return null;
  }

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name="bundle-pricing"
        title={__("Bundle Pricing", "tutorpress")}
        className="tutorpress-bundle-pricing-panel"
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  return (
    <PluginDocumentSettingPanel
      name="bundle-pricing"
      title={__("Bundle Pricing", "tutorpress")}
      className="tutorpress-bundle-pricing-panel"
    >
      {/* Render the SubscriptionModal at the root of the panel */}
      <SubscriptionModal
        isOpen={isSubscriptionModalOpen}
        onClose={handleSubscriptionModalClose}
        courseId={postId}
        postType={postType}
        initialPlan={editingPlan}
        shouldShowForm={shouldShowForm}
      />

      {panelLoading && (
        <PanelRow>
          <Spinner />
          <span>{__("Loading bundle pricing...", "tutorpress")}</span>
        </PanelRow>
      )}

      {ready && pricingData && (
        <>
          {/* Purchase Options - Show only when subscriptions addon is enabled */}
          {shouldShowPurchaseOptions && (
            <PanelRow>
              <SelectControl
                label={__("Purchase Options", "tutorpress")}
                help={__("Choose how this bundle can be purchased.", "tutorpress")}
                value={pricingData.selling_option || "one_time"}
                options={getPurchaseOptions()}
                onChange={handlePurchaseOptionChange}
              />
            </PanelRow>
          )}

          {/* Price Fields Section - Conditional based on purchase option */}
          {shouldShowPriceFields() && (
            <>
              {/* Total Value of Bundled Courses Display (Read-Only) */}
              <PanelRow>
                <div className="price-display">
                  <label className="components-base-control__label">
                    {__("Total Value of Bundled Courses", "tutorpress")}
                  </label>
                  <div className="price-value">
                    {isCalculating ? (
                      <Spinner />
                    ) : (
                      `$${(calculatedRegularPrice || pricingData?.regular_price || 0)?.toFixed(2) || "0.00"}`
                    )}
                  </div>
                  <p className="components-base-control__help">{__("Calculated from bundle courses", "tutorpress")}</p>
                </div>
              </PanelRow>

              {/* Bundle Price Input */}
              <PanelRow>
                <TextControl
                  label={__("Bundle Price", "tutorpress")}
                  value={pricingData.sale_price?.toString() || "0"}
                  onChange={handleSalePriceChange}
                  type="number"
                  min="0"
                  max={(calculatedRegularPrice || pricingData?.regular_price || 0)?.toString()}
                  step="0.01"
                  help={__("Enter the bundle price (cannot exceed total value)", "tutorpress")}
                />
              </PanelRow>
            </>
          )}

          {/* Subscription Section - Conditional based on purchase option */}
          {shouldShowSubscriptionSection() && (
            <PanelRow>
              <div className="subscription-section">
                {/* Existing Subscription Plans List */}
                {subscriptionPlans.length > 0 && (
                  <div className="tutorpress-saved-files-list">
                    <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
                      {__("Subscription Plans:", "tutorpress")}
                    </div>
                    {subscriptionPlans.map((plan: SubscriptionPlan) => (
                      <div key={plan.id} className="tutorpress-saved-file-item">
                        <div className="file-info">
                          <span className="file-name">{plan.plan_name}</span>
                          <span className="file-meta">
                            ${plan.regular_price} / {plan.recurring_value} {plan.recurring_interval}
                            {plan.recurring_limit > 0 && ` (${plan.recurring_limit} cycles)`}
                          </span>
                        </div>
                        <div className="file-actions">
                          <Button
                            variant="tertiary"
                            icon={edit}
                            onClick={() => handleEditPlan(plan)}
                            className="edit-button"
                            aria-label={__("Edit subscription plan", "tutorpress")}
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Add Subscription Button */}
                <div style={{ marginTop: subscriptionPlans.length > 0 ? "12px" : "0" }}>
                  <Button icon={plus} variant="secondary" onClick={handleAddSubscription}>
                    {__("Add Subscription", "tutorpress")}
                  </Button>
                </div>
              </div>
            </PanelRow>
          )}

          {/* Ribbon Type Selection - Always shown */}
          <PanelRow>
            <SelectControl
              label={__("Ribbon Display", "tutorpress")}
              value={pricingData.ribbon_type || "none"}
              options={ribbonOptions}
              onChange={handleRibbonTypeChange}
              help={__("Choose how to display the discount ribbon", "tutorpress")}
            />
          </PanelRow>
        </>
      )}
    </PluginDocumentSettingPanel>
  );
};

export default BundlePricingPanel;
