/**
 * TutorPress Override Utilities
 *
 * This file contains utilities for overriding Tutor LMS's default behaviors.
 * Each section is clearly documented with its purpose and implementation details.
 */

// Make this a module
export {};

interface TutorPressSettings {
  enableDashboardRedirects?: boolean;
  enableAdminRedirects?: boolean;
  adminUrl?: string;
}

// Declare global types in module scope
declare global {
  interface Window {
    TutorPressData?: TutorPressSettings;
    _tutorobject?: {
      nonce: string;
    };
    ajaxurl?: string;
    jQuery?: any;
  }
}

/**
 * ============================================================================
 * Frontend (Dashboard) Overrides
 * ============================================================================
 *
 * These functions override Tutor LMS's default behaviors in the frontend dashboard:
 * - Edit icons in course cards
 */

/**
 * Override frontend edit icons in course cards
 */
function overrideFrontendEditIcons(): void {
  document.querySelectorAll(".tutor-my-course-edit").forEach((item) => {
    const href = item.getAttribute("href");
    if (!href) return;

    // Course edit links are now handled by PHP filter 'tutor_dashboard_course_list_edit_link'
    // This section can be removed as the URLs are corrected at the source

    // Bundle edit link override
    if (href.includes("dashboard/create-bundle?action=edit&id=")) {
      const bundleId = href.split("id=")[1].split("#")[0];
      if (bundleId) {
        item.setAttribute("href", "post.php?post=" + bundleId + "&action=edit");
      }
    }
  });
}

/**
 * ============================================================================
 * Backend (WP Admin) Overrides
 * ============================================================================
 *
 * These functions override Tutor LMS's default behaviors in the WordPress admin:
 * - "New Course" and "New Bundle" buttons in Courses list
 * - Edit links in course/bundle dropdown menus
 */

/**
 * Override backend "New Course" and "New Bundle" buttons
 */
function overrideBackendButtons(): void {
  // Override "New Course" button - only target backend buttons (not frontend dashboard)
  // Backend button has additional classes like tutor-d-flex, tutor-align-center, tutor-gap-1
  // Frontend button has tutor-dashboard-create-course class
  const newCourseBtn = document.querySelector(
    "a.tutor-create-new-course:not(.tutor-dashboard-create-course), button.tutor-create-new-course:not(.tutor-dashboard-create-course)"
  );
  if (newCourseBtn) {
    // Clone the button without event listeners
    const clonedBtn = newCourseBtn.cloneNode(true) as HTMLElement;
    // Remove Tutors class to prevent their handler
    clonedBtn.classList.remove("tutor-create-new-course");
    clonedBtn.setAttribute("href", "#");
    // Add our click handler
    clonedBtn.onclick = function (e) {
      e.preventDefault();
      e.stopPropagation();
      this.onclick = null;

      // Use jQuery for AJAX to match Tutor's expectations
      window.jQuery
        .post(window.ajaxurl, {
          action: "tutor_create_new_draft_course",
          source: "backend",
          _wpnonce: window._tutorobject?.nonce,
        })
        .done(function (response: any) {
          const data = typeof response === "string" ? JSON.parse(response) : response;
          if (data?.data && typeof data.data === "string") {
            // Extract course ID from create-course URL and redirect to Gutenberg
            const urlParams = new URLSearchParams(data.data.split("?")[1]);
            const courseId = urlParams.get("course_id");
            if (courseId) {
              window.location.href = "post.php?post=" + courseId + "&action=edit";
            } else {
              alert("Could not extract course ID from response");
            }
          } else {
            alert("Course Creation Failed " + JSON.stringify(data));
          }
        })
        .fail(function (xhr: any) {
          alert("Course Creation Failed: " + xhr.responseText);
        });
    };
    // Replace the original button with our clone
    newCourseBtn.parentNode?.replaceChild(clonedBtn, newCourseBtn);
  }

  // Override "New Bundle" button
  const newBundleBtn = document.querySelector("a.tutor-add-new-course-bundle");
  if (newBundleBtn) {
    const clonedBundleBtn = newBundleBtn.cloneNode(true) as HTMLElement;
    clonedBundleBtn.setAttribute("href", "#");
    clonedBundleBtn.onclick = function (e) {
      e.preventDefault();
      e.stopPropagation();
      this.onclick = null;

      // Use jQuery for AJAX to match Tutor's expectations
      window.jQuery
        .post(window.ajaxurl, {
          action: "tutor_create_course_bundle",
          source: "backend",
          _wpnonce: window._tutorobject?.nonce,
        })
        .done(function (response: any) {
          const data = typeof response === "string" ? JSON.parse(response) : response;
          if (data?.status_code === 200 && data?.data) {
            window.location.href = data.data;
          } else {
            alert("Bundle Creation Failed " + JSON.stringify(data));
          }
        })
        .fail(function (xhr: any) {
          alert("Bundle Creation Failed: " + xhr.responseText);
        });
    };
    newBundleBtn.parentNode?.replaceChild(clonedBundleBtn, newBundleBtn);
  }
}

/**
 * Override backend edit links in dropdown menus
 */
function overrideBackendEditLinks(): void {
  /**
   * Rewrite any anchors that point to Tutor LMS's legacy create-course / course-bundle
   * admin pages so they open in the WP post editor (Gutenberg).
   *
   * This targets:
   * - Anchors with href containing `admin.php?page=create-course&course_id=` (courses)
   * - Anchors with href containing `admin.php?page=course-bundle&action=edit&id=` (bundles)
   * - Existing `.tutor-dropdown-item` items (keeps backward compatibility)
   */
  function rewriteAnchors(root: ParentNode = document): void {
    const selector =
      'a[href*="admin.php?page=create-course&course_id="], a[href*="admin.php?page=course-bundle&action=edit&id="], .tutor-dropdown-item';

    Array.from(root.querySelectorAll<HTMLElement>(selector)).forEach((item) => {
      let anchor: HTMLAnchorElement | null = null;

      if (item instanceof HTMLAnchorElement) {
        anchor = item;
      } else if (item.querySelector) {
        anchor = item.querySelector("a");
      }

      if (!anchor) return;

      const href = anchor.getAttribute("href") || "";

      // Course edit link override
      if (href.includes("admin.php?page=create-course&course_id=")) {
        const courseId = href.split("course_id=")[1]?.split("#")[0];
        if (courseId) {
          anchor.setAttribute("href", "post.php?post=" + courseId + "&action=edit");
        }
      }

      // Bundle edit link override
      if (href.includes("admin.php?page=course-bundle&action=edit&id=")) {
        const bundleId = href.split("id=")[1]?.split("#")[0];
        if (bundleId) {
          anchor.setAttribute("href", "post.php?post=" + bundleId + "&action=edit");
        }
      }
    });
  }

  // Run immediately and again shortly after to catch elements rendered asynchronously
  rewriteAnchors();
  setTimeout(() => rewriteAnchors(document), 500);

  // Use a short-lived MutationObserver to catch dynamic updates (disconnect after 10s)
  if (typeof MutationObserver !== "undefined") {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((m) => {
        if (m.addedNodes && m.addedNodes.length) {
          m.addedNodes.forEach((n) => {
            if (n instanceof Element) {
              rewriteAnchors(n);
            }
          });
        }
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
    setTimeout(() => observer.disconnect(), 10000);
  }
}

/**
 * ============================================================================
 * Initialization
 * ============================================================================
 */

// Initialize when DOM ready
document.addEventListener("DOMContentLoaded", function () {
  // Backend overrides
  if (typeof window.TutorPressData !== "undefined" && window.TutorPressData.enableAdminRedirects) {
    overrideBackendButtons();
    overrideBackendEditLinks();
  }

  // Frontend edit icons
  if (typeof window.TutorPressData !== "undefined" && window.TutorPressData.enableDashboardRedirects) {
    overrideFrontendEditIcons();
  }
});
