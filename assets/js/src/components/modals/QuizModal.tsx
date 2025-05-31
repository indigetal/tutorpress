import React, { useState } from "react";
import { Modal, TabPanel, Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";

interface QuizModalProps {
  isOpen: boolean;
  onClose: () => void;
  topicId?: number;
  courseId?: number;
  quizId?: number; // For editing existing quiz
}

export const QuizModal: React.FC<QuizModalProps> = ({ isOpen, onClose, topicId, courseId, quizId }) => {
  const [activeTab, setActiveTab] = useState("question-details");

  // Get quiz duplication state from curriculum store
  const quizDuplicationState = useSelect((select) => {
    return (select("tutorpress/curriculum") as any).getQuizDuplicationState();
  }, []);

  const { setQuizDuplicationState } = useDispatch("tutorpress/curriculum") as any;

  const handleClose = () => {
    // Reset any quiz state if needed
    setQuizDuplicationState({ status: "idle" });
    onClose();
  };

  const tabs = [
    {
      name: "question-details",
      title: __("Question Details", "tutorpress"),
      className: "quiz-modal-tab-question-details",
    },
    {
      name: "settings",
      title: __("Settings", "tutorpress"),
      className: "quiz-modal-tab-settings",
    },
  ];

  const renderQuestionDetailsTab = () => {
    return (
      <div className="quiz-modal-question-details">
        <div className="quiz-modal-three-column-layout">
          {/* Left Column: Quiz name, Question dropdown, Questions list */}
          <div className="quiz-modal-left-column">
            <div className="quiz-modal-quiz-info">
              <h3>{__("Quiz Title", "tutorpress")}</h3>
              <p className="quiz-modal-topic-context">{topicId && __("Topic ID: ", "tutorpress") + topicId}</p>
            </div>

            <div className="quiz-modal-questions-section">
              <div className="quiz-modal-questions-header">
                <h4>{__("Questions", "tutorpress")}</h4>
                <Button
                  variant="primary"
                  className="quiz-modal-add-question-btn"
                  onClick={() => {
                    console.log("Add question clicked");
                  }}
                >
                  +
                </Button>
              </div>

              <div className="quiz-modal-questions-list">
                <p className="quiz-modal-no-questions">{__("No questions added yet.", "tutorpress")}</p>
              </div>
            </div>
          </div>

          {/* Center Column: Contextual question form */}
          <div className="quiz-modal-center-column">
            <div className="quiz-modal-question-form">
              <div className="quiz-modal-empty-state">
                <p>{__("Create/Select a question to view details", "tutorpress")}</p>
              </div>
            </div>
          </div>

          {/* Right Column: Contextual question settings */}
          <div className="quiz-modal-right-column">
            <div className="quiz-modal-question-settings">
              <h4>{__("Question Type", "tutorpress")}</h4>
              <div className="quiz-modal-empty-state">
                <p>{__("Select a question to view settings", "tutorpress")}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderSettingsTab = () => {
    return (
      <div className="quiz-modal-settings">
        <div className="quiz-modal-single-column-layout">
          <div className="quiz-modal-settings-content">
            <h3>{__("Quiz Settings", "tutorpress")}</h3>

            <div className="quiz-modal-basic-settings">
              <h4>{__("Basic Settings", "tutorpress")}</h4>
              <p>{__("Quiz settings will be implemented here", "tutorpress")}</p>
            </div>

            <div className="quiz-modal-advanced-settings">
              <h4>{__("Advanced Settings", "tutorpress")}</h4>
              <p>{__("Advanced quiz settings will be implemented here", "tutorpress")}</p>
            </div>
          </div>
        </div>
      </div>
    );
  };

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={quizId ? __("Edit Quiz", "tutorpress") : __("Create Quiz", "tutorpress")}
      onRequestClose={handleClose}
      className="quiz-modal"
      size="large"
    >
      <div className="quiz-modal-content">
        <TabPanel
          className="quiz-modal-tabs"
          activeClass="is-active"
          tabs={tabs}
          onSelect={(tabName) => setActiveTab(tabName)}
        >
          {(tab) => {
            switch (tab.name) {
              case "question-details":
                return renderQuestionDetailsTab();
              case "settings":
                return renderSettingsTab();
              default:
                return null;
            }
          }}
        </TabPanel>

        {/* Modal Footer */}
        <div className="quiz-modal-footer">
          <Button variant="secondary" onClick={handleClose}>
            {__("Cancel", "tutorpress")}
          </Button>
          <Button
            variant="primary"
            onClick={() => {
              console.log("Save quiz clicked");
              // TODO: Implement save functionality
            }}
          >
            {__("Save Quiz", "tutorpress")}
          </Button>
        </div>
      </div>
    </Modal>
  );
};
