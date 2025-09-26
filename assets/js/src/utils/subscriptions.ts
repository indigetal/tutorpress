const API_ROOT = "/tutorpress/v1";

export const buildFetchRequest = (objectId: number, postType: "course" | "course-bundle" = "course") => {
  const path =
    postType === "course-bundle"
      ? `${API_ROOT}/bundles/${objectId}/subscriptions`
      : `${API_ROOT}/courses/${objectId}/subscriptions`;
  return { path, method: "GET" };
};

export const buildCreateRequest = (objectId: number, data: any) => ({
  path: `${API_ROOT}/subscriptions`,
  method: "POST",
  data: { ...data, object_id: objectId },
});

export const buildUpdateRequest = (id: number, data: any) => ({
  path: `${API_ROOT}/subscriptions/${id}`,
  method: "PUT",
  data,
});

export const buildDeleteRequest = (id: number, objectId?: number) => ({
  path: `${API_ROOT}/subscriptions/${id}`,
  method: "DELETE",
  data: typeof objectId !== "undefined" ? { object_id: objectId } : undefined,
});

export const buildDuplicateRequest = (id: number, objectId?: number) => ({
  path: `${API_ROOT}/subscriptions/${id}/duplicate`,
  method: "POST",
  data: typeof objectId !== "undefined" ? { object_id: objectId } : undefined,
});

export const buildSortRequest = (objectId: number, postType: "course" | "course-bundle", planOrder: number[]) => ({
  path:
    postType === "course-bundle"
      ? `${API_ROOT}/bundles/${objectId}/subscriptions/sort`
      : `${API_ROOT}/courses/${objectId}/subscriptions/sort`,
  method: "PUT",
  data: { plan_order: planOrder },
});

export default {
  buildFetchRequest,
  buildCreateRequest,
  buildUpdateRequest,
  buildDeleteRequest,
  buildDuplicateRequest,
  buildSortRequest,
};
