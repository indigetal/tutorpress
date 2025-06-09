import React, { useState } from "react";
import { Card, CardBody, Button, Flex, TextControl, TextareaControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { CurriculumError, TopicFormData } from "../../../types/curriculum";

/**
 * Props for topic form component
 */
export interface TopicFormProps {
  initialData?: TopicFormData;
  onSave: (data: TopicFormData) => void;
  onCancel: () => void;
  error?: CurriculumError;
  isCreating?: boolean;
}

/**
 * Topic form component for adding/editing topics
 */
export const TopicForm: React.FC<TopicFormProps> = ({
  initialData,
  onSave,
  onCancel,
  error,
  isCreating,
}): JSX.Element => {
  const [formData, setFormData] = useState<TopicFormData>({
    title: initialData?.title ?? "",
    summary: initialData?.summary ?? "",
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSave(formData);
  };

  // Check if form is valid (title is required)
  const isValid = formData.title.trim().length > 0;

  return (
    <Card className="tutorpress-topic" style={{ boxShadow: "0 0 0 2px #007cba33" }}>
      <form onSubmit={handleSubmit}>
        <CardBody>
          <Flex direction="column" gap={3}>
            {error && <div style={{ color: "#cc1818", marginBottom: "8px" }}>{error.message}</div>}
            <TextControl
              label={__("Topic Title", "tutorpress")}
              placeholder={__("Add title", "tutorpress")}
              value={formData.title}
              onChange={(title) => setFormData((prev) => ({ ...prev, title }))}
              autoFocus
              required
            />
            <TextareaControl
              label={__("Topic Summary", "tutorpress")}
              placeholder={__("Add summary", "tutorpress")}
              value={formData.summary}
              onChange={(summary) => setFormData((prev) => ({ ...prev, summary }))}
              rows={4}
            />
            <Flex justify="flex-end" gap={2}>
              <Button variant="secondary" onClick={onCancel}>
                {__("Cancel", "tutorpress")}
              </Button>
              <Button variant="primary" type="submit" isBusy={isCreating} disabled={!isValid || isCreating}>
                {__("Save", "tutorpress")}
              </Button>
            </Flex>
          </Flex>
        </CardBody>
      </form>
    </Card>
  );
};

export default TopicForm;
