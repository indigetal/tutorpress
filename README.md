# TutorPress v2.0 — Premium Gutenberg Course Builder for Tutor LMS

**TutorPress** is a premium WordPress plugin that modernizes Tutor LMS by replacing the legacy frontend course builder with a comprehensive Gutenberg-native editing experience. Built with modern WordPress architecture, REST API endpoints, and TypeScript, TutorPress restores WordPress best practices while extending Tutor LMS functionality.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![Tutor LMS](https://img.shields.io/badge/Tutor%20LMS-Compatible-green.svg)](https://www.themeum.com/product/tutor-lms/)
[![Premium](https://img.shields.io/badge/License-Premium-gold.svg)](https://indigetal.com/tutorpress)

---

## 🚀 **What TutorPress Delivers**

### **Gutenberg-First Course Builder**

TutorPress replaces Tutor LMS's frontend course builder with native Gutenberg editing. Course creators work within the familiar WordPress editor enhanced with specialized course management tools.

### **Modern WordPress Architecture**

- **REST API Native**: Every endpoint follows WordPress standards with proper schema validation
- **TypeScript Integration**: Complete type safety from frontend to API contracts
- **WordPress Data Stores**: Centralized state management using `@wordpress/data`
- **Freemius Integration**: Secure premium feature gating with 14-day free trial

---

## 🏗️ **Current Features (v2.0)**

### **Core WordPress Integrations**

✅ **Restored Comment System**

- Reinstates WordPress comment system on Tutor LMS lesson pages
- Implements tabbed sidebar navigation for enhanced lesson interaction
- Maintains compatibility with popular comment plugins

✅ **Gutenberg Editor Redirects**

- Redirects course edit buttons to Gutenberg editor (backend and frontend)
- Replaces Tutor LMS's frontend course builder workflow
- Streamlines course creation for instructors

✅ **WordPress Template Hierarchy**

- Restores standard WordPress template hierarchy for Course Archive pages
- Enables theme customization using child themes and template overrides
- Compatible with advanced themes like Blocksy for dynamic content design

✅ **Enhanced Frontend Dashboard**

- Adds media library access for instructors
- Includes H5P editor links when H5P plugin is active
- Improves instructor workflow efficiency

✅ **Automated Metadata Management**

- Updates `tutor_course_rating_count` and `tutor_course_average_rating`
- Triggers on course saves and review updates
- Replaces Tutor LMS's custom metadata functions with WordPress standards

### **Premium Course Builder Features**

✅ **Course Curriculum Metabox**

- Interactive curriculum builder within Gutenberg editor
- Available for both Course and Lesson post types
- Displays parent Course curriculum in Lesson editor
- Drag-and-drop content organization with visual feedback

✅ **Advanced Quiz Builder**

- Modern quiz creation modal with comprehensive question types
- Support for all 11 Tutor LMS question types (Multiple Choice, True/False, Fill in the Blanks, etc.)
- Enhanced quiz settings management
- Proper validation and error handling

✅ **Interactive Quiz Integration**

- H5P content selection and integration
- Enhanced preview capabilities beyond Tutor LMS shortcode display
- Seamless workflow for interactive content creation

✅ **Course Settings Panels**

- **Course Details Panel**: Course information management in Gutenberg sidebar
- **Pricing Model Panel**: Free/Paid options with WooCommerce integration
- **Assignment Settings Panel**: Complete assignment configuration
- **Lesson Settings Panel**: Video detection, exercise files, preview settings

✅ **Payment Engine Integration**

- **WooCommerce Support**: Product linking and price synchronization
- **Native Tutor Monetization**: Support for built-in payment system
- **Multiple Payment Engines**: Detection and integration for various payment systems
- **Pricing Panel**: Conditional display based on active monetization engine

✅ **Additional & Certificate Metaboxes**

- Replicates Tutor LMS "Additional" tab functionality
- Separate Certificate metabox for course completion certificates
- Content Drip settings integration

### **Technical Architecture**

✅ **WordPress Data Store Integration**

- Curriculum store for content management
- Course settings store for configuration data
- Optimized selectors and state management
- Type-safe state handling with TypeScript

✅ **REST API Endpoints**

- `/tutorpress/v1/topics` - Topic CRUD operations
- `/tutorpress/v1/courses/{id}/settings` - Course settings management
- `/tutorpress/v1/h5p/contents` - H5P content integration
- `/tutorpress/v1/woocommerce/products` - WooCommerce product management
- Full WordPress REST API compliance with proper validation

✅ **Drag-and-Drop System**

- Shared utilities for consistent drag behavior
- Support for topics, quiz questions, and question options
- Visual feedback with blue overlays and drop indicators
- Smooth animations and cursor-following transforms

---

## 🎯 **Target Users**

### **Course Creators & Educators**

- Prefer Gutenberg editor over frontend course builders
- Need efficient course content organization tools
- Want modern quiz creation capabilities
- Require professional course management workflow

### **WordPress Developers & Theme Authors**

- Want to customize Tutor LMS using WordPress template hierarchy
- Need REST API endpoints for custom integrations
- Require modern WordPress development patterns
- Building custom LMS solutions for clients

### **Site Administrators**

- Managing WordPress sites with Tutor LMS
- Need improved compatibility with WordPress ecosystem
- Want enhanced performance and security patterns
- Require reliable metadata and rating management

---

## 💰 **Pricing & Licensing**

### **Premium-Only with Free Trial**

TutorPress v2.0 is premium-only with a **14-day free trial** via Freemius:

- **Full Feature Access**: All features available during trial
- **No Credit Card Required**: Start trial immediately
- **Automatic Trial Management**: Managed through Freemius SDK
- **Upgrade Options**: Multiple licensing tiers available

### **Feature Gating**

- **UI Content Swapping**: Premium panels show promo content when trial/license expires
- **Server-Side Enforcement**: Premium settings blocked at API level for security
- **Graceful Degradation**: Core WordPress integrations remain functional

---

## 🛠️ **Technical Requirements**

### **Minimum Requirements**

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Tutor LMS**: Latest version recommended
- **Browser**: Modern browser with JavaScript enabled

### **Recommended Environment**

- **WordPress**: 6.0+ for optimal Gutenberg support
- **PHP**: 8.0+ for enhanced performance
- **Memory**: 256MB+ PHP memory limit
- **Themes**: Compatible with most WordPress themes

---

## 📦 **Installation & Setup**

### **Installation Process**

1. **Download**: Get TutorPress from your purchase confirmation
2. **Upload**: Install via WordPress admin Plugins → Add New → Upload
3. **Activate**: Enable through WordPress Plugins menu
4. **License**: Activate license or start 14-day free trial
5. **Settings**: Configure options under Tutor LMS → TutorPress

### **Important Setup Notes**

1. **Backup First**: Always backup your site before installation
2. **Test Environment**: Test workflow changes on staging site
3. **Course Editing**: Edit links now redirect to Gutenberg (workflow change)
4. **Theme Compatibility**: Test with your current theme

---

## 🔧 **Key Workflow Changes**

### **For Course Creators**

- **Course Editing**: Click "Edit Course" → Opens in Gutenberg (not frontend builder)
- **Curriculum Management**: Use Course Curriculum metabox in Gutenberg
- **Quiz Creation**: Create quizzes via modern modal interface
- **Settings Management**: Configure course settings in Gutenberg sidebar panels

### **For Site Visitors**

- **Course Archives**: Now use standard WordPress template hierarchy
- **Lesson Comments**: WordPress comment system restored on lesson pages
- **Course Ratings**: Accurate metadata automatically maintained

### **For Developers**

- **Template Overrides**: Use standard WordPress template hierarchy
- **REST API**: Modern endpoints available for custom integrations
- **Meta Fields**: Standard WordPress meta field patterns

---

## 🚀 **Performance & Security**

### **Modern WordPress Standards**

- **REST API Architecture**: Replaces legacy AJAX patterns
- **WordPress Caching**: Leverages built-in caching mechanisms
- **Permission System**: Uses WordPress capability system
- **Data Validation**: Comprehensive schema validation for all endpoints

### **Performance Optimizations**

- **TypeScript Compilation**: Optimized bundle size (~274 KiB)
- **Efficient State Management**: WordPress Data Store patterns
- **Smart Caching**: Transient caching for feature detection
- **Optimistic Updates**: Instant UI feedback for better UX

---

## 🔍 **Troubleshooting**

### **Common Issues**

- **Premium Panel Shows Promo**: Check license status and Freemius cache
- **Course Edit Redirects**: Expected behavior - now opens in Gutenberg
- **Missing Features**: Ensure license is active and trial hasn't expired
- **Theme Conflicts**: Test with default WordPress theme

### **Debug Information**

- **WordPress Debug**: Enable `WP_DEBUG` for detailed logging
- **Browser Console**: Check for JavaScript errors in dev tools
- **Network Tab**: Monitor API requests for error responses

---

## 📋 **Compatibility**

### **Tested With**

- **WordPress**: 5.8 - 6.4+
- **Tutor LMS**: Latest stable versions
- **PHP**: 7.4 - 8.2
- **Popular Themes**: Blocksy, Astra, GeneratePress, and more

### **Plugin Integrations**

- **H5P**: Enhanced interactive content support
- **WooCommerce**: Product linking and price synchronization
- **Comment Plugins**: Restored WordPress comment compatibility
- **Caching Plugins**: Compatible with major caching solutions

---

## 🎉 **What's New in v2.0**

### **Major Release Changes**

- **Premium-Only Model**: Complete feature set with 14-day trial
- **Enhanced Gutenberg Integration**: Full course builder in WordPress editor
- **Advanced Quiz System**: Modern quiz creation with comprehensive question types
- **Payment Engine Support**: WooCommerce and native monetization integration
- **Freemius Integration**: Secure licensing and feature gating

### **Breaking Changes from Previous Versions**

- **No Free Version**: All features now require license or active trial
- **Workflow Changes**: Course editing workflow significantly different
- **UI Changes**: Premium features show promotional content when locked

---

**TutorPress v2.0** transforms Tutor LMS into a modern, WordPress-native course management system that course creators and developers will love.

_Developed by [Indigetal WebCraft](https://indigetal.com) — Modernizing WordPress LMS Solutions_
