const { merge } = require("webpack-merge");
const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const DependencyExtractionWebpackPlugin = require("@wordpress/dependency-extraction-webpack-plugin");
const path = require("path");
const webpack = require('webpack');

module.exports = merge(defaultConfig, {
  output: {
    path: path.resolve(process.cwd(), "plugin/build"),
  },
  entry: {
    scan: path.resolve(process.cwd(), "src/js", "scan.js"),
  },
  plugins: [
    ...defaultConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.name !== "DependencyExtractionWebpackPlugin"
    ),
    new DependencyExtractionWebpackPlugin(),
    new webpack.ProvidePlugin({
      Buffer: ['buffer', 'Buffer'],
    }),
  ],
  resolve: {
    fallback: {
      buffer: require.resolve("buffer/"),
    }
  }
});