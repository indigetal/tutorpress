/**
 * TutorPress Entry Point
 */
import { render } from "@wordpress/element";
import { registerPlugin } from "@wordpress/plugins";
import React from "react";
import Curriculum from "./components/metaboxes/Curriculum";
import AssignmentSettingsPanel from "./components/settings/AssignmentSettingsPanel";
import LessonSettingsPanel from "./components/settings/LessonSettingsPanel";
import { AddonChecker, isH5pEnabled } from "./utils/addonChecker";
import "./api"; // Import API module to expose it to window

// Import stores to ensure they are registered
import "./store/h5p"; // H5P store registration

// Import CSS for bundling
import "../../css/src/index.css";

// Register the assignment settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-assignment-settings", {
  render: AssignmentSettingsPanel,
});

// Register the lesson settings plugin for Gutenberg sidebar
registerPlugin("tutorpress-lesson-settings", {
  render: LessonSettingsPanel,
});

// Wait for DOM to be ready for curriculum metabox
document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("tutorpress-curriculum-root");
  if (root) {
    render(<Curriculum />, root);
  }
});

// Expose utilities to global scope for testing
(window as any).tutorpress = (window as any).tutorpress || {};
(window as any).tutorpress.AddonChecker = AddonChecker;

// Conditionally expose Interactive Quiz components only when H5P is enabled
if (isH5pEnabled()) {
  // Dynamic import to avoid loading H5P components when H5P addon is not available
  import("./components/modals/QuizModal").then(({ QuizModal }) => {
    (window as any).tutorpress.QuizModal = QuizModal;
  });
}
