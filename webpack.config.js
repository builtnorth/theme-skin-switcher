const defaultConfig = require("@wordpress/scripts/config/webpack.config");

const configs = Array.isArray(defaultConfig) ? defaultConfig : [defaultConfig];
const baseConfig = configs[0];

module.exports = {
	...baseConfig,
	entry: {
		"/assets/editor/editor": "./src/assets/js/editor/editor.js",
	},
};
