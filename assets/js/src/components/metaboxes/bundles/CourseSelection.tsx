/**
 * CourseSelection.tsx
 *
 * Main component for displaying and managing course selection in the Bundle Course Metabox.
 * This component handles the entire course selection section including:
 * - Course list display with drag/drop functionality and action buttons
 * - Course selection modal integration
 * - Responsive button layout with proper state management
 *
 * Key Features:
 * - Drag and drop reordering via @dnd-kit using useSortableList hook
 * - Integration with WordPress Data Store for all operations
 * - Course selection modal for adding new courses
 * - Responsive design with WordPress admin styling consistency
 * - Form validation and user feedback via WordPress notices
 *
 * @package TutorPress
 * @subpackage Course Bundles/Components
 * @since 1.0.0
 */

import React, { useState, useEffect } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock, Spinner, Notice } from "@wordpress/components";
import { dragHandle, plus, close } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";
import { DndContext, DragOverlay } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { useSelect, useDispatch } from "@wordpress/data";
import { store as noticesStore } from "@wordpress/notices";
import type { AvailableCourse, BundlePricing } from "../../../types/bundle";
import { CourseSelectionModal } from "../../modals/bundles/CourseSelectionModal";
import { useSortableList } from "../../../hooks/common/useSortableList";
import { useError } from "../../../hooks/useError";
import { useBundleMeta } from "../../../hooks/common";

const COURSE_BUNDLES_STORE = "tutorpress/course-bundles";

/**
 * Extract numeric price from course price string
 * Handles formats like "$99.99", "Free", "$0", and HTML formatted prices
 */
const extractNumericPrice = (priceString: string): number => {
  if (!priceString || priceString.toLowerCase() === "free") {
    return 0;
  }

  // Handle HTML formatted prices (from bundle courses API)
  if (priceString.includes("<span")) {
    // Extract regular price from HTML (always use regular price for bundle calculation)
    const regularPriceMatch = priceString.match(/tutor-course-price-regular[^>]*>\$([\d.]+)/);
    if (regularPriceMatch) {
      return parseFloat(regularPriceMatch[1]);
    }
  }

  // Extract numeric value from strings like "$99.99"
  const match = priceString.match(/[\d.]+/);
  return match ? parseFloat(match[0]) : 0;
};

/**
 * Check if removing a course would create an invalid pricing state
 */
const checkPricingValidation = (
  currentCourses: AvailableCourse[],
  courseToRemove: AvailableCourse,
  currentSalePrice: number
): { isValid: boolean; newRegularPrice: number; message?: string } => {
  // Calculate current regular price
  const currentRegularPrice = currentCourses.reduce((sum, course) => {
    return sum + extractNumericPrice(course.price || "");
  }, 0);

  // Calculate new regular price after removal
  const coursePrice = extractNumericPrice(courseToRemove.price || "");
  const newRegularPrice = currentRegularPrice - coursePrice;

  // Check if sale price would exceed new regular price
  if (currentSalePrice > newRegularPrice) {
    return {
      isValid: false,
      newRegularPrice,
      message: `Removing this course would reduce the total value to $${newRegularPrice.toFixed(2)}, which is less than the current bundle price of $${currentSalePrice.toFixed(2)}. Please adjust the bundle price first.`,
    };
  }

  return {
    isValid: true,
    newRegularPrice,
  };
};

interface BundleCourseSelectionProps {
  bundleId?: number;
}

interface CourseItemProps {
  course: AvailableCourse;
  index: number;
  onRemove: () => void;
  dragHandleProps?: any;
  className?: string;
  style?: React.CSSProperties;
}

/**
 * Renders a single course item in topic-like structure
 */
const CourseItem: React.FC<CourseItemProps> = ({
  course,
  index,
  onRemove,
  dragHandleProps,
  className,
  style,
}): JSX.Element => (
  <Card className={`tutorpress-topic ${className || ""}`} style={style}>
    <CardHeader className="tutorpress-topic-header">
      <Flex align="center" gap={2}>
        {/* Course index and drag handle */}
        <div className="tutorpress-topic-icon tpress-flex-shrink-0">
          <span className="course-number">{index + 1}</span>
          <Button icon={dragHandle} label={__("Drag to reorder", "tutorpress")} isSmall {...dragHandleProps} />
        </div>

        {/* Course thumbnail */}
        <div className="tutorpress-course-thumbnail">
          {course.featured_image ? (
            <img src={course.featured_image} alt={course.title} width="40" height="40" />
          ) : (
            <div className="tutorpress-course-thumbnail-placeholder">{course.title.charAt(0).toUpperCase()}</div>
          )}
        </div>

        {/* Course title */}
        <FlexBlock style={{ textAlign: "left" }}>
          <div className="tutorpress-topic-title">{course.title}</div>
        </FlexBlock>

        {/* Course price and delete button */}
        <div className="tpress-item-actions-right">
          <span
            className="course-price"
            dangerouslySetInnerHTML={{ __html: course.price || __("Free", "tutorpress") }}
          />
          <Button
            icon={close}
            label={__("Remove course", "tutorpress")}
            isSmall
            className="delete-button"
            onClick={onRemove}
          />
        </div>
      </Flex>
    </CardHeader>
  </Card>
);

/**
 * Sortable wrapper for course items
 */
const SortableCourseItem: React.FC<CourseItemProps> = (props): JSX.Element => {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: props.course.id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    ...props.style,
  };

  return (
    <div ref={setNodeRef} style={style}>
      <CourseItem
        {...props}
        dragHandleProps={{
          ...attributes,
          ...listeners,
          ref: setActivatorNodeRef,
        }}
        className={isDragging ? "tutorpress-topic--dragging" : ""}
      />
    </div>
  );
};

/**
 * Main Bundle Course Selection component
 */
export const BundleCourseSelection: React.FC<BundleCourseSelectionProps> = ({ bundleId }): JSX.Element => {
  // State management
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [localCourses, setLocalCourses] = useState<AvailableCourse[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  // Store dispatch
  const { getBundleCourses, updateBundleCourses } = useDispatch(COURSE_BUNDLES_STORE);

  // Get bundle pricing data for validation (entity-based)
  const { meta } = useBundleMeta();
  const pricingData = {
    sale_price: (meta?.tutor_course_sale_price as number) || 0,
  };

  // We load courses via loadBundleCourses into local state

  // Get notice actions
  const { createNotice } = useDispatch(noticesStore);

  // Drag and drop functionality
  const {
    sensors: courseSensors,
    dragHandlers,
    dragState,
  } = useSortableList({
    items: localCourses,
    onReorder: async (newOrder) => {
      if (!bundleId) return { success: false };

      try {
        await updateBundleCourses(
          bundleId,
          newOrder.map((course) => course.id)
        );
        setLocalCourses(newOrder);
        return { success: true };
      } catch (error) {
        console.error("Error updating bundle courses:", error);
        return { success: false, error: { code: "update_failed", message: String(error) } };
      }
    },
    persistenceMode: "api",
    context: "topics",
  });

  // Load bundle courses on mount
  const loadBundleCourses = async () => {
    if (!bundleId) return;

    try {
      setIsLoading(true);
      const response = await getBundleCourses(bundleId);
      if (response && response.data) {
        setLocalCourses(response.data);
      }
    } catch (error) {
      console.error("Error loading bundle courses:", error);
    } finally {
      setIsLoading(false);
    }
  };

  // Handle course removal
  const handleRemoveCourse = async (courseId: number) => {
    if (!bundleId) return;

    // Find the course to be removed
    const courseToRemove = localCourses.find((course) => course.id === courseId);
    if (!courseToRemove) {
      createNotice("error", __("Course not found in bundle.", "tutorpress"), {
        type: "snackbar",
      });
      return;
    }

    // Check pricing validation before removal
    if (pricingData && pricingData.sale_price > 0) {
      const validation = checkPricingValidation(
        localCourses,
        courseToRemove,
        pricingData.sale_price
      );

      if (!validation.isValid) {
        createNotice("error", validation.message || __("Cannot remove course due to pricing conflict.", "tutorpress"), {
          type: "snackbar",
        });
        return;
      }
    }

    try {
      const updatedCourses = localCourses.filter((course) => course.id !== courseId);
      await updateBundleCourses(
        bundleId,
        updatedCourses.map((course) => course.id)
      );
      setLocalCourses(updatedCourses);

      // Dispatch custom event to notify other components of course changes
      window.dispatchEvent(
        new CustomEvent("tutorpress-bundle-courses-updated", {
          detail: { bundleId, courseIds: updatedCourses.map((course) => course.id) },
        })
      );

      createNotice("success", __("Course removed from bundle successfully.", "tutorpress"), {
        type: "snackbar",
      });
    } catch (error) {
      console.error("Error removing course:", error);
      createNotice("error", __("Failed to remove course from bundle.", "tutorpress"), {
        type: "snackbar",
      });
    }
  };

  // Handle adding courses
  const handleAddCourses = async (courseIds: number[]) => {
    if (!bundleId) return;

    try {
      const existingCourseIds = localCourses.map((course) => course.id);
      const newCourseIds = courseIds.filter((id) => !existingCourseIds.includes(id));

      if (newCourseIds.length === 0) {
        createNotice("warning", __("All selected courses are already in the bundle.", "tutorpress"), {
          type: "snackbar",
        });
        return;
      }

      const updatedCourseIds = [...existingCourseIds, ...newCourseIds];
      await updateBundleCourses(bundleId, updatedCourseIds);

      // Reload courses to get full course data
      await loadBundleCourses();

      // Dispatch custom event to notify other components of course changes
      window.dispatchEvent(
        new CustomEvent("tutorpress-bundle-courses-updated", {
          detail: { bundleId, courseIds: updatedCourseIds },
        })
      );

      createNotice("success", __("Courses added to bundle successfully.", "tutorpress"), {
        type: "snackbar",
      });
    } catch (error) {
      console.error("Error adding courses:", error);
      createNotice("error", __("Failed to add courses to bundle.", "tutorpress"), {
        type: "snackbar",
      });
    }
  };

  // Calculate summary statistics
  const calculateSummary = () => {
    const totalDuration = localCourses.reduce((sum, course) => sum + (Number(course.duration) || 0), 0);
    const totalQuizzes = localCourses.reduce((sum, course) => sum + (course.quiz_count || 0), 0);
    const totalLessons = localCourses.reduce((sum, course) => sum + (course.lesson_count || 0), 0);
    const totalResources = localCourses.reduce((sum, course) => sum + (course.resource_count || 0), 0);

    return {
      totalDuration,
      totalQuizzes,
      totalLessons,
      totalResources,
    };
  };

  // Load courses on mount
  useEffect(() => {
    if (bundleId) {
      loadBundleCourses();
    }
  }, [bundleId]);

  const summary = calculateSummary();

  return (
    <div className="tutorpress-bundle-course-selection">
      {/* Error Display - not using store error */}

      {/* Course Count and Add Button - at the top */}
      {!isLoading && (
        <div className="tutorpress-bundle-course-header">
          <div className="tutorpress-course-count">
            {localCourses.length} {__("Courses Selected", "tutorpress")}
          </div>
          <Button variant="secondary" onClick={() => setIsModalOpen(true)}>
            <Icon icon={plus} />
            {__("Add Courses", "tutorpress")}
          </Button>
        </div>
      )}

      {isLoading && (
        <div className="tutorpress-bundle-course-loading">
          <Spinner />
          <span>{__("Loading bundle courses...", "tutorpress")}</span>
        </div>
      )}

      {/* Course List */}
      {!isLoading && (
        <div className="tutorpress-bundle-course-list">
          {localCourses.length === 0 ? (
            <div className="tutorpress-bundle-course-empty">
              <p>{__("No courses added to this bundle yet.", "tutorpress")}</p>
              <Button variant="primary" onClick={() => setIsModalOpen(true)}>
                {__("Add First Course", "tutorpress")}
              </Button>
            </div>
          ) : (
            <>
              <DndContext
                sensors={courseSensors}
                onDragStart={dragHandlers.handleDragStart}
                onDragOver={dragHandlers.handleDragOver}
                onDragEnd={dragHandlers.handleDragEnd}
                onDragCancel={dragHandlers.handleDragCancel}
              >
                <SortableContext items={localCourses.map((course) => course.id)} strategy={verticalListSortingStrategy}>
                  <div className="tutorpress-bundle-course-items">
                    {localCourses.map((course, index) => (
                      <SortableCourseItem
                        key={course.id}
                        course={course}
                        index={index}
                        onRemove={() => handleRemoveCourse(course.id)}
                      />
                    ))}
                  </div>
                </SortableContext>
              </DndContext>

              {/* Selection Overview - Temporarily disabled
               * 
               * Issue: Tutor LMS does not automatically set or maintain lesson/quiz/resource count 
               * meta fields (_lesson_count, _quiz_count, _resource_count) on courses. These 
               * statistics are calculated on-the-fly in the frontend but not stored persistently.
               * 
               * The Course Bundle addon expects these meta fields to exist for accurate statistics
               * display. Without them, the statistics show 0 or incorrect values.
               * 
               * Solution needed: Implement a custom statistics handler that updates these meta 
               * fields when course content changes (lessons/quizzes/assignments added/removed).
               * 
               * See: Course Statistics Analysis for detailed investigation and implementation plan.
              
              <div className="tutorpress-selection-overview">
                <h4>{__("Selection Overview", "tutorpress")}</h4>
                <div className="tutorpress-selection-stats">
                  <span>
                    {summary.totalDuration} {__("Total Duration", "tutorpress")}
                  </span>
                  <span>•</span>
                  <span>
                    {summary.totalLessons} {__("Lessons", "tutorpress")}
                  </span>
                  <span>•</span>
                  <span>
                    {summary.totalQuizzes} {__("Quizzes", "tutorpress")}
                  </span>
                  <span>•</span>
                  <span>
                    {summary.totalResources} {__("Resources", "tutorpress")}
                  </span>
                </div>
              </div>
              */}
            </>
          )}
        </div>
      )}

      {/* Course Selection Modal */}
      <CourseSelectionModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onAddCourses={handleAddCourses}
        excludeCourseIds={localCourses.map((course) => course.id)}
      />
    </div>
  );
};
