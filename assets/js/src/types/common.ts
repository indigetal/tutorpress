/**
 * Common type definitions
 *
 * Contains shared types and WordPress type declarations needed for the
 * Course Curriculum metabox implementation.
 */

// WordPress package type declarations
declare module "@wordpress/element" {
  export function render(element: JSX.Element, container: Element | null): void;
}

// Topic-related types
export interface Topic {
  id: number;
  title: string;
  isExpanded?: boolean;
}
