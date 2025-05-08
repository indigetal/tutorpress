/**
 * Hook to get the course ID in both course editor and lesson editor contexts
 * @returns The course ID from either the URL query parameter (course editor) or the parent topic's parent (lesson editor)
 */
export function useCourseId(): number {
  // Get the context from the localized script data
  const isLesson = (window as any).tutorPressCurriculum?.isLesson;

  // Get the post ID from the URL query parameter
  const postId = Number(new URLSearchParams(window.location.search).get("post"));

  // If we're in the course editor, return the post ID directly
  if (!isLesson) {
    return postId;
  }

  // If we're in the lesson editor, we need to get the course ID from the parent topic's parent
  // This will be implemented in the next phase
  return 0;
}
