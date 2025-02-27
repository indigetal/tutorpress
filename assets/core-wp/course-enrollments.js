// Handles course enrollment, prerequisites, and restrictions (including AJAX-based enrollment logic migrated to REST).

document.addEventListener("DOMContentLoaded", function () {
    const enrollButton = document.querySelector(".tutor-enroll-button");
    const courseId = tutor_course_data?.course_id || null; // Assuming tutor_course_data is available globally

    if (!enrollButton || !courseId) {
        return;
    }

    // Fetch course availability from REST API
    fetch(`${window.wpApiSettings.root}tutorpress/v1/course-availability/${courseId}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.fully_booked) {
                // Replace enrollment button with "Fully Booked" notice
                enrollButton.outerHTML = `
                      <div class="list-item-booking booking-full tutor-d-flex tutor-align-center">
                          <div class="booking-progress tutor-d-flex">
                              <span class="tutor-mr-8 tutor-color-warning tutor-icon-circle-info"></span>
                          </div>
                          <div class="tutor-fs-7 tutor-fw-medium">
                              ${window.tutor_localized_strings.fully_booked || "Fully Booked"}
                          </div>
                      </div>
                  `;
            }
        })
        .catch((error) => console.error("Error fetching course availability:", error));
});
