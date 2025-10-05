import React, { useState, useEffect, useMemo, useRef } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch, select as wpSelect } from "@wordpress/data";
import { PanelRow, Notice, Spinner, RadioControl, TextControl, Button, SelectControl } from "@wordpress/components";
import { plus, edit } from "@wordpress/icons";
import { useEntityProp } from "@wordpress/core-data";

// Import course settings types
import type { CourseSettings, WcProduct } from "../../types/courses";
import type { SubscriptionPlan } from "../../types/subscriptions";
import {
  isMonetizationEnabled,
  isWooCommerceMonetization,
  isEddMonetization,
  getPaymentEngine,
  isPmproMonetization,
  isPmproAvailable,
} from "../../utils/addonChecker";
import { SubscriptionModal } from "../modals/subscription/SubscriptionModal";
import { useCourseSettings } from "../../hooks/common";
import PromoPanel from "../common/PromoPanel";

const CoursePricingPanel: React.FC = () => {
  // Get settings from our store and Gutenberg store
  const {
    postType,
    postId,
    subscriptionPlans,
    subscriptionPlansLoading,
    woocommerceProducts,
    woocommerceLoading,
    eddProducts,
    eddLoading,
  } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      postId: select("core/editor").getCurrentPostId(),
      subscriptionPlans: select("tutorpress/subscriptions").getSubscriptionPlans() || [],
      subscriptionPlansLoading: select("tutorpress/subscriptions").getSubscriptionPlansLoading(),
      woocommerceProducts: select("tutorpress/commerce").getWooProducts(),
      woocommerceLoading: select("tutorpress/commerce").getWooLoading(),
      eddProducts: select("tutorpress/commerce").getEddProducts(),
      eddLoading: select("tutorpress/commerce").getEddLoading(),
    }),
    []
  );

  // Get dispatch actions
  const { getSubscriptionPlans } = useDispatch("tutorpress/subscriptions");
  const { fetchWooProducts, fetchWooProductDetails, fetchEddProducts, fetchEddProductDetails } =
    useDispatch("tutorpress/commerce");
  const editorDispatch = useDispatch("core/editor");

  // Entity-prop for course settings (Step 3b: product ids only)
  const [courseSettings, setCourseSettings] = useEntityProp("postType", "courses", "course_settings");

  // Get shared course settings to access is_public_course
  const { courseSettings: sharedCourseSettings } = useCourseSettings();

  // Legacy mirror removed; entity is sole write target

  // Modal state
  const [isSubscriptionModalOpen, setSubscriptionModalOpen] = useState(false);
  const [editingPlan, setEditingPlan] = useState<SubscriptionPlan | null>(null);
  const [shouldShowForm, setShouldShowForm] = useState(false);

  // Error states
  const [woocommerceError, setWooCommerceError] = useState<string | null>(null);
  const [eddError, setEddError] = useState<string | null>(null);

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

  // Fetch subscription plans when component mounts and course ID is available
  useEffect(() => {
    // Also fetch when Paid Memberships Pro is the active engine so PMPro-backed plans appear
    const shouldFetchSubscriptionPlans = (window.tutorpressAddons?.subscription ?? false) || isPmproMonetization();
    if (postType === "courses" && postId && shouldFetchSubscriptionPlans) {
      getSubscriptionPlans();
    }
  }, [postType, postId, getSubscriptionPlans]);

  // Fetch WooCommerce products when component mounts and WooCommerce is active
  useEffect(() => {
    if (postType === "courses" && postId && isWooCommerceMonetization()) {
      fetchWooProducts({
        course_id: postId,
        per_page: 50,
        exclude_linked_products: false,
      });
    }
  }, [postType, postId, fetchWooProducts]);

  // Fetch EDD products when component mounts and EDD monetization is active
  useEffect(() => {
    if (postType === "courses" && postId && isEddMonetization()) {
      fetchEddProducts({
        course_id: postId,
        per_page: 50,
        exclude_linked_products: false,
      });
    }
  }, [postType, postId, fetchEddProducts]);

  // Remove legacy → entity seeding; entity is the source of truth now

  // Track last manual price edit to guard against stale detail overwrites (must be before any early returns)
  const lastManualPriceEditRef = useRef<number>(0);

  // Derive entity values and keep an immediate UI shadow for responsiveness (hooks must be before any early return)
  const pricingModelEntity = ((courseSettings as any)?.pricing_model || "free") as string;
  const sellingOption = ((courseSettings as any)?.selling_option || "one_time") as string;
  const [uiPricingModel, setUiPricingModel] = useState<string>(pricingModelEntity);
  useEffect(() => {
    setUiPricingModel(pricingModelEntity);
  }, [pricingModelEntity]);

  // Auto-reset to "free" when public course is enabled and course is currently paid
  useEffect(() => {
    const isPublicCourse = sharedCourseSettings?.is_public_course ?? false;
    if (isPublicCourse && pricingModelEntity === "paid") {
      // Auto-reset to free when public course is enabled
      const next = {
        ...(courseSettings || {}),
        pricing_model: "free",
        is_free: true,
        price: 0,
        sale_price: null,
      } as any;
      setCourseSettings(next);
      editorDispatch.editPost({ course_settings: next });
    }
  }, [sharedCourseSettings?.is_public_course, pricingModelEntity, courseSettings, setCourseSettings, editorDispatch]);

  // Validate EDD product selection when products are loaded
  useEffect(() => {
    if (isEddMonetization() && (courseSettings as any)?.edd_product_id && eddProducts && !eddLoading) {
      const selectedProductId = (courseSettings as any).edd_product_id;
      const productExists = eddProducts.some((product: any) => product.ID === selectedProductId);
      // If product not found in the dropdown, quietly allow synthetic option to represent current linkage
    }
  }, [(courseSettings as any)?.edd_product_id, eddProducts, eddLoading]);

  // Validate WooCommerce product selection when products are loaded
  useEffect(() => {
    if (
      isWooCommerceMonetization() &&
      (courseSettings as any)?.woocommerce_product_id &&
      woocommerceProducts &&
      !woocommerceLoading
    ) {
      const selectedProductId = (courseSettings as any).woocommerce_product_id;
      const productExists = woocommerceProducts.some((product: WcProduct) => product.ID === selectedProductId);
      // If product not found in the dropdown, quietly allow synthetic option to represent current linkage
    }
  }, [(courseSettings as any)?.woocommerce_product_id, woocommerceProducts, woocommerceLoading]);

  // Build options with synthetic entry for the active engine when needed
  const wooSelectedId = String((courseSettings as any)?.woocommerce_product_id || "");
  const wooOptions = useMemo(() => {
    const base = [
      { label: __("Select a product", "tutorpress"), value: "" },
      ...(woocommerceProducts || []).map((p: WcProduct) => ({ label: p.post_title, value: String(p.ID) })),
    ];
    if (
      isWooCommerceMonetization() &&
      wooSelectedId &&
      !(woocommerceProducts || []).some((p: WcProduct) => String(p.ID) === wooSelectedId)
    ) {
      base.splice(1, 0, { label: `#${wooSelectedId}`, value: wooSelectedId });
    }
    return base;
  }, [woocommerceProducts, wooSelectedId, isWooCommerceMonetization()]);

  const eddSelectedId = String((courseSettings as any)?.edd_product_id || "");
  const eddOptions = useMemo(() => {
    const base = [
      { label: __("Select a product", "tutorpress"), value: "" },
      ...(eddProducts || []).map((p: any) => ({ label: p.post_title, value: String(p.ID) })),
    ];
    if (isEddMonetization() && eddSelectedId && !(eddProducts || []).some((p: any) => String(p.ID) === eddSelectedId)) {
      base.splice(1, 0, { label: `#${eddSelectedId}`, value: eddSelectedId });
    }
    return base;
  }, [eddProducts, eddSelectedId, isEddMonetization()]);

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Show loading state while fetching relevant data
  const panelLoading =
    (isMonetizationEnabled() && isWooCommerceMonetization() && woocommerceLoading) ||
    (isMonetizationEnabled() && isEddMonetization() && eddLoading) ||
    ((window.tutorpressAddons?.subscription ?? false) && subscriptionPlansLoading);

  if (panelLoading) {
    return (
      <PluginDocumentSettingPanel
        name="course-pricing-settings"
        title={__("Pricing Model", "tutorpress")}
        className="tutorpress-course-pricing-panel"
      >
        {/* Always mount modal so it can open even during loading states */}
        <SubscriptionModal
          isOpen={isSubscriptionModalOpen}
          onClose={handleSubscriptionModalClose}
          courseId={postId}
          initialPlan={editingPlan}
          shouldShowForm={shouldShowForm}
        />
        <PanelRow>
          <div style={{ width: "100%", textAlign: "center", padding: "20px 0" }}>
            <Spinner />
          </div>
        </PanelRow>
      </PluginDocumentSettingPanel>
    );
  }

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name="course-pricing-settings"
        title={__("Pricing Model", "tutorpress")}
        className="tutorpress-course-pricing-panel"
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  // Handle pricing model change — 5a.1 entity-only writes for simple flags
  const handlePricingModelChange = (value: string) => {
    // Check if trying to select "paid" while public course is enabled
    const isPublicCourse = sharedCourseSettings?.is_public_course ?? false;
    if (value === "paid" && isPublicCourse) {
      // Don't allow paid courses to be public - this should be prevented by UI but adding safety
      return;
    }

    // PMPro-specific warning: switching to Free removes attached PMPro levels
    if (isPmproMonetization() && value === "free" && pricingModelEntity !== "free") {
      const confirmed = window.confirm(
        __(
          "Switching to Free will remove all existing price settings for this course. This action cannot be undone. Continue?",
          "tutorpress"
        )
      );
      if (!confirmed) {
        return;
      }
    }

    setUiPricingModel(value); // immediate UI update for responsiveness
    // Entity write + mark post dirty via editor
    const next = {
      ...(courseSettings || {}),
      pricing_model: value,
      is_free: value === "free",
      ...(value === "paid" ? { price: 10, sale_price: null } : { price: 0, sale_price: null }),
    } as any;
    setCourseSettings(next);
    editorDispatch.editPost({ course_settings: next });
  };

  // (moved above early returns)

  // Handle price change (normalize to 2 decimals)
  const handlePriceChange = (value: string) => {
    const raw = parseFloat(value);
    let price = isNaN(raw) || raw < 0 ? 0 : raw;
    price = Math.round(price * 100) / 100;
    lastManualPriceEditRef.current = Date.now();
    const next = { ...(courseSettings || {}), price } as any;
    setCourseSettings(next);
    editorDispatch.editPost({ course_settings: next });
  };

  // Handle sale price change (allow empty -> null)
  const handleSalePriceChange = (value: string) => {
    let nextSale: number | null;
    if (value === "" || value === null) {
      nextSale = null;
    } else {
      const raw = parseFloat(value);
      if (isNaN(raw) || raw < 0) {
        nextSale = null;
      } else {
        const currentPrice = Number((courseSettings as any)?.price ?? 0) || 0;
        if (raw >= currentPrice) {
          nextSale = null;
        } else {
          nextSale = Math.round(raw * 100) / 100;
        }
      }
    }
    lastManualPriceEditRef.current = Date.now();
    const next = { ...(courseSettings || {}), sale_price: nextSale } as any;
    setCourseSettings(next);
    editorDispatch.editPost({ course_settings: next });
  };

  // Handle purchase option change — 5a.1 entity-only writes for simple flags
  const handlePurchaseOptionChange = (value: string) => {
    // PMPro-specific warnings when switching between one-time and subscription
    if (isPmproMonetization()) {
      const current = sellingOption;
      if (value === "subscription" && current !== "subscription") {
        const ok = window.confirm(
          __(
            "Switching to Subscription will remove the existing one-time purchase setting for this course. Continue?",
            "tutorpress"
          )
        );
        if (!ok) {
          return;
        }
      } else if (value === "one_time" && current !== "one_time") {
        const ok = window.confirm(
          __(
            "Switching to One-time purchase will remove existing subscription plans for this course. Continue?",
            "tutorpress"
          )
        );
        if (!ok) {
          return;
        }
      }
    }

    // Entity write
    const next = {
      ...(courseSettings || {}),
      selling_option: value,
      subscription_enabled: value === "subscription" || value === "both" || value === "all",
    } as any;
    setCourseSettings(next);
    editorDispatch.editPost({ course_settings: next });
  };

  // Handle WooCommerce product selection
  const handleWooCommerceProductChange = async (productId: string) => {
    // Update the product ID
    const id = String(productId || "");
    {
      const next = { ...(courseSettings || {}), woocommerce_product_id: id } as any;
      setCourseSettings(next);
      editorDispatch.editPost({ course_settings: next });
    }

    // If a product is selected, fetch its details and sync prices
    if (id) {
      try {
        const chosenId = id;
        const productDetails = await fetchWooProductDetails(chosenId, postId);
        if (productDetails) {
          // Validate price data before updating
          const regularPrice = parseFloat(productDetails.regular_price);
          const salePrice = parseFloat(productDetails.sale_price);
          let price = !isNaN(regularPrice) && regularPrice >= 0 ? regularPrice : 0;
          let sale_price: number | null = !isNaN(salePrice) && salePrice >= 0 ? salePrice : null;
          if (sale_price !== null && sale_price >= price) sale_price = null;
          // Last-write guard: ensure current entity still matches chosen id and do not overwrite recent manual edits
          const current = wpSelect("core/editor").getEditedPostAttribute("course_settings") as any;
          const manualWindowMs = 800;
          const recentlyEdited = Date.now() - (lastManualPriceEditRef.current || 0) < manualWindowMs;
          if ((current?.woocommerce_product_id || "") === chosenId && !recentlyEdited) {
            const next = { ...(current || {}), price, sale_price } as any;
            setCourseSettings(next);
            editorDispatch.editPost({ course_settings: next });
          }
        }
      } catch (error) {
        // Show user-friendly error message
        setWooCommerceError(
          __("Failed to load product details. Please try selecting the product again.", "tutorpress")
        );
        // Don't update prices if there's an error - keep existing values
      }
    } else {
      // Reset prices when no product is selected
      const next = { ...(courseSettings || {}), price: 0, sale_price: null } as any;
      setCourseSettings(next);
      editorDispatch.editPost({ course_settings: next });
    }

    // 5a.2: entity-only writes for product id (no legacy mirror)
    // Clear any previous errors on successful update
    if (woocommerceError) {
      setWooCommerceError(null);
    }
  };

  // Handle EDD product selection
  const handleEddProductChange = async (productId: string) => {
    // Update the product ID
    const id = String(productId || "");
    {
      const next = { ...(courseSettings || {}), edd_product_id: id } as any;
      setCourseSettings(next);
      editorDispatch.editPost({ course_settings: next });
    }

    // If a product is selected, fetch its details and sync prices
    if (id) {
      try {
        const chosenId = id;
        const productDetails = await fetchEddProductDetails(chosenId, postId);
        if (productDetails) {
          // Validate price data before updating
          const regularPrice = parseFloat(productDetails.regular_price);
          const salePrice = parseFloat(productDetails.sale_price);
          let price = !isNaN(regularPrice) && regularPrice >= 0 ? regularPrice : 0;
          let sale_price: number | null = !isNaN(salePrice) && salePrice >= 0 ? salePrice : null;
          if (sale_price !== null && sale_price >= price) sale_price = null;
          // Last-write guard: ensure current entity still matches chosen id and do not overwrite recent manual edits
          const current = wpSelect("core/editor").getEditedPostAttribute("course_settings") as any;
          const manualWindowMs = 800;
          const recentlyEdited = Date.now() - (lastManualPriceEditRef.current || 0) < manualWindowMs;
          if ((current?.edd_product_id || "") === chosenId && !recentlyEdited) {
            const next = { ...(current || {}), price, sale_price } as any;
            setCourseSettings(next);
            editorDispatch.editPost({ course_settings: next });
          }
        }
      } catch (error) {
        // Show user-friendly error message
        setEddError(__("Failed to load product details. Please try selecting the product again.", "tutorpress"));
        // Don't update prices if there's an error - keep existing values
      }
    } else {
      // Reset prices when no product is selected
      const next = { ...(courseSettings || {}), price: 0, sale_price: null } as any;
      setCourseSettings(next);
      editorDispatch.editPost({ course_settings: next });
    }

    // 5a.2: entity-only writes for product id (no legacy mirror)
    // Clear any previous errors on successful update
    if (eddError) {
      setEddError(null);
    }
  };

  const shouldShowPurchaseOptions =
    uiPricingModel === "paid" &&
    isMonetizationEnabled() &&
    ((getPaymentEngine() === "tutor_pro" && window.tutorpressAddons?.subscription) ||
      (getPaymentEngine() === "pmpro" && isPmproAvailable()));

  // Helper function to determine if price fields should be shown
  const shouldShowPriceFields = () => {
    // Don't show if pricing model is not "paid" or monetization is disabled
    if (uiPricingModel !== "paid" || !isMonetizationEnabled()) {
      return false;
    }

    // Don't show price fields when WooCommerce monetization is active
    if (isWooCommerceMonetization()) {
      return false;
    }

    // Don't show price fields when EDD monetization is active
    if (isEddMonetization()) {
      return false;
    }

    // If neither subscription addon is enabled nor PMPro is selected, always show price fields for "paid" courses
    if (!(window.tutorpressAddons?.subscription ?? false) && getPaymentEngine() !== "pmpro") {
      return true;
    }

    // If subscription addon is enabled OR PMPro is selected, show based on selling option
    return ["one_time", "both", "all"].includes(sellingOption);
  };

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
      {/* Render the SubscriptionModal at the root of the panel, not inside the button container */}
      <SubscriptionModal
        isOpen={isSubscriptionModalOpen}
        onClose={handleSubscriptionModalClose}
        courseId={postId}
        initialPlan={editingPlan}
        shouldShowForm={shouldShowForm}
      />
      {/* No legacy error state */}

      {/* Pricing Model Selection */}
      <PanelRow>
        <RadioControl
          label={__("Pricing Type", "tutorpress")}
          help={
            (sharedCourseSettings?.is_public_course ?? false)
              ? __(
                  "Public courses cannot be paid. Disable 'Public Course' in Course Details to enable paid pricing.",
                  "tutorpress"
                )
              : __("Choose whether this course is free or paid.", "tutorpress")
          }
          selected={uiPricingModel}
          options={[
            {
              label: __("Free", "tutorpress"),
              value: "free",
            },
            // Only show "Paid" option if monetization is enabled AND public course is not enabled
            ...(isMonetizationEnabled() && !(sharedCourseSettings?.is_public_course ?? false)
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

      {/* WooCommerce Product Selector - Only show when WooCommerce monetization is active and course is paid */}
      {isWooCommerceMonetization() && uiPricingModel === "paid" && (
        <PanelRow>
          <SelectControl
            label={__("WooCommerce Product", "tutorpress")}
            help={__(
              "Select a WooCommerce product to link to this course. The product's price will automatically sync to this course. Only products not already linked to other courses are shown.",
              "tutorpress"
            )}
            value={wooSelectedId}
            options={wooOptions as any}
            onChange={handleWooCommerceProductChange}
            disabled={woocommerceLoading}
          />
          {woocommerceError && (
            <div style={{ marginTop: "8px" }}>
              <Notice status="error" isDismissible={false}>
                {woocommerceError}
              </Notice>
            </div>
          )}
        </PanelRow>
      )}

      {/* EDD Product Selector - Only show when EDD monetization is active and course is paid */}
      {isEddMonetization() && uiPricingModel === "paid" && (
        <PanelRow>
          <SelectControl
            label={__("EDD Product", "tutorpress")}
            help={__(
              "Select an EDD product to link to this course. The product's price will automatically sync to this course. Only products not already linked to other courses are shown.",
              "tutorpress"
            )}
            value={eddSelectedId}
            options={eddOptions as any}
            onChange={handleEddProductChange}
            disabled={eddLoading}
          />
          {eddError && (
            <div style={{ marginTop: "8px" }}>
              <Notice status="error" isDismissible={false}>
                {eddError}
              </Notice>
            </div>
          )}
        </PanelRow>
      )}

      {/* Purchase Options Dropdown - Only show when conditions are met */}
      {shouldShowPurchaseOptions && (
        <PanelRow>
          <SelectControl
            label={__("Purchase Options", "tutorpress")}
            help={__("Choose how this course can be purchased.", "tutorpress")}
            value={sellingOption}
            options={getPurchaseOptions()}
            onChange={handlePurchaseOptionChange}
          />
        </PanelRow>
      )}

      {/* Price Fields - Show based on pricing model, subscription addon status, and selling option */}
      {shouldShowPriceFields() && (
        <div className="price-fields">
          <PanelRow>
            <div className="price-field">
              <TextControl
                label={__("Regular Price", "tutorpress")}
                help={__("Enter the regular price for this course.", "tutorpress")}
                type="number"
                min="0"
                step="0.01"
                value={((courseSettings as any)?.price ?? 0).toString()}
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
                value={
                  typeof (courseSettings as any)?.sale_price === "number"
                    ? (courseSettings as any)?.sale_price?.toString()
                    : ""
                }
                onChange={handleSalePriceChange}
              />
            </div>
          </PanelRow>
        </div>
      )}

      {/* Subscription Section - Show based on purchase option selection */}
      {uiPricingModel === "paid" &&
        isMonetizationEnabled() &&
        ((window.tutorpressAddons?.subscription ?? false) || getPaymentEngine() === "pmpro") &&
        (sellingOption === "subscription" || sellingOption === "both" || sellingOption === "all") && (
          <PanelRow>
            <div className="subscription-section">
              {/* Existing Plans List */}
              {subscriptionPlans.length > 0 && (
                <div className="tutorpress-saved-files-list">
                  <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
                    {__("Subscription Plans:", "tutorpress")}
                  </div>
                  {subscriptionPlans.map((plan: SubscriptionPlan) => (
                    <div key={plan.id} className="tutorpress-saved-file-item">
                      <div className="plan-info">
                        <div className="plan-name">
                          {plan.plan_name.length > 30 ? `${plan.plan_name.substring(0, 30)}...` : plan.plan_name}
                        </div>
                        <div className="plan-details">
                          ${plan.regular_price} / {plan.recurring_value} {plan.recurring_interval}
                          {plan.sale_price && plan.sale_price > 0 && ` (Sale: $${plan.sale_price})`}
                          {plan.is_featured && " • Featured"}
                        </div>
                      </div>
                      <Button
                        variant="tertiary"
                        icon={edit}
                        onClick={() => handleEditPlan(plan)}
                        className="edit-button"
                        aria-label={__("Edit subscription plan", "tutorpress")}
                      />
                    </div>
                  ))}
                </div>
              )}

              {/* Add/Manage Button */}
              <Button
                icon={plus}
                variant="secondary"
                onClick={handleAddSubscription}
                style={{ marginTop: subscriptionPlans.length > 0 ? "12px" : "0" }}
              >
                {__("Add Subscription", "tutorpress")}
              </Button>
            </div>
          </PanelRow>
        )}
    </PluginDocumentSettingPanel>
  );
};

export default CoursePricingPanel;
