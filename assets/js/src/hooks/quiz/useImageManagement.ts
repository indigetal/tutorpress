/**
 * Image Management Hook
 *
 * @description Centralized image management for quiz question options. Handles WordPress Media
 *              Library integration, image upload/removal, and state management. Extracted from
 *              MultipleChoiceQuestion and QuizModal during Phase 2.5 refactoring to eliminate
 *              image handling code duplication while preserving exact functionality.
 *
 * @features
 * - WordPress Media Library integration
 * - Image upload with type validation
 * - Image removal functionality
 * - Consistent error handling
 * - State management for current images
 * - Callback-based architecture for flexibility
 *
 * @usage
 * const {
 *   currentImage,
 *   setCurrentImage,
 *   openMediaLibrary,
 *   removeImage,
 *   isMediaLibraryAvailable
 * } = useImageManagement();
 *
 * @package TutorPress
 * @subpackage Quiz/Hooks
 * @since 1.0.0
 */

import { useState, useCallback } from "react";
import { __ } from "@wordpress/i18n";

/**
 * Image data interface
 */
export interface ImageData {
  id: number;
  url: string;
}

/**
 * Media library configuration options
 */
export interface MediaLibraryConfig {
  title?: string;
  buttonText?: string;
  multiple?: boolean;
  allowedTypes?: string[];
}

/**
 * Callback function for when an image is selected
 */
export type ImageSelectCallback = (imageData: ImageData) => void;

/**
 * Callback function for when an image is removed
 */
export type ImageRemoveCallback = () => void;

/**
 * Hook return interface
 */
export interface UseImageManagementReturn {
  /** Current image data */
  currentImage: ImageData | null;
  /** Set current image data */
  setCurrentImage: (imageData: ImageData | null) => void;
  /** Open WordPress Media Library */
  openMediaLibrary: (config?: MediaLibraryConfig, onSelect?: ImageSelectCallback) => void;
  /** Remove current image */
  removeImage: (onRemove?: ImageRemoveCallback) => void;
  /** Check if WordPress Media Library is available */
  isMediaLibraryAvailable: () => boolean;
  /** Create image handlers for option editing */
  createImageHandlers: (onImageUpdate: (imageData: ImageData | null) => void) => {
    handleImageAdd: () => void;
    handleImageRemove: () => void;
  };
}

/**
 * Image Management Hook
 */
export const useImageManagement = (): UseImageManagementReturn => {
  const [currentImage, setCurrentImage] = useState<ImageData | null>(null);

  /**
   * Check if WordPress Media Library is available
   */
  const isMediaLibraryAvailable = useCallback((): boolean => {
    return typeof (window as any).wp !== "undefined" && (window as any).wp.media;
  }, []);

  /**
   * Open WordPress Media Library with configuration
   */
  const openMediaLibrary = useCallback(
    (config: MediaLibraryConfig = {}, onSelect?: ImageSelectCallback) => {
      if (!isMediaLibraryAvailable()) {
        console.error("WordPress media library not available");
        return;
      }

      const {
        title = __("Select Image for Option", "tutorpress"),
        buttonText = __("Use this image", "tutorpress"),
        multiple = false,
        allowedTypes = ["image"],
      } = config;

      const mediaFrame = (window as any).wp.media({
        title,
        button: {
          text: buttonText,
        },
        multiple,
        library: {
          type: allowedTypes.join(","),
          uploadedTo: null, // Allow all images in media library
        },
        // Restrict to specified file types only
        states: [
          new (window as any).wp.media.controller.Library({
            title,
            library: (window as any).wp.media.query({
              type: allowedTypes.join(","),
            }),
            multiple,
            date: false,
          }),
        ],
      });

      mediaFrame.on("select", () => {
        const attachment = mediaFrame.state().get("selection").first().toJSON();

        // Validate that the selected file is of allowed type
        if (!attachment.type || !allowedTypes.includes(attachment.type)) {
          console.error(`Selected file is not a valid type. Allowed types: ${allowedTypes.join(", ")}`);
          return;
        }

        const imageData: ImageData = {
          id: attachment.id,
          url: attachment.url,
        };

        // Update internal state
        setCurrentImage(imageData);

        // Call external callback if provided
        if (onSelect) {
          onSelect(imageData);
        }
      });

      mediaFrame.open();
    },
    [isMediaLibraryAvailable]
  );

  /**
   * Remove current image
   */
  const removeImage = useCallback((onRemove?: ImageRemoveCallback) => {
    setCurrentImage(null);

    // Call external callback if provided
    if (onRemove) {
      onRemove();
    }
  }, []);

  /**
   * Create image handlers for option editing
   * This provides a simple interface for components that need image add/remove functionality
   */
  const createImageHandlers = useCallback(
    (onImageUpdate: (imageData: ImageData | null) => void) => {
      const handleImageAdd = () => {
        openMediaLibrary(
          {
            title: __("Select Image for Option", "tutorpress"),
            buttonText: __("Use this image", "tutorpress"),
            multiple: false,
            allowedTypes: ["image"],
          },
          (imageData) => {
            onImageUpdate(imageData);
          }
        );
      };

      const handleImageRemove = () => {
        removeImage(() => {
          onImageUpdate(null);
        });
      };

      return {
        handleImageAdd,
        handleImageRemove,
      };
    },
    [openMediaLibrary, removeImage]
  );

  return {
    currentImage,
    setCurrentImage,
    openMediaLibrary,
    removeImage,
    isMediaLibraryAvailable,
    createImageHandlers,
  };
};
