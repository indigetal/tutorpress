wp.domReady(() => {
  const { createElement: el } = wp.element;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { select, subscribe, dispatch } = wp.data;

  const registerSidebars = () => {
    if (select("core/editor").getCurrentPostType() !== "courses") {
      return;
    }

    const existingPlugins = wp.plugins.getPlugins().map((plugin) => plugin.name);

    const sections = [
      { name: "tutor-course-details", title: "Course Details", className: "tutor-course-details-sidebar", order: 5 },
      {
        name: "tutor-access-enrollment",
        title: "Access & Enrollment",
        className: "tutor-access-enrollment-sidebar",
        order: 6,
      },
      { name: "tutor-course-media", title: "Course Media", className: "tutor-course-media-sidebar", order: 7 },
      { name: "tutor-live-meetings", title: "Live Meetings", className: "tutor-live-meetings-sidebar", order: 8 },
      {
        name: "tutor-course-instructors",
        title: "Instructors",
        className: "tutor-course-instructors-sidebar",
        order: 9,
      },
    ];

    sections.forEach(({ name, title, className, order }) => {
      if (!existingPlugins.includes(name)) {
        wp.plugins.registerPlugin(name, {
          render: () =>
            el(
              PluginDocumentSettingPanel,
              { name, title, className, initialOpen: false, order },
              el("p", {}, `This section will contain ${title.toLowerCase()} settings.`)
            ),
          icon: "admin-generic",
        });
      }
    });

    // 🚀 Ensure the document sidebar opens only if it is not already open
    if (!select("core/edit-post").isEditorSidebarOpened()) {
      dispatch("core/edit-post").openGeneralSidebar("edit-post/document");
    }
  };

  const unsubscribe = subscribe(() => {
    if (select("core/editor").getCurrentPostType()) {
      registerSidebars();
      unsubscribe();
    }
  });
});
