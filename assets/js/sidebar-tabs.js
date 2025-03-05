document.addEventListener("DOMContentLoaded", function () {
  let tabs = document.querySelectorAll(".tutorpress-tab");
  let contents = document.querySelectorAll(".tutorpress-tab-content");
  let sidebar = document.querySelector(".tutor-course-single-content-wrapper");
  let sidebarClose = document.querySelector(".tutor-hide-course-single-sidebar");
  let sidebarToggle = document.querySelector("[tutor-course-topics-sidebar-toggler]");
  let commentBubble = document.getElementById("wpd-bubble-wrapper");

  // Function to update comment bubble visibility
  function updateCommentBubble() {
    if (!commentBubble || !sidebar) return;
    commentBubble.style.display = window.innerWidth < 1200 ? "block" : "none";
  }

  // Ensure close button works
  if (sidebarClose) {
    sidebarClose.addEventListener("click", function () {
      sidebar.classList.remove("tutor-course-single-sidebar-open"); // Close sidebar
      document.body.classList.remove("tutor-overflow-hidden"); // Enable scrolling again
    });
  }

  // Ensure only Discussion is hidden on page load (even if dynamically modified)
  let discussionTab = document.getElementById("discussion");
  if (discussionTab) {
    discussionTab.style.display = "none";
  }

  let courseContent = document.getElementById("course-content");
  if (courseContent) {
    courseContent.style.display = "block";
  }

  tabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      tabs.forEach((t) => t.classList.remove("active"));
      contents.forEach((c) => (c.style.display = "none"));

      this.classList.add("active");
      let targetTab = document.getElementById(this.dataset.tab);
      if (targetTab) {
        targetTab.style.display = "block";
      }
    });
  });

  // Monitor sidebar toggle button
  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", function () {
      setTimeout(updateCommentBubble, 300);
    });
  }

  // Ensure clicking the comment bubble opens the sidebar with Discussion tab
  if (commentBubble) {
    commentBubble.addEventListener("click", function () {
      sidebar.classList.add("tutor-course-single-sidebar-open");
      document.body.classList.add("tutor-overflow-hidden"); // Prevent scrolling
      tabs.forEach((t) => t.classList.remove("active"));
      contents.forEach((c) => (c.style.display = "none"));
      let discussionTabButton = document.querySelector(".tutorpress-tab[data-tab='discussion']");
      if (discussionTabButton) discussionTabButton.classList.add("active");
      if (discussionTab) discussionTab.style.display = "block";
    });
  }

  // Detect screen width auto-collapse in Tutor LMS
  function checkSidebarAutoCollapse() {
    if (window.innerWidth < 1200) {
      sidebar.classList.remove("tutor-course-single-sidebar-open");
      document.body.classList.remove("tutor-overflow-hidden");
    }
    updateCommentBubble();
  }

  window.addEventListener("resize", checkSidebarAutoCollapse);

  // Fix: Ensure correct comment bubble visibility on initial page load
  setTimeout(updateCommentBubble, 500);

  // Remove the wpdiscuz_hidden_secondary_form element
  var hiddenForm = document.getElementById("wpdiscuz_hidden_secondary_form");
  if (hiddenForm) {
    hiddenForm.remove();
  }
});
