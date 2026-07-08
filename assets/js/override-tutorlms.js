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

  var courseCreateUrl = adminUrl + "post-new.php?post_type=courses";
  var bundleCreateUrl = adminUrl + "post-new.php?post_type=course-bundle";

  function addUniqueControl(controls, control) {
    if (control && controls.indexOf(control) === -1) {
      controls.push(control);
    }
  }

  function redirectControl(control, redirectUrl, classToRemove) {
    if (!control || !control.parentNode) {
      return;
    }

    var replacement = control.cloneNode(true);

    if (classToRemove) {
      replacement.classList.remove(classToRemove);
    }

    replacement.removeAttribute("@click");
    replacement.removeAttribute("x-on:click");

    if (replacement.tagName === "A") {
      replacement.setAttribute("href", redirectUrl);
    } else if (replacement.tagName === "BUTTON" && !replacement.hasAttribute("type")) {
      replacement.setAttribute("type", "button");
    }

    replacement.addEventListener("click", function (e) {
      e.preventDefault();
      window.location.href = redirectUrl;
    });

    control.parentNode.replaceChild(replacement, control);
  }

  function isTutorCreateCourseBinding(control) {
    var clickBinding = control.getAttribute("@click") || control.getAttribute("x-on:click") || "";

    return clickBinding.replace(/\s+/g, "") === "handleCreateCourse()";
  }

  // Override Tutor LMS course creation controls across legacy and Tutor LMS 4.0 dashboards.
  var courseControls = [];
  document.querySelectorAll(".tutor-header-right-side .tutor-create-new-course, .tutor-create-new-course").forEach(function (control) {
    addUniqueControl(courseControls, control);
  });

  document.querySelectorAll('[x-data="tutorMyCourses()"] button').forEach(function (control) {
    if (isTutorCreateCourseBinding(control)) {
      addUniqueControl(courseControls, control);
    }
  });

  courseControls.forEach(function (control) {
    redirectControl(control, courseCreateUrl, "tutor-create-new-course");
  });

  // Override Tutor LMS Pro bundle creation controls across anchor and button variants.
  document.querySelectorAll("a.tutor-add-new-course-bundle[data-source='frontend'], button.tutor-add-new-course-bundle[data-source='frontend']").forEach(function (control) {
    redirectControl(control, bundleCreateUrl, "tutor-add-new-course-bundle");
  });
});
