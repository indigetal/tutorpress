/**
 * Bundle Certificate Toggle Component
 *
 * Toggle control for allowing/disallowing individual course certificates
 * within a bundle.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import React from "react";
import { ToggleControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { BundleCertificateToggleProps } from "../../../types/certificate";

/**
 * BundleCertificateToggle Component
 *
 * Allows instructors to control whether individual courses within a bundle
 * can award their own certificates, or if only the bundle completion
 * certificate should be awarded.
 *
 * Features:
 * - Simple toggle interface
 * - Context-aware help text
 * - Disabled state support
 * - Accessible design using WordPress components
 */
export const BundleCertificateToggle: React.FC<BundleCertificateToggleProps> = ({
  value,
  onChange,
  disabled = false,
  help,
}) => {
  const isChecked = value === "1";

  const handleChange = (checked: boolean) => {
    onChange(checked ? "1" : "0");
  };

  const defaultHelp = isChecked
    ? __("Individual courses in this bundle can award their own certificates upon completion.", "tutorpress")
    : __("Only the bundle completion certificate will be awarded. Individual courses will not issue certificates.", "tutorpress");

  return (
    <ToggleControl
      label={__("Allow Individual Course Certificates", "tutorpress")}
      help={help || defaultHelp}
      checked={isChecked}
      onChange={handleChange}
      disabled={disabled}
    />
  );
};

export default BundleCertificateToggle;
