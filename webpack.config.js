const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const { filesystemCache, sharedWatchOptions } = require("../../webpack.shared.config");

const configs = Array.isArray(defaultConfig) ? defaultConfig : [defaultConfig];
const baseConfig = configs[0];

module.exports = {
	...baseConfig,
	cache: filesystemCache(__dirname),
	watchOptions: sharedWatchOptions(baseConfig.watchOptions),
	entry: {
		"/assets/editor/editor": "./src/assets/js/editor/editor.js",
	},
};
