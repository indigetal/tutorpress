wp.domReady(() => {
  const { createElement: el } = wp.element;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { select, subscribe, dispatch } = wp.data;

  // Register the sidebar once post type is available
  const registerSidebar = () => {
    if (select("core/editor").getCurrentPostType() !== "courses") {
      return;
    }

    wp.plugins.registerPlugin("tutor-course-details-sidebar", {
      render: () =>
        el(
          PluginDocumentSettingPanel,
          {
            name: "tutor-course-details",
            title: "Course Details",
            className: "tutor-course-details-sidebar",
            initialOpen: true,
            order: 5,
          },
          el("p", {}, "This section will contain course details settings.")
        ),
      icon: "admin-generic",
    });

    // Open the document sidebar
    setTimeout(() => dispatch("core/edit-post").openGeneralSidebar("edit-post/document"), 1000);
  };

  // Wait for the post type to be available
  const unsubscribe = subscribe(() => {
    if (select("core/editor").getCurrentPostType()) {
      registerSidebar();
      unsubscribe();
    }
  });
});
