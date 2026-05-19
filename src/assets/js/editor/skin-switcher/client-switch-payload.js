/**
 * Build a skin switch payload from preloaded boot data (instant apply).
 *
 * @param {string} slug
 * @param {string} previousSlug
 * @param {Object} boot
 * @return {Object|null}
 */
export function getSwitchPayload(slug, previousSlug, boot) {
	const template =
		boot?.switchPayloads?.[slug] || buildFallbackPayload(slug, boot);

	if (!template) {
		return null;
	}

	const bodyClass = template.bodyClass || `compass-waas-style-${slug}`;

	return {
		...template,
		slug,
		previousSlug: previousSlug || '',
		bodyClass,
		previousBodyClass: previousSlug
			? `compass-waas-style-${previousSlug}`
			: '',
	};
}

/**
 * @param {string} slug
 * @param {Object} boot
 * @return {Object|null}
 */
function buildFallbackPayload(slug, boot) {
	const themeUrl = boot?.themeUrl || '';

	if (!themeUrl || !slug) {
		return null;
	}

	const styleCssUrl = `${themeUrl}build/design-skins/${slug}/style.css`;
	const fontQuery = boot?.styleFonts?.[slug] || '';
	const fontCssUrl = fontQuery
		? `https://fonts.googleapis.com/css2?family=${fontQuery}&display=swap`
		: '';

	return {
		bodyClass: `compass-waas-style-${slug}`,
		styleCssUrl,
		fontCssUrl,
		editorExperience: {
			active_style: {
				slug,
				assets_url: `${themeUrl}design-skins/${slug}/`,
			},
		},
	};
}

/**
 * @param {Object} boot
 * @param {Object} response
 */
export function mergeBootFromResponse(boot, response) {
	if (!boot || !response) {
		return;
	}

	if (response.styles?.length) {
		boot.styles = response.styles;
	}

	if (response.switchPayloads) {
		boot.switchPayloads = {
			...(boot.switchPayloads || {}),
			...response.switchPayloads,
		};
	}

	if (response.themeUrl) {
		boot.themeUrl = response.themeUrl;
	}
}
