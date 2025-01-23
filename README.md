## Tutor LMS Advanced Customization Toolkit

A powerful integration tool for Tutor LMS that extends its functionality by enabling advanced template overrides, metadata storage, and seamless customization with WordPress-native tools. Designed with flexibility in mind, this toolkit is perfect for developers and site owners who want to leverage themes like **Blocksy** and advanced Gutenberg-based customization frameworks.

---

### **Key Features**

1. **Dynamic Template Override:**

   - Overrides the Tutor LMS custom template loader, specifically for the course archive page, to restore WordPress's default template hierarchy.
   - Allows themes like Blocksy to fully integrate with Tutor LMS templates, unlocking dynamic content design capabilities.

2. **Course Metadata Management:**

   - Automatically updates course metadata (e.g., average rating, rating count) when courses are saved or reviews are updated.
   - Leverages Tutor LMS’s built-in functionality to ensure metadata accuracy and consistency.

3. **Integration with Blocksy Content Blocks:**

   - Enables visual customization for the course archive page using Blocksy’s Content Blocks and the Gutenberg editor.

4. **Modular Design:**

   - Structured for selective template overrides, ensuring stability and compatibility with Tutor LMS updates.
   - Supports extending functionality to additional Tutor LMS templates and settings.

5. **Comment-Based Metadata Updates:**
   - Automatically updates metadata when reviews are approved or their status changes, ensuring course ratings reflect the latest feedback.

---

### **Use Cases**

This plugin is ideal if:

1. You’re using a theme like **Blocksy** or similar advanced frameworks with dynamic content design capabilities.
2. You want to design Tutor LMS templates directly within the WordPress admin interface, using tools like Gutenberg or Blocksy’s Content Blocks.
3. You need to extend Tutor LMS functionality with metadata management or additional dynamic template overrides.

---

### **New in Version 1.1.1**

1. **Course Metadata Storage:**

   - Adds and updates metadata fields (`tutor_course_rating_count` and `tutor_course_average_rating`) for courses dynamically.
   - Ensures seamless metadata recalculations during course save or review updates.

2. **Improved Template Override:**
   - Restores full compatibility with WordPress’s default template hierarchy for the course archive page.
   - Dynamically loads `archive-course.php` if available, falling back to `archive.php` as needed.

---

### **Notes**

- This plugin **targets specific templates** for override, ensuring compatibility and minimizing conflicts with Tutor LMS’s default behavior.
- Metadata updates are seamlessly integrated with Tutor LMS’s built-in review and course structures.
- Future updates will include extendable functionality for additional Tutor LMS backend pages like Orders, Students, and Reports.

---

### **Planned Features**

1. Granular access controls for backend pages such as Orders, Withdraw Requests, and Reports.
2. Role-based filtering for dynamic data visibility (e.g., ensuring instructors only see their associated data).
3. Extendable metadata storage for custom fields and additional data types.

---

### **Requirements**

- WordPress 5.8 or higher.
- Tutor LMS (latest version recommended).
- Compatible with themes like Blocksy and GreenShift for advanced design integration.

---

### **Installation**

1. Upload the plugin folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. The plugin will automatically initialize its custom template loader and metadata management functionality.

---

This plugin is designed and maintained by Brandon Meyer, providing a flexible and powerful toolkit for advanced Tutor LMS customizations.
