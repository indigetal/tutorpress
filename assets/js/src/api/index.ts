import { getTopics, reorderTopics, duplicateTopic } from "./topics";

// Define the API interface
interface TutorPressApi {
  getTopics: typeof getTopics;
  reorderTopics: typeof reorderTopics;
  duplicateTopic: typeof duplicateTopic;
}

// Create the API object
const api: TutorPressApi = {
  getTopics,
  reorderTopics,
  duplicateTopic,
};

// Export the API object
export default api;

// Expose to window object for testing
declare global {
  interface Window {
    tutorpress: {
      api: TutorPressApi;
    };
  }
}

// Initialize window.tutorpress if it doesn't exist
if (typeof window.tutorpress === "undefined") {
  window.tutorpress = {
    api,
  };
} else {
  window.tutorpress.api = api;
}
