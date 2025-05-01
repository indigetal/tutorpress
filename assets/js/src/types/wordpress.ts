/**
 * WordPress package type declarations
 */

declare module "@wordpress/element" {
  export function render(element: JSX.Element, container: Element | null): void;
}
