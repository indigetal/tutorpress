import React, { useEffect, useState, useCallback } from "react";
import { useEntityProp } from "@wordpress/core-data";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { useSelect, useDispatch, select } from "@wordpress/data";
import { Spinner, Notice, FormTokenField, Button, Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { chevronDown, chevronUp } from "@wordpress/icons";

// Import course settings types
import type { CourseInstructors, InstructorSearchResult, InstructorUser } from "../../types/courses";
import { isMultiInstructorsEnabled } from "../../utils/addonChecker";
import PromoPanel from "../common/PromoPanel";

const CourseInstructorsPanel: React.FC = () => {
  // State for search functionality
  const [searchValue, setSearchValue] = useState("");
  const [selectedTokens, setSelectedTokens] = useState<string[]>([]);
  const [authorSearchValue, setAuthorSearchValue] = useState("");
  const [isAuthorExpanded, setIsAuthorExpanded] = useState(false);

  // Entity binding and local UI echo for immediate feedback
  const [entitySettings, setCourseSettings] = useEntityProp("postType", "courses", "course_settings");
  const [uiCoIds, setUiCoIds] = useState<number[] | null>(null);
  const [uiAuthor, setUiAuthor] = useState<InstructorUser | null>(null);

  // Get instructor data from dedicated instructors store (read-only)
  const { postType, instructors, error, isLoading, searchResults, isSearching, searchError } = useSelect(
    (select: any) => {
      // Derive author from the core entity (author id) and resolve to a user object if possible
      const authorId = select("core/editor").getEditedPostAttribute("author");
      const storeAuthor = select("tutorpress/instructors").getAuthor();
      const storeCo = select("tutorpress/instructors").getCoInstructors() || [];
      const searchCo = select("tutorpress/instructors").getSearchResults() || [];
      const entityIds = Array.isArray(uiCoIds)
        ? uiCoIds
        : Array.isArray(entitySettings?.instructors)
          ? (entitySettings?.instructors as number[])
          : [];

      const idToUser = (list: any[]) => {
        const entries: Array<[number, any]> = [];
        for (const u of list) {
          if (u && typeof u.id === "number") {
            entries.push([u.id, u]);
          }
        }
        return new Map<number, any>(entries);
      };

      const storeMap = idToUser(storeCo);
      const searchMap = idToUser(searchCo);
      const composedCo = Array.isArray(entityIds)
        ? entityIds
            .filter((id) => typeof id === "number")
            .map((id) => {
              const fromStore = storeMap.get(id) || searchMap.get(id);
              if (fromStore) return fromStore;
              const coreUser: any = (select as any)("core").getEntityRecord("root", "user", id);
              if (coreUser) {
                const avatar =
                  (coreUser.avatar_urls &&
                    (coreUser.avatar_urls[96] || coreUser.avatar_urls[48] || coreUser.avatar_urls[24])) ||
                  "";
                return {
                  id,
                  display_name: coreUser.name || `#${id}`,
                  user_email: coreUser.email || "",
                  avatar_url: avatar,
                };
              }
              return { id, display_name: `#${id}`, user_email: "", avatar_url: "" };
            })
        : storeCo;

      const authorObj =
        typeof authorId === "number"
          ? uiAuthor && uiAuthor.id === authorId
            ? uiAuthor
            : storeAuthor && storeAuthor.id === authorId
              ? storeAuthor
              : (() => {
                  const found = searchCo.find((u: any) => u && u.id === authorId);
                  if (found) return found;
                  const coreUser: any = (select as any)("core").getEntityRecord("root", "user", authorId);
                  if (coreUser) {
                    const avatar =
                      (coreUser.avatar_urls &&
                        (coreUser.avatar_urls[96] || coreUser.avatar_urls[48] || coreUser.avatar_urls[24])) ||
                      "";
                    return {
                      id: authorId,
                      display_name: coreUser.name || `#${authorId}`,
                      user_email: coreUser.email || "",
                      avatar_url: avatar,
                    };
                  }
                  return { id: authorId, display_name: `#${authorId}`, user_email: "", avatar_url: "" };
                })()
          : storeAuthor || null;
      const composed =
        authorObj || (composedCo && composedCo.length > 0) ? { author: authorObj, co_instructors: composedCo } : null;
      return {
        postType: select("core/editor").getCurrentPostType(),
        instructors: composed,
        error: select("tutorpress/instructors").getError(),
        isLoading: select("tutorpress/instructors").getIsLoading(),
        searchResults: select("tutorpress/instructors").getSearchResults(),
        isSearching: select("tutorpress/instructors").getIsSearching(),
        searchError: select("tutorpress/instructors").getSearchError(),
      };
    },
    [uiCoIds, entitySettings, uiAuthor]
  );

  // Get dispatch actions
  const { fetchCourseInstructors, searchInstructors } = useDispatch("tutorpress/instructors");
  const { editPost } = useDispatch("core/editor");

  // Load instructors when component mounts
  useEffect(() => {
    if (postType === "courses") {
      fetchCourseInstructors();
    }
  }, [postType, fetchCourseInstructors]);

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-course-instructors"
        title={__("Course Instructors", "tutorpress")}
        className="tutorpress-course-instructors-panel"
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  // Check if Multi Instructors addon is enabled
  const isMultiInstructorsAddonEnabled = isMultiInstructorsEnabled();

  // Clear UI echo once entity reflects the pending ids
  useEffect(() => {
    if (Array.isArray(uiCoIds) && Array.isArray(entitySettings?.instructors)) {
      const a = uiCoIds.slice().sort();
      const b = (entitySettings?.instructors as number[]).slice().sort();
      if (a.length === b.length && a.every((id, idx) => id === b[idx])) {
        setUiCoIds(null);
      }
    }
  }, [entitySettings, uiCoIds]);

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

      // Snapshot the previous author before changing
      const prevAuthorId = (select as any)("core/editor").getEditedPostAttribute("author");

      // Find the instructor ID for the selected token
      const selectedInstructor = searchResults.find((instructor: InstructorSearchResult) =>
        tokenStrings.includes(`${instructor.display_name} (${instructor.user_email})`)
      );

      if (selectedInstructor) {
        // Check if the selected instructor is already the current author
        if (instructors?.author?.id === selectedInstructor.id) {
          setAuthorSearchValue("");
          setIsAuthorExpanded(false);
          return;
        }

        // Update author in entity
        editPost({ author: selectedInstructor.id });
        setUiAuthor({
          id: selectedInstructor.id,
          display_name: selectedInstructor.display_name,
          user_email: (selectedInstructor as any).user_email || "",
          avatar_url: (selectedInstructor as any).avatar_url || "",
        } as any);

        // Replicate Tutor LMS Multi Instructors behavior: add previous author as co-instructor
        if (isMultiInstructorsAddonEnabled && typeof prevAuthorId === "number" && prevAuthorId > 0) {
          // Start from entity instructors or current UI store-derived list
          const currentEntity = (select as any)("core/editor").getEditedPostAttribute("course_settings") || {};
          const currentInstructorIds = Array.isArray(currentEntity?.instructors)
            ? (currentEntity?.instructors as number[])
            : instructors?.co_instructors?.map((i: InstructorUser) => i.id) || [];

          // Ensure the newly selected author is not listed as co-instructor
          let nextIds = currentInstructorIds.filter((id: number) => id !== selectedInstructor.id);

          // Append previous author if not already present and not the same as new author
          if (prevAuthorId !== selectedInstructor.id && !nextIds.includes(prevAuthorId)) {
            nextIds = [...nextIds, prevAuthorId];
          }

          // Write to entity and echo locally for immediate UI feedback
          (setCourseSettings as any)((prev: any) => ({ ...(prev || {}), instructors: nextIds }));
          setUiCoIds(nextIds);
        }

        setAuthorSearchValue("");
        setIsAuthorExpanded(false);
      }
    },
    [searchResults, instructors, isMultiInstructorsAddonEnabled, setCourseSettings, editPost]
  );

  // Clear uiAuthor echo when store author catches up or author id diverges
  useEffect(() => {
    if (!uiAuthor) return;
    const currentAuthorId = (select as any)("core/editor").getEditedPostAttribute("author");
    if (typeof currentAuthorId !== "number") {
      setUiAuthor(null);
      return;
    }
    const storeAuthor = (select as any)("tutorpress/instructors").getAuthor?.();
    if (storeAuthor && storeAuthor.id === currentAuthorId) {
      setUiAuthor(null);
    }
  }, [uiAuthor]);

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
        // Prefer entity instructors when available
        const currentInstructorIds = Array.isArray(entitySettings?.instructors)
          ? (entitySettings?.instructors as number[])
          : []
              .concat(
                !Array.isArray(entitySettings?.instructors) && instructors?.co_instructors
                  ? instructors.co_instructors.map((i: InstructorUser) => i.id)
                  : []
              )
              .filter((v: any, idx: number, arr: any[]) => typeof v === "number" && arr.indexOf(v) === idx);
        const newInstructorIds = selectedInstructors.map((i: InstructorSearchResult) => i.id);
        const updatedInstructorIds = [...new Set([...currentInstructorIds, ...newInstructorIds])];
        // Entity write and UI echo to render immediately before entity propagation
        setCourseSettings((prev: any) => ({ ...(prev || {}), instructors: updatedInstructorIds }));
        setUiCoIds(updatedInstructorIds);
        setSearchValue("");
        setSelectedTokens([]);
      }
    },
    [searchResults, instructors, setCourseSettings]
  );

  // Handle instructor removal
  const handleRemoveInstructor = useCallback(
    (instructorId: number) => {
      if (window.confirm(__("Are you sure you want to remove this instructor from the course?", "tutorpress"))) {
        const currentInstructorIds = Array.isArray(entitySettings?.instructors)
          ? (entitySettings?.instructors as number[])
          : instructors?.co_instructors?.map((i: InstructorUser) => i.id) || [];
        const updatedInstructorIds = currentInstructorIds.filter((id: number) => id !== instructorId);
        setCourseSettings((prev: any) => ({ ...(prev || {}), instructors: updatedInstructorIds }));
        setUiCoIds(updatedInstructorIds);
      }
    },
    [entitySettings, instructors, setCourseSettings]
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
