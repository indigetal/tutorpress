wp.domReady(() => {
    const { createElement: el, useState, useEffect } = wp.element;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { select, dispatch } = wp.data;
    const { __ } = wp.i18n;

    const CourseMonetization = () => {
        const postId = select("core/editor").getCurrentPostId();
        const [meta, setMeta] = useState({});
        const [product, setProduct] = useState(null);

        // Fetch Course Metadata
        useEffect(() => {
            wp.apiFetch({ path: `/wp-json/tutorpress/v1/course/meta?post_id=${postId}` })
                .then(setMeta)
                .catch(console.error);
        }, [postId]);

        // Fetch WooCommerce Product Data
        useEffect(() => {
            if (meta._tutor_wc_product_id) {
                wp.apiFetch({
                    path: `/wp-json/tutorpress/v1/course/product?product_id=${meta._tutor_wc_product_id}&post_id=${postId}`,
                })
                    .then(setProduct)
                    .catch(console.error);
            }
        }, [meta._tutor_wc_product_id, postId]);

        // Update Course Metadata via REST API
        const updateMeta = (key, value) => {
            const newMeta = { ...meta, [key]: value };
            setMeta(newMeta);
            dispatch("core/editor").editPost({ meta: newMeta });

            wp.apiFetch({
                path: "/wp-json/tutorpress/v1/course/meta",
                method: "PUT",
                data: { post_id: postId, meta_key: key, value },
            }).catch(console.error);
        };

        return (
            <PluginDocumentSettingPanel name="course-monetization" title={__("Course Monetization", "tutor")}>
                {/* WooCommerce Product Selection */}
                <label>{__("Linked WooCommerce Product", "tutor")}</label>
                <input
                    type="number"
                    value={meta._tutor_wc_product_id || ""}
                    onChange={(e) => updateMeta("_tutor_wc_product_id", e.target.value)}
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
            </PluginDocumentSettingPanel>
        );
    };

    wp.plugins.registerPlugin("course-monetization", { render: CourseMonetization });
});
