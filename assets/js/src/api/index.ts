import { getTopics, reorderTopics, duplicateTopic } from "./topics";
import type { TutorPressApi } from "../types/wordpress";

// Create the API object
const api: TutorPressApi = {
  getTopics,
  reorderTopics,
  duplicateTopic,
};

// Export the API object
export default api;

// Initialize window.tutorpress if it doesn't exist
if (typeof window.tutorpress === "undefined") {
  window.tutorpress = {
    api,
  };
} else {
  window.tutorpress.api = api;
}
