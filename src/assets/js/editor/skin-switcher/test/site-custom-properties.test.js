/**
 * The skin's canvas CSS beats core's :root so a skin switch previews; the
 * site's own tokens (settings.custom in the editor's global styles, unsaved
 * edits included) are laid after it so they still win, as on the front end.
 */
import { select } from '@wordpress/data';
import { applyToDocuments, customPropertiesFrom } from '../apply-skin-client';

jest.mock('@wordpress/data', () => ({ select: jest.fn(), subscribe: jest.fn() }));

const payload = {
	slug: 'flow',
	bodyClass: 'theme-skin-flow',
	customThemeProperties: { '--wp--custom--border-radius--button': 'var(--wp--preset--border-radius--round)' },
	variationThemeJsonCss: '',
	blockAssetStyles: [],
};

function siteCustom(custom) {
	select.mockReturnValue({
		__experimentalGetCurrentGlobalStylesId: () => 7,
		getEditedEntityRecord: () => ({ settings: { custom } }),
	});
}

beforeEach(() => {
	document.head.innerHTML = '';
	document.body.innerHTML = '';
});

describe('customPropertiesFrom', () => {
	it('names variables as core does', () => {
		expect(customPropertiesFrom({ 'border-radius': { button: '0' }, lineHeight: { body: 1.6 }, h1: { size: '2rem' } })).toEqual({
			'--wp--custom--border-radius--button': '0',
			'--wp--custom--line-height--body': '1.6',
			'--wp--custom--h-1--size': '2rem',
		});
	});

	it('leaves out values that could close the rule or the style element', () => {
		expect(customPropertiesFrom({ a: '1px;} body{display:none', b: '</style>', c: '', d: '4px' })).toEqual({ '--wp--custom--d': '4px' });
	});
});

describe('site tokens over the skin', () => {
	it('comes after the skin tokens, with the same scope', () => {
		siteCustom({ 'border-radius': { button: '0' } });

		applyToDocuments(payload);

		const styles = [ ...document.head.querySelectorAll('style') ];
		const site = document.getElementById('theme-skin-switcher-site-custom-properties');
		expect(styles[ styles.length - 1 ]).toBe(site);
		expect(site.textContent).toBe(':root, body.theme-skin-flow, .editor-styles-wrapper{--wp--custom--border-radius--button: 0;}');
	});

	it('adds nothing when the site sets no tokens', () => {
		siteCustom(undefined);

		applyToDocuments(payload);

		expect(document.getElementById('theme-skin-switcher-site-custom-properties')).toBeNull();
	});
});
