/**
 * A live skin switch styles the documents that render blocks. When the editor
 * canvas is iframed, canvas CSS stays out of the outer admin page: WordPress
 * copies canvas-looking styles from there into the iframe with a console
 * warning. The outer page keeps the font link and body class, as on reload.
 */
import { applyToDocuments } from '../apply-skin-client';

const payload = {
	slug: 'arch',
	bodyClass: 'theme-skin-arch',
	styleCssUrl: 'https://example.test/build/design-skins/arch/style.css',
	fontCssUrl: 'https://fonts.googleapis.com/css2?family=Inter',
	customThemeProperties: { '--wp--custom--radius': '4px' },
	variationThemeJsonCss: ':root{--wp--preset--color--base:#fff}',
	blockAssetStyles: [],
};

const CANVAS_IDS = [
	'theme-skin-switcher-active-skin-style',
	'theme-skin-switcher-custom-theme-properties',
	'theme-skin-switcher-variation-theme-json',
];

function addIframe(name) {
	const frame = document.createElement('iframe');
	if (name) {
		frame.setAttribute('name', name);
	}
	document.body.appendChild(frame);

	return frame.contentDocument;
}

beforeEach(() => {
	document.head.innerHTML = '';
	document.body.innerHTML = '';
	document.body.className = '';
});

test('with an iframed editor canvas, canvas CSS goes only into the iframe', () => {
	const canvas = addIframe('editor-canvas');

	applyToDocuments(payload, { slugChanged: true });

	CANVAS_IDS.forEach((id) => {
		expect(document.getElementById(id)).toBeNull();
		expect(canvas.getElementById(id)).not.toBeNull();
	});
	expect(canvas.body.classList.contains('theme-skin-arch')).toBe(true);
});

test('the outer page keeps the font link and body class', () => {
	addIframe('editor-canvas');

	applyToDocuments(payload, { slugChanged: true });

	expect(document.getElementById('theme-skin-switcher-active-skin-font')).not.toBeNull();
	expect(document.body.classList.contains('theme-skin-arch')).toBe(true);
});

test('canvas CSS left on the outer page by an earlier switch is removed', () => {
	addIframe('editor-canvas');
	const stale = document.createElement('style');
	stale.id = 'theme-skin-switcher-custom-theme-properties';
	document.head.appendChild(stale);

	applyToDocuments(payload, { slugChanged: false });

	expect(document.getElementById('theme-skin-switcher-custom-theme-properties')).toBeNull();
});

test('other same-origin previews get the canvas CSS too', () => {
	addIframe('editor-canvas');
	const preview = addIframe('');

	applyToDocuments(payload, { slugChanged: true });

	CANVAS_IDS.forEach((id) => expect(preview.getElementById(id)).not.toBeNull());
});

test('without an iframed canvas, the outer page is the canvas', () => {
	applyToDocuments(payload, { slugChanged: true });

	CANVAS_IDS.forEach((id) => expect(document.getElementById(id)).not.toBeNull());
});
