/**
 * Live skin switch swaps per-block skin stylesheets (Kitaris BlockAssets):
 * the previous skin's linked or inlined styles go, the new skin's get linked.
 */
import { syncBlockAssetStyles } from '../apply-skin-client';

const archButton = {
	id: 'kitaris-block-assets-skin-arch-core-button',
	href: 'https://example.test/build/design-skins/arch/block-assets/core/button.css?ver=1',
};

beforeEach(() => {
	document.head.innerHTML = `
		<style id="kitaris-block-assets-skin-flow-core-button-inline-css">.flow{}</style>
		<link id="kitaris-block-assets-skin-flow-core-quote-css" rel="stylesheet" href="flow-quote.css">
		<link id="kitaris-block-assets-compass-waas-trades-core-button-css" rel="stylesheet" href="theme.css">
	`;
});

test('removes the previous skin and adds the active skin', () => {
	syncBlockAssetStyles(document, [archButton]);

	const ids = [...document.head.querySelectorAll('[id]')].map((node) => node.id);

	expect(ids).toEqual([
		// Theme-level files are not skin files and stay.
		'kitaris-block-assets-compass-waas-trades-core-button-css',
		'kitaris-block-assets-skin-arch-core-button-css',
	]);
	expect(document.getElementById(`${archButton.id}-css`).getAttribute('href')).toBe(archButton.href);
});

test('keeps an active skin style that core already inlined and does not duplicate it', () => {
	document.head.innerHTML = `<style id="${archButton.id}-inline-css">.arch{}</style>`;

	syncBlockAssetStyles(document, [archButton]);
	syncBlockAssetStyles(document, [archButton]);

	expect(document.head.children).toHaveLength(1);
	expect(document.head.firstElementChild.id).toBe(`${archButton.id}-inline-css`);
});

test('a skin without block styles removes the previous skin files', () => {
	syncBlockAssetStyles(document, undefined);

	expect(document.querySelectorAll('[id^="kitaris-block-assets-skin-"]')).toHaveLength(0);
});
