import React, { useState, useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, RadioControl, TextControl, Button, SelectControl } from "@wordpress/components";
import { plus, edit } from "@wordpress/icons";

// Import course settings types
import type { CourseSettings, WcProduct } from "../../types/courses";
import type { SubscriptionPlan } from "../../types/subscriptions";
import {
  isMonetizationEnabled,
  isSubscriptionEnabled,
  isWooCommerceMonetization,
  isEddMonetization,
} from "../../utils/addonChecker";
import { SubscriptionModal } from "../modals/subscription/SubscriptionModal";

const CoursePricingPanel: React.FC = () => {
  // Get settings from our store and Gutenberg store
  const {
    postType,
    postId,
    settings,
    error,
    isLoading,
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
      settings: select("tutorpress/course-settings").getSettings(),
      error: select("tutorpress/course-settings").getError(),
      isLoading: select("tutorpress/course-settings").getFetchState().isLoading,
      subscriptionPlans: select("tutorpress/subscriptions").getSubscriptionPlans(),
      subscriptionPlansLoading: select("tutorpress/subscriptions").getSubscriptionPlansLoading(),
      woocommerceProducts: select("tutorpress/commerce").getWooProducts(),
      woocommerceLoading: select("tutorpress/commerce").getWooLoading(),
      eddProducts: select("tutorpress/commerce").getEddProducts(),
      eddLoading: select("tutorpress/commerce").getEddLoading(),
    }),
    []
  );

  // Get dispatch actions
  const { updateSettings } = useDispatch("tutorpress/course-settings");
  const { getSubscriptionPlans } = useDispatch("tutorpress/subscriptions");
  const { fetchWooProducts, fetchWooProductDetails, fetchEddProducts, fetchEddProductDetails } =
    useDispatch("tutorpress/commerce");

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
    if (postId && isSubscriptionEnabled()) {
      getSubscriptionPlans();
    }
  }, [postId, getSubscriptionPlans]);

  // Fetch WooCommerce products when component mounts and WooCommerce is active
  useEffect(() => {
    if (postId && isWooCommerceMonetization()) {
      fetchWooProducts({
        course_id: postId,
        per_page: 50,
      });
    }
  }, [postId, fetchWooProducts]);

  // Fetch EDD products when component mounts and EDD monetization is active
  useEffect(() => {
    if (postId && isEddMonetization()) {
      fetchEddProducts({
        course_id: postId,
        per_page: 50,
      });
    }
  }, [postId, fetchEddProducts]);

  // Validate EDD product selection when products are loaded
  useEffect(() => {
    if (isEddMonetization() && settings?.edd_product_id && eddProducts && !eddLoading) {
      const selectedProductId = settings.edd_product_id;
      const productExists = eddProducts.some((product: any) => product.ID === selectedProductId);
      if (!productExists) {
        // Product not in dropdown - could be linked to current course or truly unavailable
        // Only log once when products are loaded, not on every render
        console.warn("Selected EDD product not in dropdown - may be linked to current course");
      }
    }
  }, [settings?.edd_product_id, eddProducts, eddLoading]);

  // Validate WooCommerce product selection when products are loaded
  useEffect(() => {
    if (isWooCommerceMonetization() && settings?.woocommerce_product_id && woocommerceProducts && !woocommerceLoading) {
      const selectedProductId = settings.woocommerce_product_id;
      const productExists = woocommerceProducts.some((product: WcProduct) => product.ID === selectedProductId);
      if (!productExists) {
        // Product not in dropdown - could be linked to current course or truly unavailable
        // Only log once when products are loaded, not on every render
        console.warn("Selected WooCommerce product not in dropdown - may be linked to current course");
      }
    }
  }, [settings?.woocommerce_product_id, woocommerceProducts, woocommerceLoading]);

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

  // Handle WooCommerce product selection
  const handleWooCommerceProductChange = async (productId: string) => {
    if (settings) {
      // Update the product ID
      const updatedSettings = {
        ...settings,
        woocommerce_product_id: productId,
      };

      // If a product is selected, fetch its details and sync prices
      if (productId) {
        try {
          const productDetails = await fetchWooProductDetails(productId, postId);
          if (productDetails) {
            // Validate price data before updating
            const regularPrice = parseFloat(productDetails.regular_price);
            const salePrice = parseFloat(productDetails.sale_price);

            if (isNaN(regularPrice) || regularPrice < 0) {
              console.warn("Invalid regular price received from WooCommerce product:", productDetails.regular_price);
              updatedSettings.price = 0;
            } else {
              updatedSettings.price = regularPrice;
            }

            if (isNaN(salePrice) || salePrice < 0) {
              console.warn("Invalid sale price received from WooCommerce product:", productDetails.sale_price);
              updatedSettings.sale_price = 0;
            } else {
              updatedSettings.sale_price = salePrice;
            }

            // Validate sale price is not greater than regular price
            if (updatedSettings.sale_price > 0 && updatedSettings.sale_price >= updatedSettings.price) {
              console.warn("Sale price cannot be greater than or equal to regular price. Resetting sale price.");
              updatedSettings.sale_price = 0;
            }
          } else {
            console.warn("No product details received for product ID:", productId);
          }
        } catch (error) {
          console.error("Error fetching WooCommerce product details:", error);
          // Show user-friendly error message
          setWooCommerceError(
            __("Failed to load product details. Please try selecting the product again.", "tutorpress")
          );
          // Don't update prices if there's an error - keep existing values
        }
      } else {
        // Reset prices when no product is selected
        updatedSettings.price = 0;
        updatedSettings.sale_price = 0;
      }

      updateSettings(updatedSettings);

      // Clear any previous errors on successful update
      if (woocommerceError) {
        setWooCommerceError(null);
      }
    }
  };

  // Handle EDD product selection
  const handleEddProductChange = async (productId: string) => {
    if (settings) {
      // Update the product ID
      const updatedSettings = {
        ...settings,
        edd_product_id: productId,
      };

      // If a product is selected, fetch its details and sync prices
      if (productId) {
        try {
          const productDetails = await fetchEddProductDetails(productId, postId);
          if (productDetails) {
            // Validate price data before updating
            const regularPrice = parseFloat(productDetails.regular_price);
            const salePrice = parseFloat(productDetails.sale_price);

            if (isNaN(regularPrice) || regularPrice < 0) {
              console.warn("Invalid regular price received from EDD product:", productDetails.regular_price);
              updatedSettings.price = 0;
            } else {
              updatedSettings.price = regularPrice;
            }

            if (isNaN(salePrice) || salePrice < 0) {
              console.warn("Invalid sale price received from EDD product:", productDetails.sale_price);
              updatedSettings.sale_price = 0;
            } else {
              updatedSettings.sale_price = salePrice;
            }

            // Validate sale price is not greater than regular price
            if (updatedSettings.sale_price > 0 && updatedSettings.sale_price >= updatedSettings.price) {
              console.warn("Sale price cannot be greater than or equal to regular price. Resetting sale price.");
              updatedSettings.sale_price = 0;
            }
          } else {
            console.warn("No product details received for product ID:", productId);
          }
        } catch (error) {
          console.error("Error fetching EDD product details:", error);
          // Show user-friendly error message
          setEddError(__("Failed to load product details. Please try selecting the product again.", "tutorpress"));
          // Don't update prices if there's an error - keep existing values
        }
      } else {
        // Reset prices when no product is selected
        updatedSettings.price = 0;
        updatedSettings.sale_price = 0;
      }

      updateSettings(updatedSettings);

      // Clear any previous errors on successful update
      if (eddError) {
        setEddError(null);
      }
    }
  };

  // Check if purchase options should be shown
  const shouldShowPurchaseOptions =
    settings?.pricing_model === "paid" && isMonetizationEnabled() && isSubscriptionEnabled();

  // Helper function to determine if price fields should be shown
  const shouldShowPriceFields = () => {
    // Don't show if pricing model is not "paid" or monetization is disabled
    if (settings?.pricing_model !== "paid" || !isMonetizationEnabled()) {
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

    // If subscription addon is disabled, always show price fields for "paid" courses
    if (!isSubscriptionEnabled()) {
      return true;
    }

    // If subscription addon is enabled, show based on selling option
    const sellingOption = settings?.selling_option || "one_time";
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

      {/* WooCommerce Product Selector - Only show when WooCommerce monetization is active and course is paid */}
      {isWooCommerceMonetization() && settings?.pricing_model === "paid" && (
        <PanelRow>
          <SelectControl
            label={__("WooCommerce Product", "tutorpress")}
            help={__(
              "Select a WooCommerce product to link to this course. The product's price will automatically sync to this course. Only products not already linked to other courses are shown.",
              "tutorpress"
            )}
            value={settings?.woocommerce_product_id || ""}
            options={[
              {
                label: __("Select a product", "tutorpress"),
                value: "",
              },
              ...(woocommerceProducts || []).map((product: WcProduct) => ({
                label: product.post_title,
                value: product.ID,
              })),
            ]}
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
      {isEddMonetization() && settings?.pricing_model === "paid" && (
        <PanelRow>
          <SelectControl
            label={__("EDD Product", "tutorpress")}
            help={__(
              "Select an EDD product to link to this course. The product's price will automatically sync to this course. Only products not already linked to other courses are shown.",
              "tutorpress"
            )}
            value={settings?.edd_product_id || ""}
            options={[
              {
                label: __("Select a product", "tutorpress"),
                value: "",
              },
              ...(eddProducts || []).map((product: any) => ({
                label: product.post_title,
                value: product.ID,
              })),
            ]}
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
            value={settings?.selling_option || "one_time"}
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

      {/* Subscription Section - Show based on purchase option selection */}
      {settings?.pricing_model === "paid" &&
        isMonetizationEnabled() &&
        isSubscriptionEnabled() &&
        (settings?.selling_option === "subscription" ||
          settings?.selling_option === "both" ||
          settings?.selling_option === "all") && (
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
