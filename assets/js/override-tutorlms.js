/**
 * TutorPress Frontend Dashboard Overrides
 *
 * Overrides Tutor LMS's "New Course" and "New Bundle" buttons in the frontend dashboard
 * to navigate directly to the Gutenberg editor, matching WordPress's native post creation flow.
 */
document.addEventListener("DOMContentLoaded", function () {
  // Check setting FIRST - before touching any buttons
  if (typeof TutorPressData === "undefined" || !TutorPressData.enableDashboardRedirects) {
    return;
  }

  var adminUrl = TutorPressData.adminUrl || window.location.origin + "/wp-admin/";

  // Override "Create A New Course" button
  // Uses clone-and-replace to remove Tutor's event listeners, then sets href for direct navigation
  var createCourseButton = document.querySelector(".tutor-header-right-side .tutor-create-new-course");
  if (createCourseButton) {
    var newCourseBtn = createCourseButton.cloneNode(true);
    // Remove Tutor's class to prevent their event delegation handler from firing
    newCourseBtn.classList.remove("tutor-create-new-course");
    // Set href for direct navigation (works for both <a> and <button> styled as links)
    newCourseBtn.setAttribute("href", adminUrl + "post-new.php?post_type=courses");
    // For <button> elements, add click handler to navigate
    if (newCourseBtn.tagName === "BUTTON") {
      newCourseBtn.addEventListener("click", function (e) {
        e.preventDefault();
        window.location.href = adminUrl + "post-new.php?post_type=courses";
      });
    }
    createCourseButton.parentNode.replaceChild(newCourseBtn, createCourseButton);
  }

  // Override "Create A New Bundle" button
  // Frontend bundle button is an <a> tag with data-source="frontend"
  var createBundleButton = document.querySelector("a.tutor-add-new-course-bundle[data-source='frontend']");
  if (createBundleButton) {
    var newBundleBtn = createBundleButton.cloneNode(true);
    // Remove Tutor's class to prevent their event delegation handler from firing
    newBundleBtn.classList.remove("tutor-add-new-course-bundle");
    // Set href for direct navigation
    newBundleBtn.setAttribute("href", adminUrl + "post-new.php?post_type=course-bundle");
    createBundleButton.parentNode.replaceChild(newBundleBtn, createBundleButton);
  }
});
