/**
 * Course Curriculum Metabox Entry Point
 */
import { render } from "@wordpress/element";
import React from "react";
import Curriculum from "./components/metaboxes/Curriculum";

// Wait for DOM to be ready
document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("tutorpress-curriculum-root");
  if (root) {
    render(<Curriculum />, root);
  }
});
