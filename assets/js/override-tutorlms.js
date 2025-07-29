document.addEventListener("DOMContentLoaded", function () {
  // Function to override "Create A New Course" button
  function overrideCreateCourseButton() {
    // Check setting FIRST - before touching the button
    if (typeof TutorPressData === "undefined" || !TutorPressData.enableDashboardRedirects) {
      return; // Exit before we modify anything
    }

    let createCourseButton = document.querySelector(".tutor-dashboard-create-course");
    if (!createCourseButton) {
      return; // If button is missing, do nothing
    }

    // Remove existing event listeners added by Tutor LMS
    let newButton = createCourseButton.cloneNode(true);
    createCourseButton.parentNode.replaceChild(newButton, createCourseButton);
    createCourseButton = newButton;

    // Add our custom click event
    createCourseButton.addEventListener("click", function (event) {
      event.preventDefault();
      event.stopPropagation();

      // Kill any pending AJAX requests to prevent errors
      if (window.fetch) {
        window.fetch = function () {
          return new Promise(() => {});
        };
      }

      // Get the correct admin URL from PHP
      let adminUrl =
        typeof TutorPressData !== "undefined" ? TutorPressData.adminUrl : window.location.origin + "/wp-admin/";

      // Redirect to Gutenberg editor
      window.location.href = adminUrl + "post-new.php?post_type=courses";
    });
  }

  // Try overriding immediately
  overrideCreateCourseButton();
});
