document.addEventListener("DOMContentLoaded", function () {
  // Check if the sidebar tabs feature is enabled before modifying lesson tabs
  if (typeof TutorPressData !== "undefined" && TutorPressData.enableSidebarTabs) {
    // Remove the tabs in the main content area of the lesson pages
    let tabsToRemove = ["[data-tutor-query-value='comments']", "[data-tutor-query-value='overview']"];
    tabsToRemove.forEach((selector) => {
      let tab = document.querySelector(selector);
      if (tab) {
        tab.remove();
      }
    });
  }

  // Function to override "Create A New Course" button
  function overrideCreateCourseButton() {
    if (typeof TutorPressData === "undefined" || !TutorPressData.enableDashboardRedirects) {
      return;
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
      event.preventDefault(); // Prevent default Tutor LMS behavior
      event.stopPropagation(); // Stop AJAX request

      // Kill any pending AJAX requests to prevent errors
      if (window.fetch) {
        window.fetch = function () {
          return new Promise(() => {}); // Return a fake promise to block AJAX
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
