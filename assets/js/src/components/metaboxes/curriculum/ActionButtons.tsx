import React from "react";
import { Button, Flex } from "@wordpress/components";
import { edit, copy, trash } from "@wordpress/icons";

/**
 * Props for action buttons
 */
export interface ActionButtonsProps {
  onEdit?: () => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
}

/**
 * Action buttons for items and topics
 */
export const ActionButtons: React.FC<ActionButtonsProps> = ({ onEdit, onDuplicate, onDelete }): JSX.Element => (
  <Flex gap={1} justify="flex-end" style={{ width: "auto" }}>
    <Button icon={edit} label="Edit" isSmall onClick={onEdit} />
    <Button icon={copy} label="Duplicate" isSmall onClick={onDuplicate} />
    <Button icon={trash} label="Delete" isSmall onClick={onDelete} />
  </Flex>
);

export default ActionButtons;
