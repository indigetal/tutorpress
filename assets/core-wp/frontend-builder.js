wp.domReady(() => {
    const { createElement: el, useState, useEffect } = wp.element;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { select, dispatch } = wp.data;
    const { registerPlugin } = wp.plugins;
    const { __ } = wp.i18n;

    const CourseSidebar = () => {
        const postId = select("core/editor").getCurrentPostId();
        const [meta, setMeta] = useState({});
        const [levels, setLevels] = useState({});
        const [product, setProduct] = useState(null);

        // Fetch Course Metadata
        useEffect(() => {
            wp.apiFetch({ path: `/wp-json/tutorpress/v1/course/meta?post_id=${postId}` })
                .then(setMeta)
                .catch(console.error);

            wp.apiFetch({ path: "/wp-json/tutorpress/v1/course/levels" })
                .then((data) => {
                    setLevels(data);
                })
                .catch(console.error);

            // Fetch Course Fully Booked Status
            wp.apiFetch({ path: `/wp-json/tutorpress/v1/course-availability/${postId}` })
                .then((data) => {
                    setMeta((prev) => ({ ...prev, fullyBooked: data.fully_booked }));
                })
                .catch(console.error);

            // Fetch Course Video Metadata
            wp.apiFetch({ path: `/wp-json/tutorpress/v1/course/meta?post_id=${postId}&meta_key=_video` })
                .then((data) => {
                    setMeta((prev) => ({
                        ...prev,
                        _tutor_course_video: data.value?.source_external_url || "",
                    }));
                })
                .catch(console.error);

            // Fetch Course Duration
            wp.apiFetch({ path: `/wp-json/tutorpress/v1/course-duration/${postId}` })
                .then((data) => {
                    setMeta((prev) => ({
                        ...prev,
                        _tutor_course_duration: data ?? { hours: 0, minutes: 0 },
                    }));
                })
                .catch(console.error);
        }, [postId]);

        // Fetch WooCommerce Product Data
        useEffect(() => {
            if (meta._tutor_wc_product_id && !isNaN(meta._tutor_wc_product_id)) {
                wp.apiFetch({ path: `/wp-json/tutorpress/v1/wc-product/${meta._tutor_wc_product_id}` })
                    .then(setProduct)
                    .catch(console.error);
            }
        }, [meta._tutor_wc_product_id]);

        // Update Course Metadata via REST API
        const updateMeta = (key, value) => {
            const newMeta = { ...meta, [key]: value };
            setMeta(newMeta);

            // Update the editor state immediately
            dispatch("core/editor").editPost({ meta: newMeta });

            if (key === "_tutor_course_video") {
                wp.apiFetch({
                    path: "/wp-json/tutorpress/v1/course-media/update",
                    method: "PUT",
                    data: { post_id: postId, video_url: value },
                }).catch(console.error);
            } else if (key === "_tutor_course_duration") {
                wp.apiFetch({
                    path: "/wp-json/tutorpress/v1/course-duration/update",
                    method: "PUT",
                    data: { post_id: postId, duration: value },
                }).catch(console.error);
            } else {
                wp.apiFetch({
                    path: "/wp-json/tutorpress/v1/course/meta",
                    method: "PUT",
                    data: { post_id: postId, meta_key: key, value },
                }).catch(console.error);
            }
        };

        return (
            <PluginDocumentSettingPanel name="course-sidebar" title={__("Course Settings", "tutor")}>
                {/* Course Difficulty */}
                <label>{__("Course Difficulty", "tutor")}</label>
                <select
                    value={meta._tutor_course_level || "intermediate"}
                    onChange={(e) => updateMeta("_tutor_course_level", e.target.value)}
                >
                    {Object.entries(levels).map(([key, label]) => (
                        <option key={key} value={key}>
                            {label}
                        </option>
                    ))}
                </select>

                {/* Public Course Toggle */}
                <label>{__("Public Course", "tutor")}</label>
                <input
                    type="checkbox"
                    checked={Boolean(meta._tutor_is_public_course)}
                    onChange={(e) => updateMeta("_tutor_is_public_course", e.target.checked)}
                />

                {/* WooCommerce Product Selection */}
                <label>{__("Linked WooCommerce Product", "tutor")}</label>
                <input
                    type="number"
                    value={meta._tutor_wc_product_id || ""}
                    onChange={(e) => {
                        const productId = parseInt(e.target.value) || "";
                        if (productId !== "") {
                            updateMeta("_tutor_wc_product_id", productId);
                        }
                    }}
                />
                {product && (
                    <div>
                        <p>
                            {__("Product Name:", "tutor")} {product.name}
                        </p>
                        <p>
                            {__("Regular Price:", "tutor")} {product.regular_price}
                        </p>
                        <p>
                            {__("Sale Price:", "tutor")} {product.sale_price}
                        </p>
                    </div>
                )}

                {/* What Will I Learn */}
                <label>{__("What Will I Learn?", "tutor")}</label>
                <textarea
                    value={meta._tutor_course_benefits || ""}
                    onChange={(e) => updateMeta("_tutor_course_benefits", e.target.value)}
                />

                {/* Target Audience */}
                <label>{__("Target Audience", "tutor")}</label>
                <textarea
                    value={meta._tutor_course_target_audience || ""}
                    onChange={(e) => updateMeta("_tutor_course_target_audience", e.target.value)}
                />

                {/* Requirements/Instructions */}
                <label>{__("Requirements/Instructions", "tutor")}</label>
                <textarea
                    value={meta._tutor_course_requirements || ""}
                    onChange={(e) => updateMeta("_tutor_course_requirements", e.target.value)}
                />

                {/* Materials Included */}
                <label>{__("Materials Included", "tutor")}</label>
                <textarea
                    value={meta._tutor_course_material_includes || ""}
                    onChange={(e) => updateMeta("_tutor_course_material_includes", e.target.value)}
                />

                {/* Course Video URL */}
                <label>{__("Course Video URL", "tutor")}</label>
                <input
                    type="url"
                    value={meta._tutor_course_video || ""}
                    onChange={(e) => updateMeta("_tutor_course_video", e.target.value)}
                />

                {/* Course Duration */}
                <label>{__("Course Duration", "tutor")}</label>
                <div style={{ display: "flex", gap: "10px" }}>
                    {/* Hours Input */}
                    <input
                        type="number"
                        min="0"
                        value={meta._tutor_course_duration?.hours || 0}
                        onChange={(e) => {
                            const updatedDuration = {
                                hours: parseInt(e.target.value) || 0,
                                minutes: meta._tutor_course_duration?.minutes || 0,
                            };
                            setMeta((prev) => ({ ...prev, _tutor_course_duration: updatedDuration }));

                            clearTimeout(window.durationTimeout);
                            window.durationTimeout = setTimeout(() => {
                                updateMeta("_tutor_course_duration", updatedDuration);
                            }, 500);
                        }}
                    />
                    <span>{__("Hours", "tutor")}</span>

                    {/* Minutes Input */}
                    <input
                        type="number"
                        min="0"
                        max="59"
                        value={meta._tutor_course_duration?.minutes || 0}
                        onChange={(e) => {
                            const updatedDuration = {
                                hours: meta._tutor_course_duration?.hours || 0,
                                minutes: parseInt(e.target.value) || 0,
                            };
                            setMeta((prev) => ({ ...prev, _tutor_course_duration: updatedDuration }));

                            clearTimeout(window.durationTimeout);
                            window.durationTimeout = setTimeout(() => {
                                updateMeta("_tutor_course_duration", updatedDuration);
                            }, 500);
                        }}
                    />
                    <span>{__("Minutes", "tutor")}</span>
                </div>
            </PluginDocumentSettingPanel>
        );
    };

    registerPlugin("course-sidebar", { render: CourseSidebar });
});
