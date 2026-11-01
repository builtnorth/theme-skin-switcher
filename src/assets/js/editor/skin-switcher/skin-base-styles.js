/**
 * Hand core the theme's styles with a skin merged in, so it rebuilds the
 * editor's global stylesheet. Without this, block styles from the skin active
 * at page load stay in the canvas until a reload.
 */
import apiFetch from '@wordpress/api-fetch';
import { dispatch, select } from '@wordpress/data';
import { getSkinSwitcherBootData } from './boot-data';

const fetched = new Map();
let latestSlug = '';

/**
 * @param {string} slug The skin now showing.
 */
export async function syncBaseStyles(slug) {
	const boot = getSkinSwitcherBootData();
	const stylesheet = select('core')?.getCurrentTheme?.()?.stylesheet;

	latestSlug = slug;

	if (!slug || !stylesheet) {
		return;
	}

	try {
		if (!fetched.has(slug)) {
			fetched.set(
				slug,
				await apiFetch({
					path: `/${boot.restNamespace}${boot.restRoute}/base?slug=${encodeURIComponent(slug)}`,
				})
			);
		}
	} catch {
		// The canvas keeps its styles; a reload corrects them.
		return;
	}

	// A quicker switch may have happened during the request.
	if (slug === latestSlug) {
		dispatch('core').__experimentalReceiveThemeBaseGlobalStyles(stylesheet, fetched.get(slug));
	}
}
