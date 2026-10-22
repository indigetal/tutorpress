import React, { useState } from "react";
import { TextControl } from "@wordpress/components";
import { buildLiveLessonReconcileRequest, liveLessonRowPresentation } from "../../../utils/liveLessonReconciliation";

type RecoveryAction = ReturnType<typeof liveLessonRowPresentation>["actions"][number];

export const LiveLessonRecoveryControls = ({ actions }: { actions: RecoveryAction[] }) => (
  <>
    {actions.map((action, index) => (
      <LiveLessonRecoveryControl
        action={action}
        key={`${index}:${action.action}:${"kind" in action ? action.kind : ""}:${"operation_id" in action ? action.operation_id : ""}`}
      />
    ))}
  </>
);

const LiveLessonRecoveryControl = ({ action }: { action: RecoveryAction }) => {
  const [providerId, setProviderId] = useState("");
  const attach = action.action === "attach";
  const body = buildLiveLessonReconcileRequest(action, attach ? { provider_id: providerId } : {});
  return (
    <span className="tutorpress-live-lesson-recovery" data-reconcile-body={body === null ? "" : JSON.stringify(body)}>
      {action.action}
      {"kind" in action ? ` ${action.kind}` : null}
      {action.label === undefined ? null : ` ${action.label}`}
      {attach ? <TextControl label="provider_id" value={providerId} onChange={setProviderId} /> : null}
    </span>
  );
};
