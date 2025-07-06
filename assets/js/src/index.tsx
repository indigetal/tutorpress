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
import { AddonChecker, isH5pEnabled, isCertificateEnabled } from "./utils/addonChecker";
import "./api"; // Import API module to expose it to window

// Import stores to ensure they are registered
import "./store/h5p"; // H5P store registration
import "./store/pricing"; // Pricing store registration

// Conditionally import certificate store only when Certificate addon is enabled
if (isCertificateEnabled()) {
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

// Initialize stores
import "./store/curriculum";
import "./store/course-settings";

// Wait for DOM to be ready for curriculum metabox
document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("tutorpress-curriculum-root");
  if (root) {
    render(<Curriculum />, root);
  }

  // Conditionally render Certificate metabox only when Certificate addon is enabled
  if (isCertificateEnabled()) {
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
void AddonChecker.isH5pEnabled;
void AddonChecker.isCertificateEnabled;
void AddonChecker.isContentDripEnabled;
void AddonChecker.isGoogleMeetEnabled;
void AddonChecker.isZoomEnabled;

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
if (isH5pEnabled()) {
  // Dynamic import to avoid loading H5P components when H5P addon is not available
  import("./components/modals/QuizModal").then(({ QuizModal }) => {
    (window as any).tutorpress.QuizModal = QuizModal;
  });
}
