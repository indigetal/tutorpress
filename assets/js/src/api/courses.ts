import { apiService } from "./service";
import type {
  CourseSettings,
  CourseSettingsResponse,
  PrerequisiteCourse,
  PrerequisiteCoursesResponse,
} from "../types/courses";
import type { TutorResponse } from "../types/api";

/**
 * Get course settings
 * @param courseId Course ID
 */
export async function getCourseSettings(courseId: number): Promise<TutorResponse<CourseSettings>> {
  return apiService.get<CourseSettings>(`/courses/${courseId}/settings`);
}

/**
 * Save course settings
 * @param courseId Course ID
 * @param settings Course settings to save
 */
export async function saveCourseSettings(
  courseId: number,
  settings: Partial<CourseSettings>
): Promise<TutorResponse<CourseSettings>> {
  return apiService.post<CourseSettings>(`/courses/${courseId}/settings`, settings);
}

/**
 * Get course prerequisites
 * @param courseId Course ID
 */
export async function getCoursePrerequisites(courseId: number): Promise<TutorResponse<PrerequisiteCourse[]>> {
  return apiService.get<PrerequisiteCourse[]>(`/courses/${courseId}/prerequisites`);
}
