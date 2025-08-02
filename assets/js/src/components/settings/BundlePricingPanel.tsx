/**
 * Bundle Pricing Panel Component
 *
 * Skeleton component for bundle pricing and ribbon settings.
 * Will be expanded in Setting 3: Pricing & Ribbon Display.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React, { useEffect, useState } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, TextControl, SelectControl } from "@wordpress/components";
import { store as noticesStore } from "@wordpress/notices";

// Import bundle types
import type { BundlePricing, BundleRibbonType } from "../../types/bundle";

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

  // Get bundle data from our store and Gutenberg store
  const { postType, postId, pricingData, isLoading, error, bundleCourseIds } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      postId: select("core/editor").getCurrentPostId(),
      pricingData: select("tutorpress/course-bundles").getBundlePricingData(),
      isLoading: select("tutorpress/course-bundles").getBundlePricingLoading(),
      error: select("tutorpress/course-bundles").getBundlePricingError(),
      bundleCourseIds: select("core/editor").getEditedPostAttribute("meta")?.["bundle-course-ids"] || "",
    }),
    []
  );

  // Get dispatch actions
  const { getBundlePricing, updateBundlePricing } = useDispatch("tutorpress/course-bundles");
  const { createNotice } = useDispatch(noticesStore);

  // Fetch bundle pricing when component mounts
  useEffect(() => {
    if (postId && postType === "course-bundle") {
      getBundlePricing(postId);
    }
  }, [postId, postType, getBundlePricing]);

  // Calculate regular price when bundle courses change
  useEffect(() => {
    const updateRegularPrice = async () => {
      if (!postId || !bundleCourseIds) {
        setCalculatedRegularPrice(0);
        return;
      }

      setIsCalculating(true);
      try {
        // Calculate regular price from bundle courses
        const regularPrice = await calculateBundleRegularPrice(postId);
        setCalculatedRegularPrice(regularPrice);

        // Update the pricing data with the calculated regular price
        if (pricingData && regularPrice !== pricingData.regular_price) {
          updateBundlePricing(postId, {
            ...pricingData,
            regular_price: regularPrice,
          });
        }
      } catch (error) {
        console.error("Error updating regular price:", error);
        setCalculatedRegularPrice(0);
      } finally {
        setIsCalculating(false);
      }
    };

    // Only calculate if we have pricing data and bundle courses have changed
    if (pricingData && bundleCourseIds) {
      updateRegularPrice();
    }
  }, [postId, bundleCourseIds]); // Removed pricingData to prevent infinite loops

  // Listen for course changes via custom events
  useEffect(() => {
    const handleCourseChange = async (event: Event) => {
      const customEvent = event as CustomEvent;
      // Only respond to events for this bundle
      if (customEvent.detail?.bundleId !== postId) return;

      if (!postId || !pricingData) return;

      setIsCalculating(true);
      try {
        const regularPrice = await calculateBundleRegularPrice(postId);
        setCalculatedRegularPrice(regularPrice);

        if (regularPrice !== pricingData.regular_price) {
          updateBundlePricing(postId, {
            ...pricingData,
            regular_price: regularPrice,
          });
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
  }, [postId, pricingData]);

  // Handle sale price change (immediate Gutenberg update like Course Settings)
  const handleSalePriceChange = (value: string) => {
    if (pricingData && postId) {
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

      updateBundlePricing(postId, {
        ...pricingData,
        sale_price: bundlePrice,
      });
    }
  };

  // Handle ribbon type change (immediate Gutenberg update like Course Settings)
  const handleRibbonTypeChange = (value: string) => {
    if (pricingData && postId) {
      updateBundlePricing(postId, {
        ...pricingData,
        ribbon_type: value as BundleRibbonType,
      });
    }
  };

  // Ribbon type options
  const ribbonOptions = [
    { label: __("Show Discount % Off", "tutorpress"), value: "in_percentage" },
    { label: __("Show Discount Amount ($)", "tutorpress"), value: "in_amount" },
    { label: __("Show None", "tutorpress"), value: "none" },
  ];

  // Don't render if not on a course-bundle post
  if (postType !== "course-bundle") {
    return null;
  }

  return (
    <PluginDocumentSettingPanel
      name="bundle-pricing"
      title={__("Bundle Pricing", "tutorpress")}
      className="tutorpress-bundle-pricing-panel"
    >
      {isLoading && (
        <PanelRow>
          <Spinner />
          <span>{__("Loading bundle pricing...", "tutorpress")}</span>
        </PanelRow>
      )}

      {error && (
        <PanelRow>
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        </PanelRow>
      )}

      {!isLoading && pricingData && (
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

          {/* Ribbon Type Selection */}
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
