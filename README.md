## Tutor LMS Template Override Plugin
This plugin overrides the custom template loader of the Tutor LMS plugin **specifically for the course archive page** and reverts it back to WordPress's default template hierarchy. By doing so, it enables full compatibility with theme features like **Blocksy's Content Blocks**, allowing you to design and customize the course archive page directly within the Gutenberg editor.
___

**Key Features**

+ **Course Archive Page Override:** Selectively disables Tutor LMS's custom template loader for the course archive (archive-course.php) to restore WordPress's default template behavior.

+ **Blocksy Integration:** Unlocks the power of Blocksy's Content Blocks for dynamic and visually rich template design, managed entirely from the WordPress admin interface.

+ **Modular and Extendable:** While focused on the course archive template, the plugin's structure allows for selective overrides of other Tutor LMS templates if needed. This design minimizes conflicts and ensures stability.

___

**Use Case**

This plugin is particularly valuable if:

1. You're using the **Blocksy theme** or another advanced theme with a template design system.
2. You want to design Tutor LMS templates dynamically using Gutenberg or other native WordPress tools.
3. You need fine-grained control over template overrides without completely disabling Tutor LMS's template handling.

___

**Notes**

+ This plugin **does not globally disable Tutor LMS's template loader**. It carefully targets the course archive page for override.
+ For future compatibility, additional templates can be overridden using the same structure while maintaining selective control.
