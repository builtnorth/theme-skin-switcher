/**
 * Options for the design skin select.
 *
 * With no active skin (or one that no longer exists), a placeholder leads the
 * list. CustomSelectControl only reports a changed selection, so showing the
 * first skin as selected would make it impossible to choose.
 *
 * @param {Array<{key: string}>} options     Skin options.
 * @param {string}               activeSlug  Active skin slug ('' when none).
 * @param {string}               placeholder Placeholder label.
 * @return {Array<{key: string}>} Options to render.
 */
export function withNoSkinPlaceholder(options, activeSlug, placeholder) {
	if (!options.length || options.some((option) => option.key === activeSlug)) {
		return options;
	}

	return [{ key: '', name: placeholder }, ...options];
}
