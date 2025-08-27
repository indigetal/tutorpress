/**
 * TutorPress Entry Point
 */
import { render } from "@wordpress/element";
import { registerPlugin } from "@wordpress/plugins";
import React from "react";
import Curriculum from "./components/metaboxes/Curriculum";
import AssignmentSettingsPanel from "./components/settings/AssignmentSettingsPanel";
import LessonSettingsPanel from "./components/settings/LessonSettingsPanel";
import CourseDetailsPanel from "./components/settings/CourseDetailsPanel";
import CourseAccessPanel from "./components/settings/CourseAccessPanel";
import CourseMediaPanel from "./components/settings/CourseMediaPanel";
import CoursePricingPanel from "./components/settings/CoursePricingPanel";
import CourseInstructorsPanel from "./components/settings/CourseInstructorsPanel";
import BundlePricingPanel from "./components/settings/BundlePricingPanel";
import BundleInstructorsPanel from "./components/settings/BundleInstructorsPanel";
import EditCourseButton from "./components/common/EditCourseButton";
import { AddonChecker } from "./utils/addonChecker";
import "./utils/overrides";

// Import stores to ensure they are registered
import "./store/h5p"; // H5P store registration

// Conditionally import certificate store only when Certificate addon is enabled
if (window.tutorpressAddons?.certificate ?? false) {
  // Use synchronous import to ensure store is registered immediately
  require("./store/certificate");
}

// Always import additional content store (core fields always available)
require("./store/additional-content");

// Import CSS for bundling
import "../../css/src/index.css";

// Import content drip utilities
import {
  getDefaultContentDripItemSettings,
  getEmptyContentDripInfo,
  isContentDripSettingsEmpty,
  validateContentDripSettings,
  isContentDripItemSettings,
  isContentDripInfo,
} from "./types/content-drip";

// Register the assignment settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-assignment-settings", {
  render: AssignmentSettingsPanel,
});

// Register the lesson settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-lesson-settings", {
  render: LessonSettingsPanel,
});

// Register the course details settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-course-details-settings", {
  render: CourseDetailsPanel,
});

// Register the course access & enrollment settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-course-access-settings", {
  render: CourseAccessPanel,
});

// Register the course media settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-course-media-settings", {
  render: CourseMediaPanel,
});

// Register the course pricing settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-course-pricing-settings", {
  render: CoursePricingPanel,
});

// Register the course instructors panel plugin for Gutenberg sidebar
registerPlugin("tutorpress-course-instructors-panel", {
  render: CourseInstructorsPanel,
});

// Register the bundle pricing panel plugin for Gutenberg sidebar
registerPlugin("tutorpress-bundle-pricing-settings", {
  render: BundlePricingPanel,
});

// Register the bundle instructors panel plugin for Gutenberg sidebar
registerPlugin("tutorpress-bundle-instructors-settings", {
  render: BundleInstructorsPanel,
});

// Register the edit course button plugin for Gutenberg (Phase 1 - Testing)
registerPlugin("tutorpress-edit-course-button", {
  render: EditCourseButton,
});

// Initialize stores
import "./store/curriculum";
import "./store/subscriptions";
import "./store/course-bundles";
import "./store/attachments-meta";
import "./store/prerequisites";
import "./store/commerce";
import "./store/instructors";

// Legacy course-settings hydration removed; panels read from core entity only

// Wait for DOM to be ready for curriculum metabox
document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("tutorpress-curriculum-root");
  if (root) {
    render(<Curriculum />, root);
  }

  // Render Course Selection metabox for Course Bundles
  const courseSelectionRoot = document.getElementById("tutorpress-bundle-courses-root");
  if (courseSelectionRoot) {
    const bundleId = courseSelectionRoot.getAttribute("data-bundle-id");
    const BundleCourseSelection = require("./components/metaboxes/bundles/CourseSelection").BundleCourseSelection;
    render(<BundleCourseSelection bundleId={bundleId ? parseInt(bundleId) : undefined} />, courseSelectionRoot);
  }

  // Render Benefits metabox for Course Bundles
  const benefitsRoot = document.getElementById("tutorpress-bundle-benefits-root");
  if (benefitsRoot) {
    const Benefits = require("./components/metaboxes/bundles/Benefits").default;
    render(<Benefits />, benefitsRoot);
  }

  // Conditionally render Certificate metabox only when Certificate addon is enabled
  if (window.tutorpressAddons?.certificate ?? false) {
    const certificateRoot = document.getElementById("tutorpress-certificate-root");
    if (certificateRoot) {
      // Use synchronous import to match store loading strategy and avoid race conditions
      const Certificate = require("./components/metaboxes/Certificate").default;
      render(<Certificate />, certificateRoot);
    }
  }

  // Always render Additional Content metabox (core fields always available)
  const additionalContentRoot = document.getElementById("tutorpress-additional-content-root");
  if (additionalContentRoot) {
    // Use synchronous import to match store loading strategy
    const AdditionalContent = require("./components/metaboxes/AdditionalContent").default;
    render(<AdditionalContent />, additionalContentRoot);
  }
});

// Expose utilities to global scope for testing
(window as any).tutorpress = (window as any).tutorpress || {};
(window as any).tutorpress.AddonChecker = AddonChecker;

// Prevent tree-shaking of AddonChecker methods by referencing them
void AddonChecker.isPrerequisitesEnabled;
void AddonChecker.isEnrollmentsEnabled;
// H5P status now comes from backend data (window.tutorpressAddons?.h5p)
// Certificate status now comes from backend data (window.tutorpressAddons?.certificate)
void AddonChecker.isContentDripEnabled;
void AddonChecker.isGoogleMeetEnabled;
void AddonChecker.isZoomEnabled;
void AddonChecker.isEddEnabled;
void AddonChecker.isEddMonetization;
void AddonChecker.isWooCommerceEnabled;
void AddonChecker.isWooCommerceMonetization;
void AddonChecker.getPaymentEngine;
void AddonChecker.isMonetizationEnabled;

// Expose content drip utilities globally for testing and debugging
(window as any).tutorpress.contentDrip = {
  getDefaultContentDripItemSettings,
  getEmptyContentDripInfo,
  isContentDripSettingsEmpty,
  validateContentDripSettings,
  isContentDripItemSettings,
  isContentDripInfo,
};

// Conditionally expose Interactive Quiz components only when H5P is enabled
if (window.tutorpressAddons?.h5p ?? false) {
  // Dynamic import to avoid loading H5P components when H5P addon is not available
  import("./components/modals/QuizModal").then(({ QuizModal }) => {
    (window as any).tutorpress.QuizModal = QuizModal;
  });
}

// Expose subscription components globally for testing
import SubscriptionPlanSection from "./components/modals/subscription/SubscriptionPlanSection";
import { useSortableList } from "./hooks/common/useSortableList";
import { subscriptionStore } from "./store/subscriptions";

(window as any).tutorpress.components = (window as any).tutorpress.components || {};
(window as any).tutorpress.components.SubscriptionPlanSection = SubscriptionPlanSection;
(window as any).tutorpress.hooks = (window as any).tutorpress.hooks || {};
(window as any).tutorpress.hooks.useSortableList = useSortableList;
(window as any).tutorpress.stores = (window as any).tutorpress.stores || {};
(window as any).tutorpress.stores.subscriptions = subscriptionStore;
