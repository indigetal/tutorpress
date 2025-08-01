import React, { useEffect, useState, useCallback } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { useSelect, useDispatch, select } from "@wordpress/data";
import { Spinner, Notice, FormTokenField, Button, Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { chevronDown, chevronUp } from "@wordpress/icons";

// Import course settings types
import type { CourseInstructors, InstructorSearchResult, InstructorUser } from "../../types/courses";
import { isMultiInstructorsEnabled } from "../../utils/addonChecker";

const CourseInstructorsPanel: React.FC = () => {
  // State for search functionality
  const [searchValue, setSearchValue] = useState("");
  const [selectedTokens, setSelectedTokens] = useState<string[]>([]);
  const [authorSearchValue, setAuthorSearchValue] = useState("");
  const [isAuthorExpanded, setIsAuthorExpanded] = useState(false);

  // Get instructor data from our store
  const { postType, instructors, error, isLoading, searchResults, isSearching, searchError } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      instructors: select("tutorpress/course-settings").getInstructors(),
      error: select("tutorpress/course-settings").getInstructorsError(),
      isLoading: select("tutorpress/course-settings").getInstructorsLoading(),
      searchResults: select("tutorpress/course-settings").getInstructorSearchResults(),
      isSearching: select("tutorpress/course-settings").getInstructorsSearching(),
      searchError: select("tutorpress/course-settings").getInstructorSearchError(),
    }),
    []
  );

  // Get dispatch actions
  const { getCourseInstructors, searchInstructors, updateCourseInstructors, updateCourseAuthor } =
    useDispatch("tutorpress/course-settings");
  const { editPost } = useDispatch("core/editor");

  // Load instructors when component mounts
  useEffect(() => {
    if (postType === "courses") {
      getCourseInstructors();
    }
  }, [postType, getCourseInstructors]);

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Check if Multi Instructors addon is enabled
  const isMultiInstructorsAddonEnabled = isMultiInstructorsEnabled();

  // Debounced search function
  const debouncedSearch = useCallback(
    (() => {
      let timeoutId: NodeJS.Timeout;
      return (searchTerm: string) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
          if (searchTerm.trim().length >= 2) {
            searchInstructors(searchTerm);
          }
        }, 500); // Increased debounce time
      };
    })(),
    [searchInstructors]
  );

  // Handle search input change
  const handleSearchChange = useCallback(
    (value: string) => {
      setSearchValue(value);
      // Only search if we have enough characters and not currently searching
      if (value.trim().length >= 2 && !isSearching) {
        debouncedSearch(value);
      }
    },
    [debouncedSearch, isSearching]
  );

  // Handle author search input change
  const handleAuthorSearchChange = useCallback(
    (value: string) => {
      setAuthorSearchValue(value);
      // Only search if we have enough characters and not currently searching
      if (value.trim().length >= 2 && !isSearching) {
        debouncedSearch(value);
      }
    },
    [debouncedSearch, isSearching]
  );

  // Handle author selection
  const handleAuthorSelection = useCallback(
    async (tokens: (string | { value: string })[]) => {
      const tokenStrings = tokens.map((token) => (typeof token === "string" ? token : token.value));

      // Find the instructor ID for the selected token
      const selectedInstructor = searchResults.find((instructor: InstructorSearchResult) =>
        tokenStrings.includes(`${instructor.display_name} (${instructor.user_email})`)
      );

      if (selectedInstructor) {
        // Check if the selected instructor is already the current author
        if (instructors?.author?.id === selectedInstructor.id) {
          // Already the current author, just close the search
          setAuthorSearchValue("");
          setIsAuthorExpanded(false);
          return;
        }

        try {
          // Update the post author using our REST API
          await updateCourseAuthor(selectedInstructor.id);

          // Update the local editor state
          editPost({ post_author: selectedInstructor.id });
          setAuthorSearchValue("");
          setIsAuthorExpanded(false);
        } catch (error) {
          console.error("Error updating author:", error);
          // Could add user notification here in the future
        }
      }
    },
    [searchResults, editPost, updateCourseAuthor]
  );

  // Generate suggestions for author search (exclude current author)
  const getAuthorSuggestions = useCallback(() => {
    if (!searchResults || searchResults.length === 0) return [];

    const currentAuthorId = instructors?.author?.id;
    const availableInstructors = searchResults.filter(
      (instructor: InstructorSearchResult) => instructor.id !== currentAuthorId
    );

    return availableInstructors.map(
      (instructor: InstructorSearchResult) => `${instructor.display_name} (${instructor.user_email})`
    );
  }, [searchResults, instructors]);

  // Handle instructor selection
  const handleInstructorSelection = useCallback(
    (tokens: (string | { value: string })[]) => {
      const tokenStrings = tokens.map((token) => (typeof token === "string" ? token : token.value));
      setSelectedTokens(tokenStrings);

      // Find the instructor IDs for the selected tokens
      const selectedInstructors = searchResults.filter((instructor: InstructorSearchResult) =>
        tokenStrings.includes(`${instructor.display_name} (${instructor.user_email})`)
      );

      if (selectedInstructors.length > 0) {
        const currentInstructorIds = instructors?.co_instructors?.map((i: InstructorUser) => i.id) || [];
        const newInstructorIds = selectedInstructors.map((i: InstructorSearchResult) => i.id);
        const updatedInstructorIds = [...new Set([...currentInstructorIds, ...newInstructorIds])];

        updateCourseInstructors(updatedInstructorIds);
        setSearchValue("");
        setSelectedTokens([]);
      }
    },
    [searchResults, instructors, updateCourseInstructors]
  );

  // Handle instructor removal
  const handleRemoveInstructor = useCallback(
    (instructorId: number) => {
      if (window.confirm(__("Are you sure you want to remove this instructor from the course?", "tutorpress"))) {
        const currentInstructorIds = instructors?.co_instructors?.map((i: InstructorUser) => i.id) || [];
        const updatedInstructorIds = currentInstructorIds.filter((id: number) => id !== instructorId);
        updateCourseInstructors(updatedInstructorIds);
      }
    },
    [instructors, updateCourseInstructors]
  );

  // Generate suggestions for FormTokenField
  const getSuggestions = useCallback(() => {
    if (!searchResults || searchResults.length === 0) return [];

    // Filter out already selected instructors
    const currentInstructorIds = instructors?.co_instructors?.map((i: InstructorUser) => i.id) || [];
    const availableInstructors = searchResults.filter(
      (instructor: InstructorSearchResult) => !currentInstructorIds.includes(instructor.id)
    );

    return availableInstructors.map(
      (instructor: InstructorSearchResult) => `${instructor.display_name} (${instructor.user_email})`
    );
  }, [searchResults, instructors]);

  // Show loading state while fetching instructors
  if (isLoading) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-course-instructors"
        title={__("Course Instructors", "tutorpress")}
        className="tutorpress-course-instructors-panel"
      >
        <div className="tutorpress-settings-loading">
          <Spinner />
          <div className="tutorpress-settings-loading-text">{__("Loading instructors...", "tutorpress")}</div>
        </div>
      </PluginDocumentSettingPanel>
    );
  }

  // Show error state if there's an error
  if (error) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-course-instructors"
        title={__("Course Instructors", "tutorpress")}
        className="tutorpress-course-instructors-panel"
      >
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      </PluginDocumentSettingPanel>
    );
  }

  // Show message if no instructor data is available
  if (!instructors) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-course-instructors"
        title={__("Course Instructors", "tutorpress")}
        className="tutorpress-course-instructors-panel"
      >
        <div className="tutorpress-instructors-empty">
          <p>{__("No instructors assigned to this course.", "tutorpress")}</p>
        </div>
      </PluginDocumentSettingPanel>
    );
  }

  return (
    <PluginDocumentSettingPanel
      name="tutorpress-course-instructors"
      title={__("Course Instructors", "tutorpress")}
      className="tutorpress-course-instructors-panel"
    >
      <div className="tutorpress-instructors-panel">
        {instructors.author && (
          <div className="tutorpress-saved-files-list">
            <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
              {__("Author:", "tutorpress")}
            </div>
            <div className="tutorpress-saved-file-item">
              <div className="tutorpress-instructor-info">
                <div className="tutorpress-instructor-avatar">
                  {instructors.author.avatar_url ? (
                    <img
                      src={instructors.author.avatar_url}
                      alt={instructors.author.display_name}
                      className="tutorpress-instructor-avatar-img"
                    />
                  ) : (
                    <div className="tutorpress-instructor-avatar-placeholder">
                      {instructors.author.display_name.charAt(0).toUpperCase()}
                    </div>
                  )}
                </div>
                <div className="tutorpress-instructor-details">
                  <div className="tutorpress-instructor-name">{instructors.author.display_name}</div>
                  <div className="tutorpress-instructor-email">{instructors.author.user_email}</div>
                </div>
              </div>
              <Button
                variant="tertiary"
                onClick={() => setIsAuthorExpanded(!isAuthorExpanded)}
                className="edit-button"
                aria-label={__("Change author", "tutorpress")}
              >
                <Icon icon={isAuthorExpanded ? chevronUp : chevronDown} size={16} />
              </Button>
            </div>

            {/* Author Search Field - Expanded */}
            {isAuthorExpanded && (
              <div style={{ marginTop: "12px" }}>
                <FormTokenField
                  label={__("Change Author", "tutorpress")}
                  value={[]}
                  suggestions={getAuthorSuggestions()}
                  onChange={handleAuthorSelection}
                  onInputChange={handleAuthorSearchChange}
                  placeholder={__("Search for new author...", "tutorpress")}
                  __experimentalExpandOnFocus={true}
                  __experimentalAutoSelectFirstMatch={false}
                  __experimentalShowHowTo={false}
                />
                {isSearching && (
                  <div style={{ marginTop: "4px", fontSize: "12px", color: "#757575" }}>
                    <Spinner style={{ marginRight: "4px" }} />
                    {__("Searching...", "tutorpress")}
                  </div>
                )}
                {searchError && (
                  <div style={{ marginTop: "4px", fontSize: "12px", color: "#d63638" }}>{searchError}</div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Co-Instructors Section with Search */}
        {isMultiInstructorsAddonEnabled && (
          <div className="tutorpress-saved-files-list" style={{ marginTop: "24px" }}>
            {/* Search Field */}
            <div style={{ marginBottom: "12px" }}>
              <FormTokenField
                label={`${__("Co-Instructors:", "tutorpress")} ${instructors.co_instructors ? `(${instructors.co_instructors.length})` : ""}`}
                value={selectedTokens}
                suggestions={getSuggestions()}
                onChange={handleInstructorSelection}
                onInputChange={handleSearchChange}
                placeholder={__("Search for instructors...", "tutorpress")}
                __experimentalExpandOnFocus={true}
                __experimentalAutoSelectFirstMatch={false}
                __experimentalShowHowTo={false}
              />
              {isSearching && (
                <div style={{ marginTop: "4px", fontSize: "12px", color: "#757575" }}>
                  <Spinner style={{ marginRight: "4px" }} />
                  {__("Searching...", "tutorpress")}
                </div>
              )}
              {searchError && <div style={{ marginTop: "4px", fontSize: "12px", color: "#d63638" }}>{searchError}</div>}
            </div>

            {/* Co-Instructors List with Delete Buttons */}
            {instructors.co_instructors && instructors.co_instructors.length > 0 ? (
              instructors.co_instructors.map((instructor: InstructorUser) => (
                <div key={instructor.id} className="tutorpress-saved-file-item">
                  <div className="tutorpress-instructor-info">
                    <div className="tutorpress-instructor-avatar">
                      {instructor.avatar_url ? (
                        <img
                          src={instructor.avatar_url}
                          alt={instructor.display_name}
                          className="tutorpress-instructor-avatar-img"
                        />
                      ) : (
                        <div className="tutorpress-instructor-avatar-placeholder">
                          {instructor.display_name.charAt(0).toUpperCase()}
                        </div>
                      )}
                    </div>
                    <div className="tutorpress-instructor-details">
                      <div className="tutorpress-instructor-name">{instructor.display_name}</div>
                      <div className="tutorpress-instructor-email">{instructor.user_email}</div>
                    </div>
                  </div>
                  <Button
                    variant="tertiary"
                    onClick={() => handleRemoveInstructor(instructor.id)}
                    className="delete-button"
                    aria-label={__("Remove instructor", "tutorpress")}
                  >
                    ×
                  </Button>
                </div>
              ))
            ) : (
              <div className="tutorpress-instructors-empty">
                <p>{__("No co-instructors added.", "tutorpress")}</p>
              </div>
            )}
          </div>
        )}

        {!instructors.author && !isMultiInstructorsAddonEnabled && (
          <div className="tutorpress-instructors-empty">
            <p>{__("No instructors assigned to this course.", "tutorpress")}</p>
          </div>
        )}
      </div>
    </PluginDocumentSettingPanel>
  );
};

export default CourseInstructorsPanel;
