/**
 * Apply a Theme skin switch in the editor without reloading the page.
 */
import { select, subscribe } from '@wordpress/data';
import { getSkinSwitcherBootData } from './boot-data';

const SKIN_STYLE_LINK_ID = 'theme-skin-switcher-active-skin-style';
const SKIN_FONT_LINK_ID  = 'theme-skin-switcher-active-skin-font';
const SKIN_CUSTOM_VARS_ID = 'theme-skin-switcher-custom-theme-properties';
const SKIN_VARIATION_JSON_CSS_ID = 'theme-skin-switcher-variation-theme-json';
const SITE_CUSTOM_VARS_ID = 'theme-skin-switcher-site-custom-properties';
const SKIN_BODY_PREFIX   = 'theme-skin-';
// Matches compiled skin stylesheets under build/design-skins/{slug}/style.css.
const SKIN_CSS_PATTERN   = /\/build\/design-skins\/[a-z0-9-]+\/style\.css/i;
// Any Google Fonts CSS API v2 bundle (skin switcher replaces the active skin link).
const SKIN_FONT_PATTERN  = /fonts\.googleapis\.com\/css2\?/i;
const HREF_ATTR          = 'data-theme-skin-href';
// Per-block skin stylesheets from Kitaris BlockAssets (<link> or inlined <style>).
const BLOCK_ASSET_ID_PREFIX = 'kitaris-block-assets-skin-';

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

/**
 * Documents that render block content: same-origin iframes (the editor canvas
 * and previews), plus the outer admin page only when the editor canvas is not
 * iframed. With an iframed canvas, canvas CSS must stay off the outer page —
 * WordPress copies canvas-looking styles from it into the iframe with a
 * console warning.
 *
 * @return {Document[]}
 */
function getCanvasDocuments() {
	const docs = new Set();

	document.querySelectorAll('iframe').forEach((frame) => {
		try {
			if (frame.contentDocument?.head) {
				docs.add(frame.contentDocument);
			}
		} catch {
			// Cross-origin iframe — skip.
		}
	});

	if (!document.querySelector('iframe[name="editor-canvas"]')) {
		docs.add(document);
	}

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

/**
 * Swap per-block skin stylesheets: drop the previous skin's (linked or
 * inlined by core) and add the active skin's that are not on the page yet.
 *
 * @param {Document} doc
 * @param {Array<{id: string, href: string}>} styles Active skin's styles.
 */
export function syncBlockAssetStyles(doc, styles = []) {
	const head = doc?.head;

	if (!head) {
		return;
	}

	const keep = new Set();
	styles.forEach(({ id }) => {
		keep.add(`${id}-css`);
		keep.add(`${id}-inline-css`);
	});

	doc.querySelectorAll(`[id^="${BLOCK_ASSET_ID_PREFIX}"]`).forEach((node) => {
		if (!keep.has(node.id)) {
			node.remove();
		}
	});

	styles.forEach(({ id, href }) => {
		if (!href || doc.getElementById(`${id}-css`) || doc.getElementById(`${id}-inline-css`)) {
			return;
		}

		const link = doc.createElement('link');
		link.id = `${id}-css`;
		link.rel = 'stylesheet';
		link.href = href;
		head.appendChild(link);
	});
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

/**
 * A theme.json key as core names it in a CSS variable (_wp_to_kebab_case:
 * lineHeight → line-height, h1 → h-1).
 *
 * @param {string} key
 * @return {string}
 */
function toKebabCase(key) {
	return String(key)
		.replace(/([a-z])([A-Z])/g, '$1-$2')
		.replace(/([a-zA-Z])(\d)/g, '$1-$2')
		.replace(/(\d)([a-zA-Z])/g, '$1-$2')
		.replace(/[\s_/]+/g, '-')
		.toLowerCase();
}

/**
 * settings.custom as CSS variables, named as core and the server's
 * flatten_theme_json_custom_to_css_vars() name them. Values that could close
 * the rule or the style element are left out.
 *
 * @param {Object}   custom settings.custom (or a group inside it).
 * @param {string[]} path   Variable path so far.
 * @return {Object<string, string>} Variable name → value.
 */
export function customPropertiesFrom(custom, path = []) {
	const out = {};

	if (!custom || typeof custom !== 'object' || Array.isArray(custom)) {
		return out;
	}

	Object.entries(custom).forEach(([key, value]) => {
		const segments = [...path, toKebabCase(key)];

		if (value && typeof value === 'object' && !Array.isArray(value)) {
			Object.assign(out, customPropertiesFrom(value, segments));
			return;
		}

		if (typeof value !== 'string' && typeof value !== 'number') {
			return;
		}

		const text = String(value).trim();

		if (text === '' || /[<>{};]/.test(text)) {
			return;
		}

		out[`--wp--custom--${segments.join('--')}`] = text;
	});

	return out;
}

/**
 * The site's own settings.custom from the editor's global styles record
 * (what the Styles panel and agents change), unsaved edits included.
 *
 * @return {Object|undefined}
 */
function siteCustomSettings() {
	const core = select('core');
	const id = core?.__experimentalGetCurrentGlobalStylesId?.();
	const record = id ? core.getEditedEntityRecord?.('root', 'globalStyles', id) : null;

	return record?.settings?.custom;
}

/**
 * The site's own tokens after the skin's: the skin's canvas CSS has to beat
 * core's :root to preview a skin switch, so on its own it would also hide
 * every token the site sets over the skin (core's own order: the site's
 * styles over the theme and its skin), unsaved edits included.
 *
 * @param {Document} doc
 * @param {string}   bodyClass Active skin's body class.
 */
function applySiteCustomProperties(doc, bodyClass) {
	const head = doc?.head;

	if (!head) {
		return;
	}

	doc.getElementById(SITE_CUSTOM_VARS_ID)?.remove();

	if (!bodyClass || !/^theme-skin-[a-z0-9-]+$/.test(bodyClass)) {
		return;
	}

	const decls = Object.entries(customPropertiesFrom(siteCustomSettings()))
		.map(([key, val]) => `${key}: ${val};`)
		.join('');

	if (!decls) {
		return;
	}

	const el = doc.createElement('style');
	el.id = SITE_CUSTOM_VARS_ID;
	el.textContent = `:root, body.${bodyClass}, .editor-styles-wrapper{${decls}}`;
	head.appendChild(el);
}

let siteCustomSubscribed = false;

/**
 * Keep the site's tokens over the skin's as the editor's global styles
 * change (the Styles panel, an agent, undo).
 */
export function initSiteCustomProperties() {
	if (siteCustomSubscribed) {
		return;
	}

	siteCustomSubscribed = true;
	let last = null;

	subscribe(() => {
		const bodyClass = lastAppliedPayload?.bodyClass;
		const key = JSON.stringify([bodyClass || '', siteCustomSettings() || null]);

		if (!bodyClass || key === last) {
			return;
		}

		last = key;
		getCanvasDocuments().forEach((doc) => applySiteCustomProperties(doc, bodyClass));
	});
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
		blockAssetStyles,
	} = payload;
	const bust = Boolean(slugChanged);
	const canvasDocs = getCanvasDocuments();

	// The outer admin page always gets the font (sidebar previews) and body
	// class, as on a full load; canvas CSS only when it is the canvas.
	new Set([document, ...canvasDocs]).forEach((doc) => {
		if (slugChanged) {
			removeCompetingSkinAssets(doc, {
				keepStyleHref: styleCssUrl,
				keepFontHref: fontCssUrl,
			});
		}

		upsertStylesheet(doc, SKIN_FONT_LINK_ID, fontCssUrl, { bust });
		swapBodyClasses(doc, bodyClass);

		if (!canvasDocs.includes(doc)) {
			[SKIN_STYLE_LINK_ID, SKIN_CUSTOM_VARS_ID, SKIN_VARIATION_JSON_CSS_ID, SITE_CUSTOM_VARS_ID].forEach((id) =>
				doc.getElementById(id)?.remove()
			);
			return;
		}

		upsertStylesheet(doc, SKIN_STYLE_LINK_ID, styleCssUrl, { bust });
		// variation.json tokens must reach every canvas document (WP 7.0+ canvases are iframed).
		applyCustomThemeProperties(doc, bodyClass, customThemeProperties);
		applyVariationThemeJsonCss(doc, bodyClass, variationThemeJsonCss);
		syncBlockAssetStyles(doc, blockAssetStyles);
		// Last, so the site's own tokens stay over the skin's.
		applySiteCustomProperties(doc, bodyClass);
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
