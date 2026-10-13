/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/assets/js/editor/skin-switcher/SkinPreviewImage.js"
/*!****************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/SkinPreviewImage.js ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SkinPreviewImage)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__);

/**
 * Preview thumbnail beside skin names in the select list.
 */
function SkinPreviewImage({
  style
}) {
  const src = style?.previewImageUrl;
  if (!src) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("span", {
      className: "theme-skin-switcher__option-preview theme-skin-switcher__option-preview--fallback",
      "aria-hidden": "true"
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("img", {
    className: "theme-skin-switcher__option-preview",
    src: src,
    alt: "",
    width: 32,
    height: 32,
    loading: "lazy",
    decoding: "async",
    draggable: false
  });
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/SkinSwitcherRootCard.js"
/*!********************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/SkinSwitcherRootCard.js ***!
  \********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SkinSwitcherRootCard)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _useActiveSkin__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./useActiveSkin */ "./src/assets/js/editor/skin-switcher/useActiveSkin.js");
/* harmony import */ var _useApplySkin__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./useApplySkin */ "./src/assets/js/editor/skin-switcher/useApplySkin.js");
/* harmony import */ var _SkinPreviewImage__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./SkinPreviewImage */ "./src/assets/js/editor/skin-switcher/SkinPreviewImage.js");
/* harmony import */ var _skin_select_options__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./skin-select-options */ "./src/assets/js/editor/skin-switcher/skin-select-options.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * Design skin field on the Global Styles home screen.
 */








function SkinSwitcherRootCard() {
  const {
    activeSlug,
    styles,
    isLoading,
    setActiveSlug
  } = (0,_useActiveSkin__WEBPACK_IMPORTED_MODULE_3__["default"])();
  const {
    applySkin,
    error
  } = (0,_useApplySkin__WEBPACK_IMPORTED_MODULE_4__["default"])({
    onApplied: response => {
      if (response?.slug) {
        setActiveSlug(response.slug);
      }
    }
  });
  const selectOptions = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => (0,_skin_select_options__WEBPACK_IMPORTED_MODULE_6__.withNoSkinPlaceholder)(styles.map(style => ({
    key: style.slug,
    name: style.title,
    icon: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_SkinPreviewImage__WEBPACK_IMPORTED_MODULE_5__["default"], {
      style: style
    }),
    className: `theme-skin-switcher__option theme-skin-switcher__option--${style.slug}`
  })), activeSlug, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Choose a design skin', 'theme-skin-switcher')), [styles, activeSlug]);
  const selectedOption = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    if (!selectOptions.length) {
      return undefined;
    }
    return selectOptions.find(option => option.key === activeSlug) || selectOptions[0];
  }, [selectOptions, activeSlug]);
  const handleChange = ({
    selectedItem
  }) => {
    if (!selectedItem?.key) {
      return;
    }
    applySkin(selectedItem.key);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
    className: "theme-skin-switcher__field",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CustomSelectControl, {
      __next40pxDefaultSize: true,
      className: "theme-skin-switcher__select",
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Design skin', 'theme-skin-switcher'),
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Section patterns, dividers, and skin CSS. Your Global Styles colors and fonts stay as you set them.', 'theme-skin-switcher'),
      options: selectOptions,
      value: selectedOption,
      onChange: handleChange,
      disabled: isLoading || !selectOptions.length,
      showSelectedHint: true
    }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.__experimentalText, {
      as: "p",
      className: "theme-skin-switcher__error",
      role: "alert",
      children: error
    })]
  });
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/SkinSwitcherRootMount.js"
/*!*********************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/SkinSwitcherRootMount.js ***!
  \*********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SkinSwitcherRootMount)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _SkinSwitcherRootCard__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./SkinSwitcherRootCard */ "./src/assets/js/editor/skin-switcher/SkinSwitcherRootCard.js");
/* harmony import */ var _StylesAccessibilityNotice__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./StylesAccessibilityNotice */ "./src/assets/js/editor/skin-switcher/StylesAccessibilityNotice.js");
/* harmony import */ var _skin_persistence__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./skin-persistence */ "./src/assets/js/editor/skin-switcher/skin-persistence.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * Mounts skin switcher controls on the Global Styles home screen (same card as core presets).
 * Design skin at the top; ADA color guidance at the bottom.
 */






const SKIN_MOUNT_ID = 'theme-skin-switcher-root-mount';
const NOTICE_MOUNT_ID = 'theme-skin-switcher-accessibility-mount';
function findStylesRootStack() {
  // WP 7.0+: Global Styles UI moved to @wordpress/global-styles-ui (new root class).
  // Pre-7.0: .edit-site-global-styles-screen-root in @wordpress/edit-site.
  const root = document.querySelector('.global-styles-ui-screen-root') || document.querySelector('.edit-site-global-styles-screen-root');
  if (!root) {
    return null;
  }
  const cardBody = root.querySelector('.components-card__body');
  if (!cardBody) {
    return null;
  }
  return cardBody.querySelector('.components-v-stack') || cardBody.querySelector('.components-flex-gap-4') || cardBody.querySelector(':scope > div');
}
function isMountConnected(mount) {
  return Boolean(mount?.isConnected);
}
function ensureMount(stack, id, position) {
  let mount = document.getElementById(id);
  if (mount && !stack.contains(mount)) {
    mount.remove();
    mount = null;
  }
  if (!mount) {
    mount = document.createElement('div');
    mount.id = id;
    if (position === 'first') {
      stack.insertBefore(mount, stack.firstChild);
    } else {
      stack.appendChild(mount);
    }
  }
  return mount;
}
function SkinSwitcherRootMount() {
  const [skinMountNode, setSkinMountNode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [noticeMountNode, setNoticeMountNode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const tryAttach = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    const stack = findStylesRootStack();
    if (!stack) {
      return false;
    }
    setSkinMountNode(ensureMount(stack, SKIN_MOUNT_ID, 'first'));
    setNoticeMountNode(ensureMount(stack, NOTICE_MOUNT_ID, 'last'));
    (0,_skin_persistence__WEBPACK_IMPORTED_MODULE_3__.reapplyActiveSkin)();
    return true;
  }, []);
  const tryDetach = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    for (const id of [SKIN_MOUNT_ID, NOTICE_MOUNT_ID]) {
      const mount = document.getElementById(id);
      if (mount && isMountConnected(mount)) {
        mount.remove();
      }
    }
    setSkinMountNode(null);
    setNoticeMountNode(null);
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    let cancelled = false;
    let observer = null;
    let retryTimer = null;
    const sync = () => {
      if (cancelled) {
        return;
      }
      if (findStylesRootStack()) {
        tryAttach();
      } else {
        tryDetach();
      }
    };
    sync();
    observer = new MutationObserver(sync);
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
    retryTimer = window.setInterval(sync, 400);
    return () => {
      cancelled = true;
      observer?.disconnect();
      window.clearInterval(retryTimer);
      tryDetach();
    };
  }, [tryAttach, tryDetach]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [skinMountNode && isMountConnected(skinMountNode) ? (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createPortal)(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_SkinSwitcherRootCard__WEBPACK_IMPORTED_MODULE_1__["default"], {}), skinMountNode) : null, noticeMountNode && isMountConnected(noticeMountNode) ? (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createPortal)(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_StylesAccessibilityNotice__WEBPACK_IMPORTED_MODULE_2__["default"], {}), noticeMountNode) : null]
  });
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/StylesAccessibilityNotice.js"
/*!*************************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/StylesAccessibilityNotice.js ***!
  \*************************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ StylesAccessibilityNotice)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Color contrast guidance on the Global Styles home screen.
 */



const ADA_URL = "https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum";
function StylesAccessibilityNotice() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Notice, {
    className: "theme-skin-switcher-accessibility-notice",
    status: "warning",
    isDismissible: false,
    children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Colors are paired as name and name-contrast (e.g. primary and primary-contrast for text on primary background). When changing to custom colors, set enough contrast in each pair so text stays readable.", "theme-skin-switcher"), " ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.ExternalLink, {
      href: ADA_URL,
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("More about WCAG 2.2 guidelines", "theme-skin-switcher")
    })]
  });
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/apply-skin-client.js"
/*!*****************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/apply-skin-client.js ***!
  \*****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SKIN_CHANGED_EVENT: () => (/* binding */ SKIN_CHANGED_EVENT),
/* harmony export */   applySkinClientSide: () => (/* binding */ applySkinClientSide),
/* harmony export */   applyToDocuments: () => (/* binding */ applyToDocuments),
/* harmony export */   getLastAppliedSkinSlug: () => (/* binding */ getLastAppliedSkinSlug),
/* harmony export */   isSkinPersistencePaused: () => (/* binding */ isSkinPersistencePaused),
/* harmony export */   mergeEditorExperience: () => (/* binding */ mergeEditorExperience),
/* harmony export */   reapplyActiveSkinToDocuments: () => (/* binding */ reapplyActiveSkinToDocuments)
/* harmony export */ });
/* harmony import */ var _boot_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./boot-data */ "./src/assets/js/editor/skin-switcher/boot-data.js");
/**
 * Apply a Theme skin switch in the editor without reloading the page.
 */

const SKIN_STYLE_LINK_ID = 'theme-skin-switcher-active-skin-style';
const SKIN_FONT_LINK_ID = 'theme-skin-switcher-active-skin-font';
const SKIN_CUSTOM_VARS_ID = 'theme-skin-switcher-custom-theme-properties';
const SKIN_VARIATION_JSON_CSS_ID = 'theme-skin-switcher-variation-theme-json';
const SKIN_BODY_PREFIX = 'theme-skin-';
// Matches compiled skin stylesheets under build/design-skins/{slug}/style.css.
const SKIN_CSS_PATTERN = /\/build\/design-skins\/[a-z0-9-]+\/style\.css/i;
// Any Google Fonts CSS API v2 bundle (skin switcher replaces the active skin link).
const SKIN_FONT_PATTERN = /fonts\.googleapis\.com\/css2\?/i;
const HREF_ATTR = 'data-theme-skin-href';
const SKIN_CHANGED_EVENT = 'themeSkinSwitcherChanged';
let lastAppliedPayload = null;
let lastAppliedSlug = '';
let iframeObserver = null;
let persistencePausedUntil = 0;
let reapplyDebounceTimer = null;
function isSkinPersistencePaused() {
  return Date.now() < persistencePausedUntil;
}

/**
 * Slug last applied to the editor shell + canvas (source of truth vs React boot/REST).
 *
 * @return {string}
 */
function getLastAppliedSkinSlug() {
  return lastAppliedSlug;
}
function pausePersistence(ms = 2500) {
  persistencePausedUntil = Date.now() + ms;
}
function getTargetDocuments() {
  const docs = new Set([document]);
  document.querySelectorAll('iframe').forEach(frame => {
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
function removeCompetingSkinAssets(doc, {
  keepStyleHref = '',
  keepFontHref = ''
} = {}) {
  if (!doc) {
    return;
  }
  const keepStylePath = normalizeStylesheetHref(keepStyleHref);
  const keepFontPath = normalizeStylesheetHref(keepFontHref);
  doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
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
function upsertStylesheet(doc, linkId, href, {
  bust = false
} = {}) {
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
  body.classList.forEach(className => {
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
  const decls = Object.entries(properties).map(([key, val]) => {
    if (val == null || val === '') {
      return '';
    }
    return `${key}: ${String(val)};`;
  }).filter(Boolean).join('');
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
function mergeEditorExperience(patch) {
  if (!patch || typeof patch !== 'object') {
    return;
  }
  window.kitaris_localize = window.kitaris_localize || {};
  window.kitaris_localize.blocks = window.kitaris_localize.blocks || {};
  const current = window.kitaris_localize.blocks.editor_experience || {};
  const merged = {
    ...current,
    ...patch,
    active_style: patch.active_style || current.active_style
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
      ...patch.section_divider
    };
    if (Object.prototype.hasOwnProperty.call(patch.section_divider, 'divider_directory')) {
      merged.section_divider.divider_directory = patch.section_divider.divider_directory;
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
function applyToDocuments(payload, {
  slugChanged = false
} = {}) {
  if (!payload?.slug) {
    return;
  }
  const {
    bodyClass,
    styleCssUrl,
    fontCssUrl,
    customThemeProperties,
    variationThemeJsonCss
  } = payload;
  const bust = Boolean(slugChanged);
  getTargetDocuments().forEach(doc => {
    if (slugChanged) {
      removeCompetingSkinAssets(doc, {
        keepStyleHref: styleCssUrl,
        keepFontHref: fontCssUrl
      });
    }
    upsertStylesheet(doc, SKIN_STYLE_LINK_ID, styleCssUrl, {
      bust
    });
    upsertStylesheet(doc, SKIN_FONT_LINK_ID, fontCssUrl, {
      bust
    });
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
function reapplyActiveSkinToDocuments(payload = null) {
  if (isSkinPersistencePaused()) {
    return;
  }
  const active = payload || lastAppliedPayload;
  if (!active?.slug) {
    return;
  }
  applyToDocuments({
    ...active,
    previousBodyClass: '',
    previousSlug: ''
  }, {
    slugChanged: false
  });
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
  [400, 1200].forEach(delay => {
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
  iframeObserver = new MutationObserver(mutations => {
    if (!lastAppliedPayload || isSkinPersistencePaused()) {
      return;
    }
    const iframeChanged = mutations.some(mutation => [...mutation.addedNodes].some(node => node.nodeName === 'IFRAME' || node.querySelector?.('iframe')));
    if (iframeChanged) {
      scheduleDebouncedReapply();
    }
  });
  iframeObserver.observe(document.body, {
    childList: true,
    subtree: true
  });
}

/**
 * @param {Object} payload Switch payload (boot or REST).
 */
function applySkinClientSide(payload) {
  if (!payload?.slug) {
    return payload;
  }
  const {
    slug,
    previousSlug,
    editorExperience
  } = payload;
  // Initial boot: lastAppliedSlug is empty — do not strip PHP-enqueued canvas styles.
  const slugChanged = lastAppliedSlug !== '' && slug !== lastAppliedSlug;
  if (slugChanged) {
    pausePersistence();
  }
  lastAppliedSlug = slug;
  lastAppliedPayload = {
    ...payload,
    previousSlug: previousSlug || ''
  };
  const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_0__.getSkinSwitcherBootData)();
  if (boot) {
    boot.activeSlug = slug;
  }
  mergeEditorExperience(editorExperience);
  applyToDocuments(lastAppliedPayload, {
    slugChanged
  });
  ensureIframeObserver();
  if (slugChanged) {
    scheduleIframeReapply();
  }
  document.dispatchEvent(new CustomEvent(SKIN_CHANGED_EVENT, {
    detail: {
      slug,
      previousSlug,
      payload: lastAppliedPayload
    }
  }));
  return payload;
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/boot-data.js"
/*!*********************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/boot-data.js ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getSkinSwitcherBootData: () => (/* binding */ getSkinSwitcherBootData)
/* harmony export */ });
/**
 * Localized boot data from PHP (wp_localize_script uses sanitize_key on the object name).
 */
const LOCALIZED_GLOBAL = 'theme_skin_switcher';
function getSkinSwitcherBootData() {
  return window[LOCALIZED_GLOBAL] || {};
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/client-switch-payload.js"
/*!*********************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/client-switch-payload.js ***!
  \*********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getSwitchPayload: () => (/* binding */ getSwitchPayload),
/* harmony export */   mergeBootFromResponse: () => (/* binding */ mergeBootFromResponse)
/* harmony export */ });
/**
 * Build a skin switch payload from preloaded boot data (instant apply).
 *
 * @param {string} slug
 * @param {string} previousSlug
 * @param {Object} boot
 * @return {Object|null}
 */
function getSwitchPayload(slug, previousSlug, boot) {
  const template = boot?.switchPayloads?.[slug] || buildFallbackPayload(slug, boot);
  if (!template) {
    return null;
  }
  const bodyClass = template.bodyClass || `theme-skin-${slug}`;
  return {
    ...template,
    slug,
    previousSlug: previousSlug || '',
    bodyClass,
    previousBodyClass: previousSlug ? `theme-skin-${previousSlug}` : ''
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
  const fontCssUrl = fontQuery ? `https://fonts.googleapis.com/css2?family=${fontQuery}&display=swap` : '';
  return {
    bodyClass: `theme-skin-${slug}`,
    styleCssUrl,
    fontCssUrl,
    editorExperience: {
      active_style: {
        slug,
        assets_url: `${themeUrl}design-skins/${slug}/`
      }
    }
  };
}

/**
 * @param {Object} boot
 * @param {Object} response
 */
function mergeBootFromResponse(boot, response) {
  if (!boot || !response) {
    return;
  }
  if (response.styles?.length) {
    boot.styles = response.styles;
  }
  if (response.switchPayloads) {
    boot.switchPayloads = {
      ...(boot.switchPayloads || {}),
      ...response.switchPayloads
    };
  }
  if (response.themeUrl) {
    boot.themeUrl = response.themeUrl;
  }
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/index.js"
/*!*****************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/index.js ***!
  \*****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/plugins */ "@wordpress/plugins");
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_plugins__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _boot_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./boot-data */ "./src/assets/js/editor/skin-switcher/boot-data.js");
/* harmony import */ var _skin_persistence__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./skin-persistence */ "./src/assets/js/editor/skin-switcher/skin-persistence.js");
/* harmony import */ var _skin_site_editor_save__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./skin-site-editor-save */ "./src/assets/js/editor/skin-switcher/skin-site-editor-save.js");
/* harmony import */ var _SkinSwitcherRootMount__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./SkinSwitcherRootMount */ "./src/assets/js/editor/skin-switcher/SkinSwitcherRootMount.js");
/* harmony import */ var _skin_switcher_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./skin-switcher.scss */ "./src/assets/js/editor/skin-switcher/skin-switcher.scss");
/**
 * Theme design skin switcher (Site Editor → Styles).
 */







const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_2__.getSkinSwitcherBootData)();
if (boot.nonce) {
  _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default().createNonceMiddleware(boot.nonce));
}
;(0,_skin_persistence__WEBPACK_IMPORTED_MODULE_3__.initSkinPersistence)();
(0,_skin_site_editor_save__WEBPACK_IMPORTED_MODULE_4__.initSkinSiteEditorSave)();
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_1__.registerPlugin)('theme-skin-switcher', {
  render: _SkinSwitcherRootMount__WEBPACK_IMPORTED_MODULE_5__["default"],
  icon: null
});

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/skin-persistence.js"
/*!****************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/skin-persistence.js ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   initSkinPersistence: () => (/* binding */ initSkinPersistence),
/* harmony export */   reapplyActiveSkin: () => (/* binding */ reapplyActiveSkin)
/* harmony export */ });
/* harmony import */ var _boot_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./boot-data */ "./src/assets/js/editor/skin-switcher/boot-data.js");
/* harmony import */ var _client_switch_payload__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./client-switch-payload */ "./src/assets/js/editor/skin-switcher/client-switch-payload.js");
/* harmony import */ var _apply_skin_client__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./apply-skin-client */ "./src/assets/js/editor/skin-switcher/apply-skin-client.js");
/**
 * Keep the active skin applied when the Site Editor recreates the canvas iframe.
 */



const BOUND_IFRAME_FLAG = 'themeSkinSwitcherBound';
let persistenceStarted = false;
let debounceTimer = null;
let lastEditorIframe = null;
function debouncedReapply(delay = 150) {
  if ((0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_2__.isSkinPersistencePaused)()) {
    return;
  }
  window.clearTimeout(debounceTimer);
  debounceTimer = window.setTimeout(() => {
    (0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_2__.reapplyActiveSkinToDocuments)();
  }, delay);
}
function bindIframeLoadHandlers() {
  document.querySelectorAll('iframe').forEach(frame => {
    if (frame.dataset[BOUND_IFRAME_FLAG]) {
      return;
    }
    frame.dataset[BOUND_IFRAME_FLAG] = '1';
    frame.addEventListener('load', () => debouncedReapply(50));
  });
}

/**
 * Re-apply the saved skin (theme mod + boot) without persisting again.
 */
function reapplyActiveSkin() {
  if ((0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_2__.isSkinPersistencePaused)()) {
    return;
  }
  const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_0__.getSkinSwitcherBootData)();
  const slug = boot?.activeSlug;
  if (!slug) {
    return;
  }
  const payload = (0,_client_switch_payload__WEBPACK_IMPORTED_MODULE_1__.getSwitchPayload)(slug, '', boot);
  if (!payload?.styleCssUrl) {
    return;
  }
  ;(0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_2__.reapplyActiveSkinToDocuments)(payload);
}

/**
 * Boot-time + navigation persistence for canvas iframe recreation.
 */
function initSkinPersistence() {
  if (persistenceStarted) {
    reapplyActiveSkin();
    return;
  }
  persistenceStarted = true;
  const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_0__.getSkinSwitcherBootData)();
  const slug = boot?.activeSlug;
  if (slug) {
    const payload = (0,_client_switch_payload__WEBPACK_IMPORTED_MODULE_1__.getSwitchPayload)(slug, '', boot);
    if (payload?.styleCssUrl) {
      (0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_2__.applySkinClientSide)(payload);
    }
  }
  bindIframeLoadHandlers();
  const domObserver = new MutationObserver(() => {
    if ((0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_2__.isSkinPersistencePaused)()) {
      return;
    }
    const canvas = document.querySelector('iframe.editor-canvas, iframe[name="editor-canvas"]');
    if (canvas && canvas !== lastEditorIframe) {
      lastEditorIframe = canvas;
      bindIframeLoadHandlers();
      debouncedReapply(0);
    }
  });
  domObserver.observe(document.body, {
    childList: true,
    subtree: true
  });
  window.setTimeout(reapplyActiveSkin, 500);
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/skin-select-options.js"
/*!*******************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/skin-select-options.js ***!
  \*******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   withNoSkinPlaceholder: () => (/* binding */ withNoSkinPlaceholder)
/* harmony export */ });
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
function withNoSkinPlaceholder(options, activeSlug, placeholder) {
  if (!options.length || options.some(option => option.key === activeSlug)) {
    return options;
  }
  return [{
    key: '',
    name: placeholder
  }, ...options];
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/skin-site-editor-save.js"
/*!*********************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/skin-site-editor-save.js ***!
  \*********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getSiteEditorSkinSlug: () => (/* binding */ getSiteEditorSkinSlug),
/* harmony export */   initSkinSiteEditorSave: () => (/* binding */ initSkinSiteEditorSave),
/* harmony export */   markSkinEditInSiteEditor: () => (/* binding */ markSkinEditInSiteEditor)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _boot_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./boot-data */ "./src/assets/js/editor/skin-switcher/boot-data.js");
/* harmony import */ var _client_switch_payload__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./client-switch-payload */ "./src/assets/js/editor/skin-switcher/client-switch-payload.js");
/* harmony import */ var _apply_skin_client__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./apply-skin-client */ "./src/assets/js/editor/skin-switcher/apply-skin-client.js");
/**
 * Tie skin changes to the Site Editor save flow (dirty state + persist on Save).
 */




function getSettingKey() {
  return (0,_boot_data__WEBPACK_IMPORTED_MODULE_1__.getSkinSwitcherBootData)()?.settingKey || 'theme_skin_switcher_active_style';
}

/**
 * Read the saved or in-progress skin slug from the site settings entity.
 */
function getSiteEditorSkinSlug() {
  const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_1__.getSkinSwitcherBootData)();
  const key = getSettingKey();
  const core = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)('core');
  if (!core) {
    return boot?.activeSlug || '';
  }
  const edits = core.getEntityRecordEdits?.('root', 'site', undefined);
  const record = core.getEntityRecord?.('root', 'site', undefined);
  if (edits && Object.prototype.hasOwnProperty.call(edits, key)) {
    return edits[key] || '';
  }
  return record?.[key] ?? boot?.activeSlug ?? '';
}

/**
 * Mark the Site Editor as dirty so the Save button enables.
 *
 * @param {string} slug
 */
function markSkinEditInSiteEditor(slug) {
  const key = getSettingKey();
  try {
    (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)('core').editEntityRecord('root', 'site', undefined, {
      [key]: slug
    });
  } catch {
    // core store may not be ready yet.
  }
}

/**
 * Preview-only apply when the site entity reverts (discard / undo).
 *
 * @param {string} slug
 */
function previewSkinSlug(slug) {
  const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_1__.getSkinSwitcherBootData)();
  const payload = (0,_client_switch_payload__WEBPACK_IMPORTED_MODULE_2__.getSwitchPayload)(slug, '', boot);
  if (payload?.styleCssUrl) {
    (0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_3__.reapplyActiveSkinToDocuments)(payload);
  }
  if (boot) {
    boot.activeSlug = slug;
  }
}

/**
 * Subscribe to site settings saves and discard reverts.
 */
function initSkinSiteEditorSave() {
  const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_1__.getSkinSwitcherBootData)();
  let lastPreviewSlug = getSiteEditorSkinSlug();
  let wasSavingSite = false;
  (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.subscribe)(() => {
    const core = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)('core');
    if (!core) {
      return;
    }
    const slug = getSiteEditorSkinSlug();
    const isSaving = Boolean(core.isSavingEntityRecord?.('root', 'site', undefined));
    if (slug && slug !== lastPreviewSlug) {
      lastPreviewSlug = slug;
      const payload = (0,_client_switch_payload__WEBPACK_IMPORTED_MODULE_2__.getSwitchPayload)(slug, '', boot);
      if (payload?.styleCssUrl) {
        (0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_3__.reapplyActiveSkinToDocuments)(payload);
      }
      if (boot) {
        boot.activeSlug = slug;
      }
    }
    if (wasSavingSite && !isSaving) {
      const saved = getSiteEditorSkinSlug();
      if (boot) {
        boot.activeSlug = saved;
      }
      lastPreviewSlug = saved;
    }
    wasSavingSite = isSaving;
  });
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/useActiveSkin.js"
/*!*************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/useActiveSkin.js ***!
  \*************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ useActiveSkin)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _boot_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./boot-data */ "./src/assets/js/editor/skin-switcher/boot-data.js");
/* harmony import */ var _client_switch_payload__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./client-switch-payload */ "./src/assets/js/editor/skin-switcher/client-switch-payload.js");
/* harmony import */ var _apply_skin_client__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./apply-skin-client */ "./src/assets/js/editor/skin-switcher/apply-skin-client.js");
/**
 * Active skin state from localized boot data and REST.
 */





function useActiveSkin() {
  const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_2__.getSkinSwitcherBootData)();
  const [activeSlug, setActiveSlug] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(boot.activeSlug || '');
  const [styles, setStyles] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(boot.styles || []);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(!boot.styles?.length || !boot.switchPayloads);
  const handleSkinChanged = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(event => {
    if (event?.detail?.slug) {
      setActiveSlug(event.detail.slug);
    }
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    document.addEventListener(_apply_skin_client__WEBPACK_IMPORTED_MODULE_4__.SKIN_CHANGED_EVENT, handleSkinChanged);
    return () => {
      document.removeEventListener(_apply_skin_client__WEBPACK_IMPORTED_MODULE_4__.SKIN_CHANGED_EVENT, handleSkinChanged);
    };
  }, [handleSkinChanged]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (boot.styles?.length && boot.switchPayloads) {
      return undefined;
    }
    let cancelled = false;
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: `/${boot.restNamespace}${boot.restRoute}`
    }).then(response => {
      if (cancelled) {
        return;
      }
      ;(0,_client_switch_payload__WEBPACK_IMPORTED_MODULE_3__.mergeBootFromResponse)(boot, response);
      setActiveSlug((0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_4__.getLastAppliedSkinSlug)() || response.slug || boot.activeSlug || '');
      setStyles(response.styles || boot.styles || []);
    }).catch(() => {
      if (!cancelled) {
        setStyles(boot.styles || []);
      }
    }).finally(() => {
      if (!cancelled) {
        setIsLoading(false);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [boot.restNamespace, boot.restRoute, boot.styles?.length, boot.switchPayloads]);
  return {
    activeSlug,
    styles,
    isLoading,
    setActiveSlug
  };
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/useApplySkin.js"
/*!************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/useApplySkin.js ***!
  \************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ useApplySkin)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _boot_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./boot-data */ "./src/assets/js/editor/skin-switcher/boot-data.js");
/* harmony import */ var _client_switch_payload__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./client-switch-payload */ "./src/assets/js/editor/skin-switcher/client-switch-payload.js");
/* harmony import */ var _apply_skin_client__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./apply-skin-client */ "./src/assets/js/editor/skin-switcher/apply-skin-client.js");
/* harmony import */ var _skin_site_editor_save__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./skin-site-editor-save */ "./src/assets/js/editor/skin-switcher/skin-site-editor-save.js");
/**
 * Preview skin in the editor immediately; persist when the user saves the site.
 */







function useApplySkin({
  onApplied
} = {}) {
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const applySkin = async slug => {
    if (!slug || slug === (0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_5__.getLastAppliedSkinSlug)()) {
      return;
    }
    const boot = (0,_boot_data__WEBPACK_IMPORTED_MODULE_3__.getSkinSwitcherBootData)();
    const previousSlug = (0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_5__.getLastAppliedSkinSlug)();
    setError('');
    try {
      const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
        path: `/${boot.restNamespace}${boot.restRoute}`
      });
      (0,_client_switch_payload__WEBPACK_IMPORTED_MODULE_4__.mergeBootFromResponse)(boot, response);
    } catch {
      // Fall back to boot payloads from page load.
    }
    const payload = (0,_client_switch_payload__WEBPACK_IMPORTED_MODULE_4__.getSwitchPayload)(slug, previousSlug, boot);
    if (!payload?.styleCssUrl) {
      setError((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Could not switch design skin. Please reload the editor.', 'theme-skin-switcher'));
      return;
    }
    ;(0,_apply_skin_client__WEBPACK_IMPORTED_MODULE_5__.applySkinClientSide)(payload);
    (0,_skin_site_editor_save__WEBPACK_IMPORTED_MODULE_6__.markSkinEditInSiteEditor)(slug);
    onApplied?.({
      slug
    });
  };
  return {
    applySkin,
    isPending: false,
    error
  };
}

/***/ },

/***/ "./src/assets/js/editor/skin-switcher/skin-switcher.scss"
/*!***************************************************************!*\
  !*** ./src/assets/js/editor/skin-switcher/skin-switcher.scss ***!
  \***************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/data"
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["data"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/plugins"
/*!*********************************!*\
  !*** external ["wp","plugins"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["plugins"];

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	const __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		const cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		const module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			const e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = (module) => {
/******/ 		const getter = module && module.__esModule ?
/******/ 			() => (module['default']) :
/******/ 			() => (module);
/******/ 		__webpack_require__.d(getter, { a: getter });
/******/ 		return getter;
/******/ 	};
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	// define getter/value functions for harmony exports
/******/ 	__webpack_require__.d = (exports, definition) => {
/******/ 		for(var key in definition) {
/******/ 			if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 				Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 			}
/******/ 		}
/******/ 	};
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	__webpack_require__.o = (obj, prop) => (Object.hasOwn(obj, prop));
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ 	
/************************************************************************/
let __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!****************************************!*\
  !*** ./src/assets/js/editor/editor.js ***!
  \****************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _skin_switcher__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./skin-switcher */ "./src/assets/js/editor/skin-switcher/index.js");
/**
 * Site Editor: Theme design skin switcher (Global Styles header).
 */

})();

/******/ })()
;
//# sourceMappingURL=editor.js.map