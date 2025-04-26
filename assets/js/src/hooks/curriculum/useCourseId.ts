/**
 * Hook to get the course ID from the URL
 * @returns The course ID from the URL query parameter
 */
export function useCourseId(): number {
  return Number(new URLSearchParams(window.location.search).get("post"));
}
