/**
 * Subscription Plan Types for TutorPress
 *
 * TypeScript interfaces for subscription plan management,
 * matching Tutor LMS subscription addon database structure.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import type { APIResponse } from "./api";

// ============================================================================
// CORE SUBSCRIPTION PLAN INTERFACES
// ============================================================================

/**
 * Subscription plan interval options
 */
export type SubscriptionInterval = "day" | "week" | "month" | "year";

/**
 * Subscription plan payment type
 */
export type SubscriptionPaymentType = "recurring";

/**
 * Subscription plan type
 */
export type SubscriptionPlanType = "course";

/**
 * Subscription plan restriction mode
 */
export type SubscriptionRestrictionMode = "unlimited" | "limited" | null;

/**
 * Base subscription plan interface matching Tutor LMS database structure
 */
export interface SubscriptionPlan {
  id: number;
  plan_name: string;
  short_description: string | null;
  description: string | null;
  payment_type: SubscriptionPaymentType;
  plan_type: SubscriptionPlanType;
  recurring_value: number;
  recurring_interval: SubscriptionInterval;
  recurring_limit: number;
  regular_price: number;
  sale_price: number | null;
  sale_price_from: string | null;
  sale_price_to: string | null;
  provide_certificate: boolean;
  enrollment_fee: number;
  trial_value: number;
  trial_interval: SubscriptionInterval | null;
  trial_fee: number;
  is_featured: boolean;
  featured_text: string | null;
  is_enabled: boolean;
  plan_order: number;
  restriction_mode: SubscriptionRestrictionMode;
  in_sale_price: boolean; // Computed field
}

/**
 * Create subscription plan data (for POST requests)
 */
export interface CreateSubscriptionPlanData {
  course_id: number;
  plan_name: string;
  short_description?: string;
  description?: string;
  payment_type: SubscriptionPaymentType;
  plan_type: SubscriptionPlanType;
  recurring_value: number;
  recurring_interval: SubscriptionInterval;
  recurring_limit?: number;
  regular_price: number;
  sale_price?: number;
  sale_price_from?: string;
  sale_price_to?: string;
  provide_certificate?: boolean;
  enrollment_fee?: number;
  trial_value?: number;
  trial_interval?: SubscriptionInterval;
  trial_fee?: number;
  is_featured?: boolean;
  featured_text?: string;
  is_enabled?: boolean;
  plan_order?: number;
  restriction_mode?: SubscriptionRestrictionMode;
}

/**
 * Update subscription plan data (for PUT requests)
 */
export interface UpdateSubscriptionPlanData extends Partial<CreateSubscriptionPlanData> {
  course_id: number;
}

// ============================================================================
// API RESPONSE INTERFACES
// ============================================================================

/**
 * Subscription plans response for a course
 */
export type SubscriptionPlansResponse = APIResponse<SubscriptionPlan[]>;

/**
 * Single subscription plan response
 */
export type SubscriptionPlanResponse = APIResponse<SubscriptionPlan>;

/**
 * Subscription plan creation response
 */
export type CreateSubscriptionPlanResponse = APIResponse<SubscriptionPlan>;

/**
 * Subscription plan update response
 */
export type UpdateSubscriptionPlanResponse = APIResponse<SubscriptionPlan>;

/**
 * Subscription plan deletion response
 */
export type DeleteSubscriptionPlanResponse = APIResponse<{ success: boolean }>;

/**
 * Subscription plan duplication response
 */
export type DuplicateSubscriptionPlanResponse = APIResponse<SubscriptionPlan>;

/**
 * Subscription plans sorting response
 */
export type SortSubscriptionPlansResponse = APIResponse<{ success: boolean }>;

// ============================================================================
// STORE STATE INTERFACES
// ============================================================================

/**
 * Subscription form mode
 */
export type SubscriptionFormMode = "add" | "edit" | "duplicate";

/**
 * Subscription form state
 */
export interface SubscriptionFormState {
  mode: SubscriptionFormMode;
  data: Partial<SubscriptionPlan>;
  isDirty: boolean;
}

/**
 * Subscription operations state
 */
export interface SubscriptionOperationsState {
  isLoading: boolean;
  error: string | null;
}

/**
 * Subscription sorting state
 */
export interface SubscriptionSortingState {
  isReordering: boolean;
  error: string | null;
}

/**
 * Main subscription store state
 */
export interface SubscriptionState {
  plans: SubscriptionPlan[];
  selectedPlan: SubscriptionPlan | null;
  formState: SubscriptionFormState;
  operations: SubscriptionOperationsState;
  sorting: SubscriptionSortingState;
}

// ============================================================================
// VALIDATION INTERFACES
// ============================================================================

/**
 * Subscription plan validation errors
 */
export interface SubscriptionValidationErrors {
  plan_name?: string;
  regular_price?: string;
  recurring_value?: string;
  recurring_interval?: string;
  sale_price?: string;
  sale_price_from?: string;
  sale_price_to?: string;
  trial_value?: string;
  trial_interval?: string;
  enrollment_fee?: string;
  recurring_limit?: string;
  featured_text?: string;
  [key: string]: string | undefined;
}

/**
 * Subscription plan validation result
 */
export interface SubscriptionValidationResult {
  isValid: boolean;
  errors: SubscriptionValidationErrors;
}

// ============================================================================
// CONSTANTS
// ============================================================================

/**
 * Default subscription plan values
 */
export const defaultSubscriptionPlan: Partial<SubscriptionPlan> = {
  plan_name: "",
  short_description: "",
  description: "",
  payment_type: "recurring",
  plan_type: "course",
  recurring_value: 1,
  recurring_interval: "month",
  recurring_limit: 0,
  regular_price: 0,
  sale_price: null,
  sale_price_from: null,
  sale_price_to: null,
  provide_certificate: true,
  enrollment_fee: 0,
  trial_value: 0,
  trial_interval: null,
  trial_fee: 0,
  is_featured: false,
  featured_text: null,
  is_enabled: true,
  plan_order: 0,
  restriction_mode: null,
};

/**
 * Subscription interval options for UI
 */
export const subscriptionIntervals: Array<{
  label: string;
  value: SubscriptionInterval;
}> = [
  { label: "Day(s)", value: "day" },
  { label: "Week(s)", value: "week" },
  { label: "Month(s)", value: "month" },
  { label: "Year(s)", value: "year" },
];

/**
 * Subscription plan type options for UI
 */
export const subscriptionPlanTypes: Array<{
  label: string;
  value: SubscriptionPlanType;
}> = [{ label: "Course", value: "course" }];

/**
 * Subscription restriction mode options for UI
 */
export const subscriptionRestrictionModes: Array<{
  label: string;
  value: SubscriptionRestrictionMode;
}> = [
  { label: "Unlimited", value: "unlimited" },
  { label: "Limited", value: "limited" },
];
