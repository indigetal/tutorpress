module.exports = {
  plugins: [
    require("postcss-import"),
    require("postcss-import-ext-glob"),
    require("postcss-preset-env")({
      stage: 2, // Enable stable modern CSS features
      features: {
        "nesting-rules": true,
        "custom-properties": true,
      },
    }),
    require("autoprefixer"),
    ...(process.env.NODE_ENV === "production" ? [require("cssnano")] : []),
  ],
};
