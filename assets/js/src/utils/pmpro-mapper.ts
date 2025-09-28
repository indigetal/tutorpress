import type { CreateSubscriptionPlanData, SubscriptionPlan } from "../types/subscriptions";

/**
 * PMPro <-> TutorPress field mapper with edge-case handling.
 * - Coerces numeric strings to numbers
 * - Normalizes interval tokens (month/months/mo -> month)
 * - Preserves UI-only fields via `meta` (e.g. sale_price)
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

const parseNumber = (v: any): number => {
  if (v === null || typeof v === "undefined" || v === "") return 0;
  if (typeof v === "number") return isFinite(v) && v > 0 ? v : Math.max(0, v || 0);
  // Strip currency and other non-numeric characters
  const cleaned = String(v).replace(/[^0-9.\-]/g, "") || "0";
  const n = parseFloat(cleaned);
  return Number.isFinite(n) ? Math.max(0, n) : 0;
};

const normalizeInterval = (token?: string): string => {
  if (!token) return "month";
  const t = String(token).toLowerCase();
  if (t.startsWith("day")) return "day";
  if (t.startsWith("week")) return "week";
  if (t.startsWith("month") || t === "mo" || t === "m") return "month";
  if (t.startsWith("year") || t === "yr" || t === "y") return "year";
  return "month";
};

export const mapUIToPmpro = (ui: Partial<CreateSubscriptionPlanData>): PMProLevel => {
  const initial_payment = parseNumber((ui as any).regular_price ?? ui.regular_price ?? 0);
  const billing_amount = parseNumber((ui as any).recurring_price ?? ui.recurring_value ?? 0);
  const cycle_period = normalizeInterval((ui as any).recurring_interval ?? ui.recurring_interval);
  const cycle_number = ui.recurring_limit
    ? Math.max(0, Number(ui.recurring_limit))
    : ui.recurring_value
      ? Math.max(0, Number(ui.recurring_value))
      : 0;

  const meta: Record<string, any> = {};
  if (
    typeof (ui as any).sale_price !== "undefined" &&
    (ui as any).sale_price !== null &&
    (ui as any).sale_price !== ""
  ) {
    meta.sale_price = parseNumber((ui as any).sale_price);
  }

  return {
    name: (ui as any).plan_name ?? "",
    description: (ui as any).description ?? (ui as any).short_description ?? "",
    initial_payment,
    billing_amount,
    cycle_period,
    cycle_number,
    trial_limit: ui.trial_value ? Math.max(0, Number(ui.trial_value)) : 0,
    trial_amount: parseNumber(ui.trial_fee ?? 0),
    meta: Object.keys(meta).length ? meta : undefined,
  };
};

export const mapPmproToUI = (level: PMProLevel): Partial<SubscriptionPlan> => {
  const meta = (level && (level as any).meta) || {};
  return {
    plan_name: level.name ?? "",
    description: level.description ?? null,
    regular_price: parseNumber(level.initial_payment),
    recurring_value: parseNumber(level.billing_amount),
    recurring_interval: normalizeInterval(level.cycle_period),
    recurring_limit: level.cycle_number ?? 0,
    trial_value: level.trial_limit ?? 0,
    trial_fee: parseNumber(level.trial_amount ?? 0),
    sale_price:
      typeof meta.sale_price !== "undefined" && meta.sale_price !== null ? parseNumber(meta.sale_price) : null,
  } as Partial<SubscriptionPlan>;
};

export default { mapUIToPmpro, mapPmproToUI };
