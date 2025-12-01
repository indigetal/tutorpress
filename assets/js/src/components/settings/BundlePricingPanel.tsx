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
import { plus, pencil } from "@wordpress/icons";
import { store as noticesStore } from "@wordpress/notices";

// Import bundle types
import type { BundleRibbonType } from "../../types/bundle";
// Import subscription types
import type { SubscriptionPlan } from "../../types/subscriptions";
// Import the shared bundle meta hook
import { useBundleMeta } from "../../hooks/common";
// Import addon checker for PMPro and subscription functionality
import {
  isMonetizationEnabled,
  getPaymentEngine,
  isPmproMonetization,
  isPmproAvailable,
} from "../../utils/addonChecker";
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
  const { postType, postId, subscriptionPlans, subscriptionPlansLoading, hasFullSiteLevels, membershipOnlyMode } =
    useSelect(
      (select: any) => ({
        postType: select("core/editor").getCurrentPostType(),
        postId: select("core/editor").getCurrentPostId(),
        subscriptionPlans: select("tutorpress/subscriptions").getSubscriptionPlans() || [],
        subscriptionPlansLoading: select("tutorpress/subscriptions").getSubscriptionPlansLoading(),
        hasFullSiteLevels: select("tutorpress/subscriptions").getHasFullSiteLevels(),
        membershipOnlyMode: select("tutorpress/subscriptions").getMembershipOnlyMode(),
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

  // Filter plans to only show recurring plans when selling_option is 'subscription'
  // This prevents one-time levels from appearing in the Subscription Plans section
  const displayedPlans = React.useMemo(() => {
    if (!pricingData?.selling_option) return subscriptionPlans;
    
    // When selling_option is 'subscription', only show recurring plans
    if (pricingData.selling_option === 'subscription') {
      return subscriptionPlans.filter((plan: SubscriptionPlan) => plan.payment_type === 'recurring');
    }
    
    // For other options, show all plans
    return subscriptionPlans;
  }, [subscriptionPlans, pricingData?.selling_option]);

  // Note: bundle-course-ids is NOT exposed to REST API (show_in_rest: false)
  // to prevent Gutenberg auto-save conflicts. We'll fetch it via API instead.

  // Calculate regular price when bundle courses change (entity-based)
  // Also recalculate when payment engine changes (for PMPro integration)
  useEffect(() => {
    const updateRegularPrice = async () => {
      if (!postId || !ready) {
        setCalculatedRegularPrice(0);
        return;
      }

      setIsCalculating(true);
      try {
        // Calculate regular price from bundle courses
        // This will use PMPro pricing if PMPro is the active monetization engine
        const regularPrice = await calculateBundleRegularPrice(postId);
        setCalculatedRegularPrice(regularPrice);

        // For PMPro monetization, we DON'T auto-update the bundle meta fields
        // because the bundle price is determined by the PMPro membership levels,
        // not by the sum of course prices. The calculated value is for display only.
        const isPmpro = isPmproMonetization();

        if (!isPmpro && pricingData && regularPrice !== pricingData.regular_price) {
          // Native Tutor LMS e-commerce: Auto-update bundle meta fields
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

    // Always calculate price when ready (bundle-course-ids not in meta)
    if (ready) {
      updateRegularPrice();
    }
  }, [postId, ready]); // bundleCourseIds not available in meta (show_in_rest: false)

  // Fetch subscription plans when component mounts and bundle ID is available
  // Supports both Tutor Pro subscription addon and PMPro (following Course pattern)
  useEffect(() => {
    const shouldFetchSubscriptionPlans = (window.tutorpressAddons?.subscription ?? false) || isPmproMonetization();
    if (postType === "course-bundle" && postId && shouldFetchSubscriptionPlans) {
      getSubscriptionPlans();
    }
  }, [postType, postId, getSubscriptionPlans, pricingData?.selling_option]); // Add sellingOption dependency

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

  // Handle bundle price change (entity-based following Course Pricing pattern)
  const handleBundlePriceChange = (value: string) => {
    if (!pricingData || !ready) return;

    const bundlePrice = parseFloat(value) || 0;
    const totalValue = calculatedRegularPrice || 0;

    // Validate that bundle price cannot exceed total value
    if (totalValue > 0 && bundlePrice > totalValue) {
      // Show error notice
      createNotice("error", __("Bundle price cannot exceed the total value of the bundled courses.", "tutorpress"), {
        type: "snackbar",
      });
      return; // Don't update if validation fails
    }

    // Entity-based update (following Course Pricing pattern)
    const metaUpdates = { tutor_course_price: bundlePrice }; // This is the bundle price (regular price)
    safeSet(metaUpdates);
    editPost({ meta: { ...meta, ...metaUpdates } });
  };

  // Handle purchase option change (selling_option)
  const handlePurchaseOptionChange = (value: string) => {
    if (!pricingData || !ready) return;

    // PMPro-specific warnings when switching between one-time and subscription
    if (isPmproMonetization()) {
      const current = pricingData.selling_option;
      if (value === "subscription" && current !== "subscription") {
        const ok = window.confirm(
          __(
            "Switching to Subscription will remove any existing one-time purchase setting on save. Continue?",
            "tutorpress"
          )
        );
        if (!ok) {
          return;
        }
      } else if (value === "one_time" && current !== "one_time") {
        const ok = window.confirm(
          __(
            "Switching to One-time purchase will remove any existing subscription plans on save. Continue?",
            "tutorpress"
          )
        );
        if (!ok) {
          return;
        }
      } else if (value === "membership" && current !== "membership") {
        const ok = window.confirm(
          __(
            "Switching to Membership only will remove any existing bundle-specific purchase options on save. Continue?",
            "tutorpress"
          )
        );
        if (!ok) {
          return;
        }
      }
    }

    // Entity-based update (following Course Pricing pattern)
    const metaUpdates: any = { tutor_course_selling_option: value };
    
    // Auto-update price_type to 'paid' when selecting a paid selling option
    // This prevents confusion where selling_option='subscription' but price_type='free'
    const isPaidOption = ['subscription', 'one_time', 'both', 'all'].includes(value);
    if (isPaidOption && pricingData.price_type === 'free') {
      metaUpdates._tutor_course_price_type = 'paid';
    }
    
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
    // Note: No need to refresh here - store updates automatically via UPDATE_SUBSCRIPTION_PLAN_SUCCESS
    // Sale price updates correctly without refresh, so renewal price should too via the same mechanism
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

  // Conditional display logic for Bundle pricing (following Course pattern)
  const shouldShowPurchaseOptions =
    isMonetizationEnabled() &&
    !membershipOnlyMode &&
    ((getPaymentEngine() === "tutor_pro" && (window.tutorpressAddons?.subscription ?? false)) ||
      (getPaymentEngine() === "pmpro" && isPmproAvailable()));

  // Bundle-specific conditional display logic based on purchase option selection
  const shouldShowPriceFields = () => {
    // Don't show price fields when global membership-only mode is enabled
    if (membershipOnlyMode) return false;

    // Always show if neither subscription addon nor PMPro is available
    if (!(window.tutorpressAddons?.subscription ?? false) && getPaymentEngine() !== "pmpro") {
      return true;
    }

    // If subscription addon is enabled OR PMPro is selected, show based on selling option
    const sellingOption = pricingData?.selling_option || "one_time";
    return ["one_time", "both", "all"].includes(sellingOption);
  };

  const shouldShowSubscriptionSection = () => {
    // Only show if subscription addon is enabled OR PMPro is selected
    if (!(window.tutorpressAddons?.subscription ?? false) && getPaymentEngine() !== "pmpro") {
      return false;
    }

    // Show subscriptions for subscription, both, and all
    const sellingOption = pricingData?.selling_option || "one_time";
    return ["subscription", "both", "all"].includes(sellingOption);
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
    (((window.tutorpressAddons?.subscription ?? false) || getPaymentEngine() === "pmpro") &&
      shouldShowSubscriptionSection() &&
      subscriptionPlansLoading);

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
                    {isCalculating ? <Spinner /> : `$${(calculatedRegularPrice || 0)?.toFixed(2) || "0.00"}`}
                  </div>
                  <p className="components-base-control__help">{__("Calculated from bundle courses", "tutorpress")}</p>
                </div>
              </PanelRow>

              {/* Bundle Price Input */}
              <PanelRow>
                <TextControl
                  label={__("Bundle Price", "tutorpress")}
                  value={pricingData.regular_price?.toString() || "0"}
                  onChange={handleBundlePriceChange}
                  type="number"
                  min="0"
                  max={(calculatedRegularPrice || 0)?.toString()}
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
                {displayedPlans.length > 0 && (
                  <div className="tutorpress-saved-files-list">
                    <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
                      {__("Subscription Plans:", "tutorpress")}
                    </div>
                    {displayedPlans.map((plan: SubscriptionPlan) => (
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
                            icon={pencil}
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
                <div style={{ marginTop: displayedPlans.length > 0 ? "12px" : "0" }}>
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
