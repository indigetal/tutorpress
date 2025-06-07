/**
 * TutorPress Entry Point
 */
import { render } from "@wordpress/element";
import { registerPlugin } from "@wordpress/plugins";
import React from "react";
import Curriculum from "./components/metaboxes/Curriculum";
import AssignmentSettingsPanel from "./components/settings/AssignmentSettingsPanel";
import LessonSettingsPanel from "./components/settings/LessonSettingsPanel";
import { QuizModal } from "./components/modals/QuizModal";
import "./api"; // Import API module to expose it to window

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

// Expose QuizModal to global scope for testing
(window as any).tutorpress = (window as any).tutorpress || {};
(window as any).tutorpress.QuizModal = QuizModal;
