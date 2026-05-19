/**
 * Keep the active skin applied when the Site Editor recreates the canvas iframe.
 */
import { getSkinSwitcherBootData } from './boot-data';
import { getSwitchPayload } from './client-switch-payload';
import {
	applySkinClientSide,
	isSkinPersistencePaused,
	reapplyActiveSkinToDocuments,
} from './apply-skin-client';

const BOUND_IFRAME_FLAG = 'compassWaasSkinBound';

let persistenceStarted = false;
let debounceTimer = null;
let lastEditorIframe = null;

function debouncedReapply(delay = 150) {
	if (isSkinPersistencePaused()) {
		return;
	}

	window.clearTimeout(debounceTimer);
	debounceTimer = window.setTimeout(() => {
		reapplyActiveSkinToDocuments();
	}, delay);
}

function bindIframeLoadHandlers() {
	document.querySelectorAll('iframe').forEach((frame) => {
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
export function reapplyActiveSkin() {
	if (isSkinPersistencePaused()) {
		return;
	}

	const boot = getSkinSwitcherBootData();
	const slug = boot?.activeSlug;

	if (!slug) {
		return;
	}

	const payload = getSwitchPayload(slug, '', boot);

	if (!payload?.styleCssUrl) {
		return;
	}

	reapplyActiveSkinToDocuments(payload);
}

/**
 * Boot-time + navigation persistence for canvas iframe recreation.
 */
export function initSkinPersistence() {
	if (persistenceStarted) {
		reapplyActiveSkin();
		return;
	}

	persistenceStarted = true;

	const boot = getSkinSwitcherBootData();
	const slug = boot?.activeSlug;

	if (slug) {
		const payload = getSwitchPayload(slug, '', boot);

		if (payload?.styleCssUrl) {
			applySkinClientSide(payload);
		}
	}

	bindIframeLoadHandlers();

	const domObserver = new MutationObserver(() => {
		if (isSkinPersistencePaused()) {
			return;
		}

		const canvas = document.querySelector(
			'iframe.editor-canvas, iframe[name="editor-canvas"]'
		);

		if (canvas && canvas !== lastEditorIframe) {
			lastEditorIframe = canvas;
			bindIframeLoadHandlers();
			debouncedReapply(0);
		}
	});

	domObserver.observe(document.body, {
		childList: true,
		subtree: true,
	});

	window.setTimeout(reapplyActiveSkin, 500);
}
