/**
 * WordPress Package Module Declarations
 *
 * This file contains type declarations for WordPress packages that cannot be
 * augmented in the main wordpress.ts file due to TypeScript module resolution rules.
 */

declare module "@wordpress/edit-post" {
  export interface PluginDocumentSettingPanelProps {
    name: string;
    title: string;
    className?: string;
    children: React.ReactNode;
  }

  export const PluginDocumentSettingPanel: React.FC<PluginDocumentSettingPanelProps>;
}
