/**
 * The Site Editor subscriber: every change of the previewed skin, a discard or
 * revert included, rebuilds core's global stylesheet. Anything else does not.
 */
import { select, subscribe } from '@wordpress/data';
import { reapplyActiveSkinToDocuments } from '../apply-skin-client';
import { syncBaseStyles } from '../skin-base-styles';
import { initSkinSiteEditorSave } from '../skin-site-editor-save';

jest.mock('@wordpress/data', () => ({
	dispatch: jest.fn(),
	select: jest.fn(),
	subscribe: jest.fn(),
}));
jest.mock('../apply-skin-client', () => ({ reapplyActiveSkinToDocuments: jest.fn() }));
jest.mock('../skin-base-styles', () => ({ syncBaseStyles: jest.fn() }));

const KEY = 'theme_skin_switcher_active_style';

let site;
let listener;

function fire() {
	listener();
}

beforeEach(() => {
	jest.clearAllMocks();

	window.theme_skin_switcher = {
		activeSlug: 'grit',
		settingKey: KEY,
		switchPayloads: {
			grit: { styleCssUrl: 'https://example.test/grit.css' },
			nord: { styleCssUrl: 'https://example.test/nord.css' },
		},
	};

	site = { edits: {}, record: { [KEY]: 'grit' }, saving: false };

	select.mockImplementation((store) =>
		store === 'core'
			? {
					getEntityRecordEdits: () => site.edits,
					getEntityRecord: () => site.record,
					isSavingEntityRecord: () => site.saving,
			  }
			: undefined
	);

	initSkinSiteEditorSave();
	listener = subscribe.mock.calls[0][0];
});

afterEach(() => {
	delete window.theme_skin_switcher;
});

test('an unchanged skin syncs nothing', () => {
	fire();
	fire();

	expect(syncBaseStyles).not.toHaveBeenCalled();
});

test('a new skin in the site edits syncs once', () => {
	site.edits = { [KEY]: 'nord' };

	fire();
	fire();

	expect(syncBaseStyles).toHaveBeenCalledTimes(1);
	expect(syncBaseStyles).toHaveBeenCalledWith('nord');
	expect(window.theme_skin_switcher.activeSlug).toBe('nord');
});

test('a discard syncs back to the saved skin', () => {
	site.edits = { [KEY]: 'nord' };
	fire();

	site.edits = {};
	fire();

	expect(syncBaseStyles).toHaveBeenCalledTimes(2);
	expect(syncBaseStyles).toHaveBeenLastCalledWith('grit');
	expect(reapplyActiveSkinToDocuments).toHaveBeenLastCalledWith(
		expect.objectContaining({ slug: 'grit' })
	);
});

test('an edit that clears the skin syncs nothing', () => {
	site.edits = { [KEY]: '' };

	fire();

	expect(syncBaseStyles).not.toHaveBeenCalled();
});

test('an unrelated site edit syncs nothing', () => {
	site.edits = { title: 'New title' };

	fire();

	expect(syncBaseStyles).not.toHaveBeenCalled();
});

test('a skin with no payload to preview still syncs', () => {
	site.edits = { [KEY]: 'unbuilt' };

	fire();

	expect(reapplyActiveSkinToDocuments).not.toHaveBeenCalled();
	expect(syncBaseStyles).toHaveBeenCalledWith('unbuilt');
});

test('finishing a save of that skin does not sync again', () => {
	site.edits = { [KEY]: 'nord' };
	fire();

	site.saving = true;
	fire();

	site.edits = {};
	site.record = { [KEY]: 'nord' };
	site.saving = false;
	fire();
	fire();

	expect(syncBaseStyles).toHaveBeenCalledTimes(1);
});

test('no core store does nothing', () => {
	select.mockImplementation(() => undefined);

	fire();

	expect(syncBaseStyles).not.toHaveBeenCalled();
});
