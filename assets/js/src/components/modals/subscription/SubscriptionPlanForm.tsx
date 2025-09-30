import React, { useState, useEffect } from "react";
import {
  Card,
  CardBody,
  Button,
  Flex,
  TextControl,
  SelectControl,
  CheckboxControl,
  ToggleControl,
  TextareaControl,
  Popover,
  __experimentalHStack as HStack,
  FlexItem,
  DatePicker,
  Notice,
  Spinner,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { calendar } from "@wordpress/icons";
import type { SubscriptionPlan, SubscriptionValidationErrors } from "../../../types/subscriptions";
import { defaultSubscriptionPlan, subscriptionIntervals } from "../../../types/subscriptions";
import { isPmproMonetization } from "../../../utils/addonChecker";

// Import our reusable datetime validation utilities
import {
  parseGMTString,
  displayDate,
  displayTime,
  combineDateTime,
  convertToGMT,
  generateTimeOptions,
  filterEndTimeOptions,
  validateAndCorrectDateTime,
} from "../../../utils/datetime-validation";

/**
 * Props for subscription plan form component
 */
export interface SubscriptionPlanFormProps {
  initialData?: Partial<SubscriptionPlan>;
  onSave: (data: Partial<SubscriptionPlan>) => void;
  onCancel: () => void;
  error?: string;
  isSaving?: boolean;
  mode?: "add" | "edit" | "duplicate";
}

/**
 * Billing cycle options for subscription plans
 */
const billingCycleOptions = [
  { label: "3 times", value: "3" },
  { label: "6 times", value: "6" },
  { label: "9 times", value: "9" },
  { label: "12 times", value: "12" },
  { label: "Until Cancelled", value: "0" },
];

/**
 * Time options for sale scheduling (using reusable utility)
 */
const timeOptions = generateTimeOptions(60); // One-hour intervals

/**
 * Subscription plan form component for adding/editing subscription plans
 */
export const SubscriptionPlanForm: React.FC<SubscriptionPlanFormProps> = ({
  initialData,
  onSave,
  onCancel,
  error,
  isSaving,
  mode = "add",
}): JSX.Element => {
  // Get store state and actions
  const {
    formData,
    formMode,
    isLoading,
    error: storeError,
  } = useSelect(
    (select: any) => ({
      formData: select("tutorpress/subscriptions").getFormData(),
      formMode: select("tutorpress/subscriptions").getFormMode(),
      isLoading: select("tutorpress/subscriptions").getSubscriptionPlansLoading(),
      error: select("tutorpress/subscriptions").getSubscriptionPlansError(),
    }),
    []
  );

  const {
    setFormData: updateFormData,
    setFormMode,
    resetForm,
    createSubscriptionPlan,
    updateSubscriptionPlan,
  } = useDispatch("tutorpress/subscriptions");

  // Local state for date picker popovers (following CourseAccessPanel pattern)
  const [saleStartDatePickerOpen, setSaleStartDatePickerOpen] = useState(false);
  const [saleEndDatePickerOpen, setSaleEndDatePickerOpen] = useState(false);

  // Local validation errors state
  const [validationErrors, setValidationErrors] = useState<SubscriptionValidationErrors>({});

  // Read selling option and post title from editor to support one-time mapping when PMPro active
  const sellingOption = useSelect((select: any) => {
    return (select("core/editor").getEditedPostAttribute?.("course_settings") || {})?.selling_option || "one_time";
  }, []);
  const postTitle = useSelect((select: any) => select("core/editor").getEditedPostAttribute?.("title") || "", []);

  // Initialize form data when component mounts or initialData changes
  useEffect(() => {
    if (initialData) {
      const mergedData = { ...defaultSubscriptionPlan, ...initialData };
      updateFormData(mergedData);
    } else {
      resetForm();
    }
    setFormMode(mode);
    setValidationErrors({});
  }, [initialData, mode, updateFormData, resetForm, setFormMode]);

  /**
   * Validate form data
   */
  const validateForm = (): boolean => {
    const errors: SubscriptionValidationErrors = {};

    // Required field validation
    if (!formData.plan_name?.trim()) {
      errors.plan_name = __("Plan name is required", "tutorpress");
    }

    if (!formData.regular_price || formData.regular_price < 0) {
      errors.regular_price = __("Regular price must be a positive number", "tutorpress");
    }

    if (!formData.recurring_value || formData.recurring_value < 1) {
      errors.recurring_value = __("Billing interval must be at least 1", "tutorpress");
    }

    // Sale price validation
    if (formData.sale_price !== null && formData.sale_price !== undefined) {
      if (formData.sale_price < 0) {
        errors.sale_price = __("Sale price must be a positive number", "tutorpress");
      } else if (formData.sale_price >= (formData.regular_price || 0)) {
        errors.sale_price = __("Sale price must be less than regular price", "tutorpress");
      }
    }

    // Sale date validation
    if (formData.sale_price_from && formData.sale_price_to) {
      const fromDate = new Date(formData.sale_price_from);
      const toDate = new Date(formData.sale_price_to);
      if (fromDate >= toDate) {
        errors.sale_price_from = __("Sale start date must be before end date", "tutorpress");
        errors.sale_price_to = __("Sale end date must be after start date", "tutorpress");
      }
    }

    // Enrollment fee validation
    if (formData.enrollment_fee !== undefined && formData.enrollment_fee < 0) {
      errors.enrollment_fee = __("Enrollment fee must be a positive number", "tutorpress");
    }

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  /**
   * Handle form submission
   */
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (validateForm()) {
      try {
        // Sanitize form data before submission
        const sanitizedData: any = {
          ...formData,
          // Ensure proper data types
          recurring_value: parseInt(String(formData.recurring_value || 1)),
          recurring_interval: formData.recurring_interval || "month", // Ensure valid interval
          recurring_limit: parseInt(String(formData.recurring_limit || 0)),
          regular_price: parseFloat(String(formData.regular_price || 0)),
          sale_price:
            formData.sale_price !== null && formData.sale_price !== undefined
              ? parseFloat(String(formData.sale_price))
              : null,
          enrollment_fee: parseFloat(String(formData.enrollment_fee || 0)),
          trial_value: parseInt(String(formData.trial_value || 0)),
          trial_fee: parseFloat(String(formData.trial_fee || 0)),
          plan_order: parseInt(String(formData.plan_order || 0)),
          // Ensure boolean values
          provide_certificate: Boolean(formData.provide_certificate),
          is_featured: Boolean(formData.is_featured),
          is_enabled: Boolean(formData.is_enabled),
          // Handle null values properly
          short_description: formData.short_description || null,
          description: formData.description || null,
          featured_text: formData.featured_text || null,
          trial_interval: formData.trial_value && formData.trial_value > 0 ? formData.trial_interval : null,
          // Handle date validation
          sale_price_from:
            formData.sale_price_from && formData.sale_price_from !== "0000-00-00 00:00:00"
              ? formData.sale_price_from
              : null,
          sale_price_to:
            formData.sale_price_to && formData.sale_price_to !== "0000-00-00 00:00:00" ? formData.sale_price_to : null,
        };

        // If PMPro is active and the purchase option is one_time, normalize payload for PMPro one-time level
        if (isPmproMonetization() && sellingOption === "one_time") {
          sanitizedData.payment_type = "one_time";
          // Ensure plan_name exists: generate from post title when empty
          if (!sanitizedData.plan_name || !String(sanitizedData.plan_name).trim()) {
            sanitizedData.plan_name = `${postTitle || "Course"} one time`;
          }
          // Clear recurring fields for one-time purchases
          sanitizedData.recurring_value = 0;
          sanitizedData.recurring_interval = null;
          sanitizedData.recurring_limit = 0;
          // Recurring monetary amount should be ignored for one-time
          if (typeof sanitizedData.recurring_price !== "undefined") {
            sanitizedData.recurring_price = 0;
          }
        }

        if (formMode === "add") {
          await createSubscriptionPlan(sanitizedData as any);
        } else if (formMode === "edit" && initialData?.id) {
          await updateSubscriptionPlan(initialData.id, sanitizedData as any);
        }

        // Call the onSave callback with the sanitized data
        onSave(sanitizedData);
      } catch (error) {
        console.error("Error saving subscription plan:", error);
      }
    }
  };

  /**
   * Update form field
   */
  const updateField = (field: keyof SubscriptionPlan, value: any) => {
    updateFormData({ [field]: value });

    // Clear validation error for this field
    if (validationErrors[field as keyof SubscriptionValidationErrors]) {
      setValidationErrors((prev) => ({ ...prev, [field]: undefined }));
    }
  };

  // Get current dates for date pickers (following CourseAccessPanel pattern)
  const currentDate = new Date();
  const saleStartDate = parseGMTString(formData.sale_price_from) || currentDate;
  const saleEndDate = parseGMTString(formData.sale_price_to) || currentDate;

  // Check if form is valid
  const isValid =
    formData.plan_name?.trim() && (formData.regular_price || 0) > 0 && (formData.recurring_value || 0) > 0;

  return (
    <Card className="tutorpress-subscription-plan-form" style={{ boxShadow: "0 0 0 2px #007cba33" }}>
      <form onSubmit={handleSubmit}>
        <CardBody>
          <Flex direction="column" gap={3}>
            {/* Error Display */}
            {(error || storeError) && (
              <Notice status="error" isDismissible={false}>
                {error || storeError}
              </Notice>
            )}

            {/* Loading State */}
            {isLoading && (
              <div style={{ textAlign: "center", padding: "20px 0" }}>
                <Spinner />
              </div>
            )}
            {/* Error Summary */}
            {error && <div style={{ color: "#cc1818", marginBottom: "8px" }}>{error}</div>}

            {/* Plan Name */}
            <TextControl
              label={__("Plan Name", "tutorpress")}
              placeholder={__("Enter plan name", "tutorpress")}
              value={formData.plan_name || ""}
              onChange={(plan_name) => updateField("plan_name", plan_name)}
              autoFocus
              required
              help={validationErrors.plan_name}
              className={validationErrors.plan_name ? "has-error" : ""}
            />

            {/* Price and Billing Section - moved below Enrollment/Initial Payment per UX spec */}

            {/* Enrollment / Initial Payment */}
            <div>
              {isPmproMonetization() ? (
                <TextControl
                  label={__("Initial Payment", "tutorpress")}
                  placeholder="0"
                  type="number"
                  value={String(formData.enrollment_fee || 0)}
                  onChange={(enrollment_fee: string) => updateField("enrollment_fee", parseFloat(enrollment_fee) || 0)}
                  min={0}
                  step={0.01}
                  help={validationErrors.enrollment_fee}
                  className={validationErrors.enrollment_fee ? "has-error" : ""}
                />
              ) : (
                <>
                  <CheckboxControl
                    label={__("Charge Enrollment Fee", "tutorpress")}
                    checked={!!(formData.enrollment_fee && formData.enrollment_fee > 0)}
                    onChange={(checked) => updateField("enrollment_fee", checked ? formData.enrollment_fee || 10 : 0)}
                  />
                  {formData.enrollment_fee && formData.enrollment_fee > 0 ? (
                    <TextControl
                      label={__("Enrollment Fee", "tutorpress")}
                      placeholder="0"
                      type="number"
                      value={String(formData.enrollment_fee)}
                      onChange={(enrollment_fee: string) =>
                        updateField("enrollment_fee", parseFloat(enrollment_fee) || 0)
                      }
                      min={0}
                      step={0.01}
                      help={validationErrors.enrollment_fee}
                      className={validationErrors.enrollment_fee ? "has-error" : ""}
                    />
                  ) : null}
                </>
              )}
            </div>

            {/* Price and Billing Section - 4 fields on same line */}
            <div className="tutorpress-subscription-plan-grid-row">
              <div className="plan-regular-price">
                <TextControl
                  label={isPmproMonetization() ? __("Renewal Price", "tutorpress") : __("Regular Price", "tutorpress")}
                  placeholder="0"
                  type="number"
                  value={String(formData.regular_price || 0)}
                  onChange={(regular_price: string) => updateField("regular_price", parseFloat(regular_price) || 0)}
                  min={0}
                  step={0.01}
                  required
                  help={validationErrors.regular_price}
                  className={validationErrors.regular_price ? "has-error" : ""}
                />
              </div>
              <div className="plan-billing-interval">
                <TextControl
                  label={__("Billing Interval", "tutorpress")}
                  placeholder="1"
                  type="number"
                  value={String(formData.recurring_value || 1)}
                  onChange={(recurring_value: string) => updateField("recurring_value", parseInt(recurring_value) || 1)}
                  min={1}
                  required
                  help={validationErrors.recurring_value}
                  className={validationErrors.recurring_value ? "has-error" : ""}
                />
              </div>
              <div className="plan-interval-type">
                <SelectControl
                  label={__("Interval Type", "tutorpress")}
                  value={formData.recurring_interval || "month"}
                  options={subscriptionIntervals}
                  onChange={(recurring_interval) => updateField("recurring_interval", recurring_interval)}
                  required
                />
              </div>
              <div className="plan-billing-cycles">
                <SelectControl
                  label={__("Billing Cycles", "tutorpress")}
                  value={String(formData.recurring_limit || 0)}
                  options={billingCycleOptions}
                  onChange={(recurring_limit: string) => updateField("recurring_limit", parseInt(recurring_limit) || 0)}
                  required
                />
              </div>
            </div>

            {/* Certificate Option */}
            <CheckboxControl
              label={__("Do Not Provide Certificate", "tutorpress")}
              checked={!formData.provide_certificate}
              onChange={(checked) => updateField("provide_certificate", !checked)}
            />

            {/* Featured Plan */}
            <CheckboxControl
              label={__("Mark as Featured", "tutorpress")}
              checked={!!formData.is_featured}
              onChange={(is_featured) => updateField("is_featured", is_featured)}
            />

            {/* Sale Price Section */}
            <div>
              <ToggleControl
                label={__("Offer Sale Price", "tutorpress")}
                checked={formData.sale_price !== null && formData.sale_price !== undefined}
                onChange={(checked) => {
                  if (checked) {
                    updateField("sale_price", 0);
                  } else {
                    updateField("sale_price", null);
                    updateField("sale_price_from", null);
                    updateField("sale_price_to", null);
                  }
                }}
              />

              {formData.sale_price !== null && formData.sale_price !== undefined && (
                <>
                  <TextControl
                    label={__("Sale Price", "tutorpress")}
                    placeholder="0"
                    type="number"
                    value={String(formData.sale_price || 0)}
                    onChange={(sale_price: string) => updateField("sale_price", parseFloat(sale_price) || 0)}
                    min={0}
                    step={0.01}
                    help={validationErrors.sale_price}
                    className={validationErrors.sale_price ? "has-error" : ""}
                  />

                  <CheckboxControl
                    label={__("Schedule the Sale Price", "tutorpress")}
                    checked={
                      !!(
                        formData.sale_price_from &&
                        formData.sale_price_to &&
                        formData.sale_price_from !== "0000-00-00 00:00:00" &&
                        formData.sale_price_to !== "0000-00-00 00:00:00"
                      )
                    }
                    onChange={(checked) => {
                      if (checked) {
                        // Set default dates when enabling scheduling
                        const now = new Date();
                        const future = new Date();
                        future.setDate(future.getDate() + 7); // 7 days from now

                        updateField("sale_price_from", convertToGMT(now));
                        updateField("sale_price_to", convertToGMT(future));
                      } else {
                        updateField("sale_price_from", null);
                        updateField("sale_price_to", null);
                      }
                    }}
                  />

                  {formData.sale_price_from && formData.sale_price_to && (
                    <>
                      {/* Sale Start Date/Time */}
                      <div className="tutorpress-datetime-section">
                        <h4>{__("Sale Start", "tutorpress")}</h4>
                        <HStack spacing={3}>
                          {/* Sale Start Date */}
                          <FlexItem>
                            <div className="tutorpress-date-picker-wrapper">
                              <Button
                                variant="secondary"
                                icon={calendar}
                                onClick={() => setSaleStartDatePickerOpen(!saleStartDatePickerOpen)}
                              >
                                {displayDate(formData.sale_price_from)}
                              </Button>

                              {saleStartDatePickerOpen && (
                                <Popover position="bottom left" onClose={() => setSaleStartDatePickerOpen(false)}>
                                  <DatePicker
                                    currentDate={saleStartDate}
                                    onChange={(date) => {
                                      const newStartDate = new Date(date);
                                      const newDate = combineDateTime(
                                        newStartDate,
                                        displayTime(formData.sale_price_from)
                                      );

                                      // Auto-correct end date if start date is later
                                      const currentEndDate = parseGMTString(formData.sale_price_to) || newStartDate;
                                      const validation = validateAndCorrectDateTime(
                                        newStartDate,
                                        displayTime(formData.sale_price_from),
                                        currentEndDate,
                                        displayTime(formData.sale_price_to)
                                      );

                                      // Always update start date, and auto-correct end date if needed
                                      const updates: any = { sale_price_from: newDate };

                                      if (validation.correctedEndDate) {
                                        updates.sale_price_to = combineDateTime(
                                          validation.correctedEndDate,
                                          displayTime(formData.sale_price_to)
                                        );
                                      }
                                      if (validation.correctedEndTime) {
                                        const endDateToUse = validation.correctedEndDate || currentEndDate;
                                        updates.sale_price_to = combineDateTime(
                                          endDateToUse,
                                          validation.correctedEndTime
                                        );
                                      }

                                      updateField("sale_price_from", updates.sale_price_from);
                                      if (updates.sale_price_to) {
                                        updateField("sale_price_to", updates.sale_price_to);
                                      }

                                      setSaleStartDatePickerOpen(false);
                                    }}
                                  />
                                </Popover>
                              )}
                            </div>
                          </FlexItem>

                          {/* Sale Start Time */}
                          <FlexItem>
                            <SelectControl
                              value={displayTime(formData.sale_price_from)}
                              options={timeOptions}
                              onChange={(value) => {
                                const newStartDate = combineDateTime(saleStartDate, value);
                                updateField("sale_price_from", newStartDate);

                                // Auto-correct end time if it becomes invalid
                                if (formData.sale_price_to) {
                                  const startDateTimeParsed = parseGMTString(formData.sale_price_from);
                                  const endDateTimeParsed = parseGMTString(formData.sale_price_to);

                                  if (startDateTimeParsed && endDateTimeParsed) {
                                    const validationResult = validateAndCorrectDateTime(
                                      startDateTimeParsed,
                                      value,
                                      endDateTimeParsed,
                                      displayTime(formData.sale_price_to)
                                    );

                                    if (validationResult.correctedEndTime) {
                                      const correctedEndDate = combineDateTime(
                                        saleEndDate,
                                        validationResult.correctedEndTime
                                      );
                                      updateField("sale_price_to", correctedEndDate);
                                    }
                                  }
                                }
                              }}
                            />
                          </FlexItem>
                        </HStack>
                      </div>

                      {/* Sale End Date/Time */}
                      <div className="tutorpress-datetime-section">
                        <h4>{__("Sale End", "tutorpress")}</h4>
                        <HStack spacing={3}>
                          {/* Sale End Date */}
                          <FlexItem>
                            <div className="tutorpress-date-picker-wrapper">
                              <Button
                                variant="secondary"
                                icon={calendar}
                                onClick={() => setSaleEndDatePickerOpen(!saleEndDatePickerOpen)}
                              >
                                {displayDate(formData.sale_price_to)}
                              </Button>

                              {saleEndDatePickerOpen && (
                                <Popover position="bottom left" onClose={() => setSaleEndDatePickerOpen(false)}>
                                  <DatePicker
                                    currentDate={saleEndDate}
                                    onChange={(date) => {
                                      const selectedDate = new Date(date);
                                      const newDate = combineDateTime(
                                        selectedDate,
                                        displayTime(formData.sale_price_to)
                                      );

                                      // Auto-correct start date if end date is earlier
                                      const startDateTime = parseGMTString(formData.sale_price_from);
                                      const updates: any = { sale_price_to: newDate };

                                      if (startDateTime) {
                                        const validationResult = validateAndCorrectDateTime(
                                          startDateTime,
                                          displayTime(formData.sale_price_from),
                                          selectedDate,
                                          displayTime(formData.sale_price_to)
                                        );

                                        if (validationResult.correctedEndTime) {
                                          updates.sale_price_to = combineDateTime(
                                            selectedDate,
                                            validationResult.correctedEndTime
                                          );
                                        }

                                        // If end date is before start date, auto-correct start date backward
                                        const startDateOnly = new Date(
                                          startDateTime.getFullYear(),
                                          startDateTime.getMonth(),
                                          startDateTime.getDate()
                                        );
                                        const endDateOnly = new Date(
                                          selectedDate.getFullYear(),
                                          selectedDate.getMonth(),
                                          selectedDate.getDate()
                                        );

                                        if (endDateOnly < startDateOnly) {
                                          updates.sale_price_from = combineDateTime(
                                            selectedDate,
                                            displayTime(formData.sale_price_from)
                                          );
                                        }
                                      }

                                      updateField("sale_price_to", updates.sale_price_to);
                                      if (updates.sale_price_from) {
                                        updateField("sale_price_from", updates.sale_price_from);
                                      }

                                      setSaleEndDatePickerOpen(false);
                                    }}
                                  />
                                </Popover>
                              )}
                            </div>
                          </FlexItem>

                          {/* Sale End Time */}
                          <FlexItem>
                            <SelectControl
                              value={displayTime(formData.sale_price_to)}
                              options={(() => {
                                const startDateTime = parseGMTString(formData.sale_price_from);
                                return startDateTime
                                  ? filterEndTimeOptions(
                                      timeOptions,
                                      startDateTime,
                                      displayTime(formData.sale_price_from),
                                      saleEndDate
                                    )
                                  : timeOptions;
                              })()}
                              onChange={(value) => {
                                const newEndDate = combineDateTime(saleEndDate, value);

                                // Validate and auto-correct if needed
                                const startDateTimeForValidation = parseGMTString(formData.sale_price_from);
                                let finalEndDate = newEndDate;

                                if (startDateTimeForValidation) {
                                  const validationResult = validateAndCorrectDateTime(
                                    startDateTimeForValidation,
                                    displayTime(formData.sale_price_from),
                                    saleEndDate,
                                    value
                                  );

                                  finalEndDate = validationResult.correctedEndTime
                                    ? combineDateTime(saleEndDate, validationResult.correctedEndTime)
                                    : newEndDate;
                                }

                                updateField("sale_price_to", finalEndDate);
                              }}
                            />
                          </FlexItem>
                        </HStack>
                      </div>
                    </>
                  )}
                </>
              )}
            </div>

            {/* Form Actions */}
            <Flex justify="flex-end" gap={2}>
              <Button variant="secondary" onClick={onCancel} disabled={isLoading}>
                {__("Cancel", "tutorpress")}
              </Button>
              <Button variant="primary" type="submit" isBusy={isLoading} disabled={!isValid || isLoading}>
                {formMode === "add" ? __("Add Plan", "tutorpress") : __("Save Changes", "tutorpress")}
              </Button>
            </Flex>
          </Flex>
        </CardBody>
      </form>
    </Card>
  );
};

export default SubscriptionPlanForm;
