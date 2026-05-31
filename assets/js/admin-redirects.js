/**
 * TutorPress Backend Admin Redirect Overrides
 *
 * Overrides Tutor LMS "New Course" / "New Bundle" buttons and edit links on the
 * Tutor LMS Courses admin list so they open the WordPress post editor (Gutenberg).
 *
 * Requires TutorPressData: enableAdminRedirects, adminUrl (localized from PHP).
 */
(function () {
  "use strict";

  function overrideBackendButtons() {
    var adminUrl = TutorPressData.adminUrl || "";

    var newCourseBtn = document.querySelector(
      "a.tutor-create-new-course:not(.tutor-dashboard-create-course), button.tutor-create-new-course:not(.tutor-dashboard-create-course)"
    );
    if (newCourseBtn) {
      var clonedBtn = newCourseBtn.cloneNode(true);
      clonedBtn.classList.remove("tutor-create-new-course");
      clonedBtn.setAttribute("href", adminUrl + "post-new.php?post_type=courses");
      if (newCourseBtn.parentNode) {
        newCourseBtn.parentNode.replaceChild(clonedBtn, newCourseBtn);
      }
    }

    var newBundleBtn = document.querySelector("a.tutor-add-new-course-bundle");
    if (newBundleBtn) {
      var clonedBundleBtn = newBundleBtn.cloneNode(true);
      clonedBundleBtn.classList.remove("tutor-add-new-course-bundle");
      clonedBundleBtn.setAttribute("href", adminUrl + "post-new.php?post_type=course-bundle");
      if (newBundleBtn.parentNode) {
        newBundleBtn.parentNode.replaceChild(clonedBundleBtn, newBundleBtn);
      }
    }
  }

  function overrideBackendEditLinks() {
    function rewriteAnchors(root) {
      root = root || document;
      var selector =
        'a[href*="admin.php?page=create-course&course_id="], a[href*="admin.php?page=course-bundle&action=edit&id="], .tutor-dropdown-item';

      Array.prototype.forEach.call(root.querySelectorAll(selector), function (item) {
        var anchor = null;

        if (item.tagName === "A") {
          anchor = item;
        } else if (item.querySelector) {
          anchor = item.querySelector("a");
        }

        if (!anchor) {
          return;
        }

        var href = anchor.getAttribute("href") || "";

        if (href.indexOf("admin.php?page=create-course&course_id=") !== -1) {
          var courseId = href.split("course_id=")[1];
          if (courseId) {
            courseId = courseId.split("#")[0];
            if (courseId) {
              anchor.setAttribute("href", "post.php?post=" + courseId + "&action=edit");
            }
          }
        }

        if (href.indexOf("admin.php?page=course-bundle&action=edit&id=") !== -1) {
          var bundleId = href.split("id=")[1];
          if (bundleId) {
            bundleId = bundleId.split("#")[0];
            if (bundleId) {
              anchor.setAttribute("href", "post.php?post=" + bundleId + "&action=edit");
            }
          }
        }
      });
    }

    rewriteAnchors(document);
    setTimeout(function () {
      rewriteAnchors(document);
    }, 500);

    if (typeof MutationObserver !== "undefined") {
      var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          if (!m.addedNodes || !m.addedNodes.length) {
            return;
          }
          Array.prototype.forEach.call(m.addedNodes, function (n) {
            if (n.nodeType === 1) {
              rewriteAnchors(n);
            }
          });
        });
      });
      observer.observe(document.body, { childList: true, subtree: true });
      setTimeout(function () {
        observer.disconnect();
      }, 10000);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (typeof TutorPressData === "undefined" || !TutorPressData.enableAdminRedirects) {
      return;
    }

    overrideBackendButtons();
    overrideBackendEditLinks();
  });
})();
