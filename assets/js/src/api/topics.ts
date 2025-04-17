import apiFetch from "@wordpress/api-fetch";

/**
 * Topic content item interface
 */
export interface TopicContent {
  id: number;
  title: string;
  type: "lesson" | "quiz" | "assignment";
  menu_order: number;
  status: string;
}

/**
 * Topic interface from API
 */
export interface TopicData {
  id: number;
  title: string;
  content: string;
  menu_order: number;
  status: string;
  contents: TopicContent[];
}

/**
 * API response interface
 */
interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

/**
 * Get topics for a course
 *
 * @param courseId The ID of the course to get topics for
 * @returns Promise resolving to an array of topics
 * @throws Error if the request fails
 */
export const getTopics = async (courseId: number): Promise<TopicData[]> => {
  try {
    const response = await apiFetch<ApiResponse<TopicData[]>>({
      path: `/tutorpress/v1/topics?course_id=${courseId}`,
    });

    if (!response.success) {
      throw new Error(response.message);
    }

    return response.data;
  } catch (error) {
    console.error("Error fetching topics:", error);
    throw error;
  }
};

/**
 * Test function to debug the topics endpoint in browser console
 * Usage: window.testTopicsEndpoint(courseId)
 */
declare global {
  interface Window {
    testTopicsEndpoint: (courseId: number) => Promise<void>;
  }
}

window.testTopicsEndpoint = async (courseId: number) => {
  console.log(`Testing topics endpoint for course ID: ${courseId}`);
  try {
    const topics = await getTopics(courseId);
    console.log("Success! Topics retrieved:", topics);

    // Log some helpful statistics
    console.log("\nSummary:");
    console.log(`Total topics: ${topics.length}`);
    console.log("Topics with content:", topics.filter((t) => t.contents.length > 0).length);
    console.log(
      "Content items by type:",
      topics.reduce(
        (acc, topic) => {
          topic.contents.forEach((item) => {
            acc[item.type] = (acc[item.type] || 0) + 1;
          });
          return acc;
        },
        {} as Record<string, number>
      )
    );
  } catch (error) {
    console.error("Test failed:", error);
  }
};
