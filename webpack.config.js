const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
  ...defaultConfig,
  entry: {
    index: path.resolve(__dirname, "assets/js/src/index.tsx"),
  },
  output: {
    path: path.resolve(__dirname, "assets/js/build"),
    filename: "[name].js",
  },
};
