/**
 * Apply a Theme skin switch in the editor without reloading the page.
 */
import { getSkinSwitcherBootData } from './boot-data';

const SKIN_STYLE_LINK_ID = 'theme-skin-switcher-active-skin-style';
const SKIN_FONT_LINK_ID  = 'theme-skin-switcher-active-skin-font';
const SKIN_CUSTOM_VARS_ID = 'theme-skin-switcher-custom-theme-properties';
const SKIN_VARIATION_JSON_CSS_ID = 'theme-skin-switcher-variation-theme-json';
const SKIN_BODY_PREFIX   = 'theme-skin-';
// Matches compiled skin stylesheets under build/design-skins/{slug}/style.css.
const SKIN_CSS_PATTERN   = /\/build\/design-skins\/[a-z0-9-]+\/style\.css/i;
// Any Google Fonts CSS API v2 bundle (skin switcher replaces the active skin link).
const SKIN_FONT_PATTERN  = /fonts\.googleapis\.com\/css2\?/i;
const HREF_ATTR          = 'data-theme-skin-href';

export const SKIN_CHANGED_EVENT = 'themeSkinSwitcherChanged';

let lastAppliedPayload = null;
let lastAppliedSlug = '';
let iframeObserver = null;
let persistencePausedUntil = 0;
let reapplyDebounceTimer = null;

export function isSkinPersistencePaused() {
	return Date.now() < persistencePausedUntil;
}

/**
 * Slug last applied to the editor shell + canvas (source of truth vs React boot/REST).
 *
 * @return {string}
 */
export function getLastAppliedSkinSlug() {
	return lastAppliedSlug;
}

function pausePersistence(ms = 2500) {
	persistencePausedUntil = Date.now() + ms;
}

function getTargetDocuments() {
	const docs = new Set([document]);

	document.querySelectorAll('iframe').forEach((frame) => {
		try {
			if (frame.contentDocument?.head) {
				docs.add(frame.contentDocument);
			}
		} catch {
			// Cross-origin iframe — skip.
		}
	});

	return [...docs];
}

function cacheBust(url) {
	if (!url) {
		return '';
	}

	const separator = url.includes('?') ? '&' : '?';

	return `${url}${separator}ver=${Date.now()}`;
}

function normalizeStylesheetHref(href) {
	if (!href) {
		return '';
	}

	try {
		return new URL(href, window.location.origin).pathname.replace(/\/+/g, '/');
	} catch {
		return href.split('?')[0].replace(/\/+/g, '/');
	}
}

/**
 * Remove other skins' stylesheets. Keep the active skin URL so PHP-enqueued
 * canvas links are not stripped if client reinjection fails or races the iframe.
 */
function removeCompetingSkinAssets(doc, { keepStyleHref = '', keepFontHref = '' } = {}) {
	if (!doc) {
		return;
	}

	const keepStylePath = normalizeStylesheetHref(keepStyleHref);
	const keepFontPath = normalizeStylesheetHref(keepFontHref);

	doc.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
		if (link.id === SKIN_STYLE_LINK_ID || link.id === SKIN_FONT_LINK_ID) {
			return;
		}

		const href = link.getAttribute('href') || '';

		if (SKIN_CSS_PATTERN.test(href)) {
			if (keepStylePath && normalizeStylesheetHref(href) === keepStylePath) {
				return;
			}

			link.remove();
			return;
		}

		if (SKIN_FONT_PATTERN.test(href)) {
			if (keepFontPath && normalizeStylesheetHref(href) === keepFontPath) {
				return;
			}

			link.remove();
		}
	});
}

function upsertStylesheet(doc, linkId, href, { bust = false } = {}) {
	const head = doc?.head;

	if (!head) {
		return;
	}

	let link = doc.getElementById(linkId);

	if (!href) {
		link?.remove();
		return;
	}

	if (!link) {
		link = doc.createElement('link');
		link.id = linkId;
		link.rel = 'stylesheet';
		head.appendChild(link);
	}

	const storedHref = link.getAttribute(HREF_ATTR) || '';

	if (!bust && storedHref === href) {
		return;
	}

	link.setAttribute(HREF_ATTR, href);
	link.href = bust ? cacheBust(href) : href;
}

function swapBodyClasses(doc, bodyClass) {
	const body = doc?.body;

	if (!body) {
		return;
	}

	body.classList.forEach((className) => {
		if (className.startsWith(SKIN_BODY_PREFIX)) {
			body.classList.remove(className);
		}
	});

	if (bodyClass) {
		body.classList.add(bodyClass);
	}
}

/**
 * Mirror merged theme.json settings.custom as CSS variables.
 * WordPress prints the initial skin on :root at iframe load; without overriding
 * :root (and the canvas wrapper), token-dependent block styles (buttons, images)
 * keep the previous skin until save + full reload.
 */
function applyCustomThemeProperties(doc, bodyClass, properties) {
	const head = doc?.head;

	if (!head) {
		return;
	}

	doc.getElementById(SKIN_CUSTOM_VARS_ID)?.remove();

	if (!bodyClass || !properties || typeof properties !== 'object') {
		return;
	}

	if (!/^theme-skin-[a-z0-9-]+$/.test(bodyClass)) {
		return;
	}

	const decls = Object.entries(properties)
		.map(([key, val]) => {
			if (val == null || val === '') {
				return '';
			}

			return `${key}: ${String(val)};`;
		})
		.filter(Boolean)
		.join('');

	if (!decls) {
		return;
	}

	const el = doc.createElement('style');
	el.id = SKIN_CUSTOM_VARS_ID;
	el.textContent = `:root, body.${bodyClass}, .editor-styles-wrapper{${decls}}`;
	head.appendChild(el);
}

/**
 * Scope WP-compiled variation.json CSS to the canvas (same targets as custom tokens).
 *
 * @param {string} css Raw stylesheet from the server (typically :root{...} rules).
 * @param {string} bodyClass
 * @return {string}
 */
function scopeVariationThemeJsonCss(css, bodyClass) {
	if (!css || !bodyClass) {
		return '';
	}

	const scope = `:root, body.${bodyClass}, .editor-styles-wrapper`;

	return css.replace(/:root\s*\{/g, `${scope}{`);
}

/**
 * Apply CSS compiled from variation.json (styles, presets, settings beyond custom flattening).
 *
 * @param {Document} doc
 * @param {string} bodyClass
 * @param {string} css
 */
function applyVariationThemeJsonCss(doc, bodyClass, css) {
	const head = doc?.head;

	if (!head) {
		return;
	}

	doc.getElementById(SKIN_VARIATION_JSON_CSS_ID)?.remove();

	if (!bodyClass || !css || typeof css !== 'string') {
		return;
	}

	if (!/^theme-skin-[a-z0-9-]+$/.test(bodyClass)) {
		return;
	}

	const scoped = scopeVariationThemeJsonCss(css.trim(), bodyClass);

	if (!scoped) {
		return;
	}

	const el = doc.createElement('style');
	el.id = SKIN_VARIATION_JSON_CSS_ID;
	el.textContent = scoped;
	head.appendChild(el);
}

export function mergeEditorExperience(patch) {
	if (!patch || typeof patch !== 'object') {
		return;
	}

	window.kitaris_localize = window.kitaris_localize || {};
	window.kitaris_localize.blocks = window.kitaris_localize.blocks || {};

	const current = window.kitaris_localize.blocks.editor_experience || {};

	const merged = {
		...current,
		...patch,
		active_style: patch.active_style || current.active_style,
	};

	if (patch.patterns) {
		// The payload holds the skin's full pattern config (theme baseline + skin), so it
		// replaces the previous skin's rather than merging over it.
		merged.patterns = patch.patterns;
	} else {
		merged.patterns = current.patterns;
	}

	if (patch.section_divider) {
		merged.section_divider = {
			...(current.section_divider || {}),
			...patch.section_divider,
		};

		if (Object.prototype.hasOwnProperty.call(patch.section_divider, 'divider_directory')) {
			merged.section_divider.divider_directory =
				patch.section_divider.divider_directory;
		}
	} else {
		merged.section_divider = current.section_divider;
	}

	window.kitaris_localize.blocks.editor_experience = merged;
}

/**
 * @param {Object} payload
 * @param {{ slugChanged?: boolean }} options
 */
export function applyToDocuments(payload, { slugChanged = false } = {}) {
	if (!payload?.slug) {
		return;
	}

	const {
		bodyClass,
		styleCssUrl,
		fontCssUrl,
		customThemeProperties,
		variationThemeJsonCss,
	} = payload;
	const bust = Boolean(slugChanged);

	getTargetDocuments().forEach((doc) => {
		if (slugChanged) {
			removeCompetingSkinAssets(doc, {
				keepStyleHref: styleCssUrl,
				keepFontHref: fontCssUrl,
			});
		}

		upsertStylesheet(doc, SKIN_STYLE_LINK_ID, styleCssUrl, { bust });
		upsertStylesheet(doc, SKIN_FONT_LINK_ID, fontCssUrl, { bust });
		swapBodyClasses(doc, bodyClass);
		// WP 7.0+ Site Editor canvas is iframed; variation.json tokens must reach every document.
		applyCustomThemeProperties(doc, bodyClass, customThemeProperties);
		applyVariationThemeJsonCss(doc, bodyClass, variationThemeJsonCss);
	});
}

/**
 * Re-apply last or provided payload (e.g. after iframe navigation).
 *
 * @param {Object|null} payload Optional explicit payload.
 */
export function reapplyActiveSkinToDocuments(payload = null) {
	if (isSkinPersistencePaused()) {
		return;
	}

	const active = payload || lastAppliedPayload;

	if (!active?.slug) {
		return;
	}

	applyToDocuments(
		{
			...active,
			previousBodyClass: '',
			previousSlug: '',
		},
		{ slugChanged: false }
	);
}

function scheduleDebouncedReapply() {
	if (isSkinPersistencePaused()) {
		return;
	}

	window.clearTimeout(reapplyDebounceTimer);
	reapplyDebounceTimer = window.setTimeout(() => {
		reapplyActiveSkinToDocuments();
	}, 120);
}

function scheduleIframeReapply() {
	if (!lastAppliedPayload || isSkinPersistencePaused()) {
		return;
	}

	[400, 1200].forEach((delay) => {
		window.setTimeout(() => {
			if (!isSkinPersistencePaused()) {
				reapplyActiveSkinToDocuments();
			}
		}, delay);
	});
}

function ensureIframeObserver() {
	if (iframeObserver) {
		return;
	}

	iframeObserver = new MutationObserver((mutations) => {
		if (!lastAppliedPayload || isSkinPersistencePaused()) {
			return;
		}

		const iframeChanged = mutations.some((mutation) =>
			[...mutation.addedNodes].some(
				(node) =>
					node.nodeName === 'IFRAME' ||
					node.querySelector?.('iframe')
			)
		);

		if (iframeChanged) {
			scheduleDebouncedReapply();
		}
	});

	iframeObserver.observe(document.body, {
		childList: true,
		subtree: true,
	});
}

/**
 * @param {Object} payload Switch payload (boot or REST).
 */
export function applySkinClientSide(payload) {
	if (!payload?.slug) {
		return payload;
	}

	const { slug, previousSlug, editorExperience } = payload;
	// Initial boot: lastAppliedSlug is empty — do not strip PHP-enqueued canvas styles.
	const slugChanged =
		lastAppliedSlug !== '' && slug !== lastAppliedSlug;

	if (slugChanged) {
		pausePersistence();
	}

	lastAppliedSlug = slug;
	lastAppliedPayload = {
		...payload,
		previousSlug: previousSlug || '',
	};

	const boot = getSkinSwitcherBootData();

	if (boot) {
		boot.activeSlug = slug;
	}

	mergeEditorExperience(editorExperience);
	applyToDocuments(lastAppliedPayload, { slugChanged });
	ensureIframeObserver();

	if (slugChanged) {
		scheduleIframeReapply();
	}

	document.dispatchEvent(
		new CustomEvent(SKIN_CHANGED_EVENT, {
			detail: {
				slug,
				previousSlug,
				payload: lastAppliedPayload,
			},
		})
	);

	return payload;
}
