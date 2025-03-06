document.addEventListener("DOMContentLoaded", function () {
  console.log("TutorPress: override-tutorlms.js loaded");

  // Check if the sidebar tabs feature is enabled before modifying lesson tabs
  if (typeof TutorPressData !== "undefined" && TutorPressData.enableSidebarTabs) {
    console.log("TutorPress: Sidebar tabs feature is enabled");

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
      console.log("TutorPress: Dashboard course editing redirect is disabled. No changes made.");
      return;
    }

    let createCourseButton = document.querySelector(".tutor-dashboard-create-course");

    if (!createCourseButton) {
      console.warn("TutorPress: 'Create A New Course' button NOT found. No further action taken.");
      return; // If button is missing, do nothing
    }

    console.log("TutorPress: Found 'Create A New Course' button");

    // Remove existing event listeners added by Tutor LMS
    let newButton = createCourseButton.cloneNode(true);
    createCourseButton.parentNode.replaceChild(newButton, createCourseButton);
    createCourseButton = newButton;

    // Add our custom click event
    createCourseButton.addEventListener("click", function (event) {
      event.preventDefault(); // Prevent default Tutor LMS behavior
      event.stopPropagation(); // Stop AJAX request

      console.log("TutorPress: Redirecting directly to Gutenberg");

      // Kill any pending AJAX requests to prevent errors
      if (window.fetch) {
        window.fetch = function () {
          console.log("TutorPress: AJAX request blocked");
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
