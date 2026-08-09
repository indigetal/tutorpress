import { describe, expect, it, beforeEach, afterEach } from "@jest/globals";
import {
  AddonChecker,
  isH5pEnabled,
  isH5pPluginActive,
} from "../addonChecker";
import {
  isInteractiveQuizEditingAvailable,
  shouldBlockQuizSettingsEditing,
} from "../quizSettingsContract";

describe("AddonChecker localize flag normalization", () => {
  const previousAddons = window.tutorpressAddons;

  beforeEach(() => {
    AddonChecker.clearCache();
  });

  afterEach(() => {
    window.tutorpressAddons = previousAddons;
    AddonChecker.clearCache();
  });

  it("normalizes wp_localize string and boolean H5P flags to real booleans", () => {
    window.tutorpressAddons = {
      ...(previousAddons || ({} as any)),
      h5p: "1" as unknown as boolean,
      h5p_plugin_active: "1" as unknown as boolean,
    };

    expect(isH5pEnabled()).toBe(true);
    expect(isH5pPluginActive()).toBe(true);
    expect(typeof isH5pEnabled()).toBe("boolean");
    expect(typeof isH5pPluginActive()).toBe("boolean");

    AddonChecker.clearCache();
    window.tutorpressAddons = {
      ...(previousAddons || ({} as any)),
      h5p: true,
      h5p_plugin_active: true,
    };
    expect(isH5pEnabled()).toBe(true);
    expect(isH5pPluginActive()).toBe(true);

    AddonChecker.clearCache();
    window.tutorpressAddons = {
      ...(previousAddons || ({} as any)),
      h5p: "0" as unknown as boolean,
      h5p_plugin_active: "" as unknown as boolean,
    };
    expect(isH5pEnabled()).toBe(false);
    expect(isH5pPluginActive()).toBe(false);
  });

  it("does not treat other truthy strings as enabled", () => {
    window.tutorpressAddons = {
      ...(previousAddons || ({} as any)),
      h5p: "true" as unknown as boolean,
      h5p_plugin_active: "yes" as unknown as boolean,
    };

    expect(isH5pEnabled()).toBe(false);
    expect(isH5pPluginActive()).toBe(false);
  });

  it("allows Interactive editing when WP H5P plugin localize flag is on and Pro H5P is off", () => {
    window.tutorpressAddons = {
      ...(previousAddons || ({} as any)),
      h5p: "0" as unknown as boolean,
      h5p_plugin_active: "1" as unknown as boolean,
    };

    expect(isH5pEnabled()).toBe(false);
    expect(isH5pPluginActive()).toBe(true);
    const h5pPluginAvailable = isH5pPluginActive();
    expect(
      isInteractiveQuizEditingAvailable({
        contentType: "tutor_h5p_quiz",
        contract: "v4",
        h5pPluginAvailable,
      })
    ).toBe(true);
    expect(
      shouldBlockQuizSettingsEditing({
        contentType: "tutor_h5p_quiz",
        contract: "v4",
        h5pPluginAvailable,
      })
    ).toBe(false);
  });

  it("blocks Interactive editing when WP H5P plugin localize flag is off", () => {
    window.tutorpressAddons = {
      ...(previousAddons || ({} as any)),
      h5p: "1" as unknown as boolean,
      h5p_plugin_active: "0" as unknown as boolean,
    };

    expect(isH5pEnabled()).toBe(true);
    expect(isH5pPluginActive()).toBe(false);
    const h5pPluginAvailable = isH5pPluginActive();
    expect(
      isInteractiveQuizEditingAvailable({
        contentType: "tutor_h5p_quiz",
        contract: "v4",
        h5pPluginAvailable,
      })
    ).toBe(false);
    expect(
      shouldBlockQuizSettingsEditing({
        contentType: "tutor_h5p_quiz",
        contract: "v4",
        h5pPluginAvailable,
      })
    ).toBe(true);
  });
});
