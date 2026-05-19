/**
 * Localized boot data from PHP (wp_localize_script uses sanitize_key on the object name).
 */
const LOCALIZED_GLOBAL = 'theme_skin_switcher';

export function getSkinSwitcherBootData() {
	return window[LOCALIZED_GLOBAL] || {};
}
