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

declare module "@wordpress/notices" {
  export interface Notice {
    id: string;
    content: string;
    status: "success" | "error" | "warning" | "info";
    isDismissible: boolean;
    type: "default" | "snackbar";
  }

  export interface NoticeActions {
    createNotice(
      status: Notice["status"],
      content: string,
      options?: {
        id?: string;
        isDismissible?: boolean;
        type?: Notice["type"];
        actions?: Array<{
          label: string;
          onClick: () => void;
        }>;
      }
    ): void;
    createSuccessNotice(content: string, options?: any): void;
    createErrorNotice(content: string, options?: any): void;
    createWarningNotice(content: string, options?: any): void;
    createInfoNotice(content: string, options?: any): void;
    removeNotice(id: string): void;
  }

  export const store: string;
}
