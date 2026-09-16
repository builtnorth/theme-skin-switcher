const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const { filesystemCache } = require("../../webpack.shared.config");

const configs = Array.isArray(defaultConfig) ? defaultConfig : [defaultConfig];
const baseConfig = configs[0];

module.exports = {
	...baseConfig,
	cache: filesystemCache(__dirname),
	entry: {
		"/assets/editor/editor": "./src/assets/js/editor/editor.js",
	},
};
