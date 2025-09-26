import type { CreateSubscriptionPlanData, SubscriptionPlan } from "../types/subscriptions";

/**
 * PMPro <-> TutorPress field mapper
 * Keep minimal and defensive: convert types and preserve unknown fields via meta.
 */
export interface PMProLevel {
  id?: number;
  name?: string;
  description?: string;
  initial_payment?: number;
  billing_amount?: number;
  cycle_period?: string;
  cycle_number?: number;
  trial_limit?: number;
  trial_amount?: number;
  meta?: Record<string, any>;
}

export const mapUIToPmpro = (ui: Partial<CreateSubscriptionPlanData>): PMProLevel => {
  return {
    name: ui.plan_name ?? ui.plan_name ?? "",
    description: ui.description ?? ui.short_description ?? "",
    initial_payment: typeof ui.regular_price === "number" ? ui.regular_price : Number(ui.regular_price) || 0,
    billing_amount: typeof ui.recurring_value === "number" ? ui.recurring_value : Number(ui.recurring_value) || 0,
    cycle_period: ui.recurring_interval ?? "month",
    cycle_number: ui.recurring_limit ?? 0,
    trial_limit: ui.trial_value ?? 0,
    trial_amount: ui.trial_fee ?? 0,
    meta: {
      sale_price: typeof ui.sale_price !== "undefined" ? ui.sale_price : null,
    },
  };
};

export const mapPmproToUI = (level: PMProLevel): Partial<SubscriptionPlan> => {
  return {
    plan_name: level.name ?? "",
    description: level.description ?? null,
    regular_price: Number(level.initial_payment) || 0,
    recurring_value: Number(level.billing_amount) || 0,
    recurring_interval: (level.cycle_period as any) || "month",
    recurring_limit: level.cycle_number ?? 0,
    trial_value: level.trial_limit ?? 0,
    trial_fee: level.trial_amount ?? 0,
    sale_price: level.meta?.sale_price ?? null,
  } as Partial<SubscriptionPlan>;
};

export default { mapUIToPmpro, mapPmproToUI };
