/**
 * A saved skin change shows the header and footer the server gave that skin,
 * by asking the store for those parts again. Nothing else asks: not a save of
 * the same skin, not a failed save, not a discard. A part with unsaved work
 * stays as it is.
 */
import { dispatch, select, subscribe } from '@wordpress/data';
import { initSkinSiteEditorSave } from '../skin-site-editor-save';

jest.mock('@wordpress/data', () => ({
	dispatch: jest.fn(),
	select: jest.fn(),
	subscribe: jest.fn(),
}));
jest.mock('../apply-skin-client', () => ({ reapplyActiveSkinToDocuments: jest.fn() }));
jest.mock('../skin-base-styles', () => ({ syncBaseStyles: jest.fn() }));

const KEY = 'theme_skin_switcher_active_style';
const HEADER = 'compass//header';
const FOOTER = 'compass//footer';

let site;
let theme;
let editedParts;
let savingParts;
let invalidateResolution;

function fire() {
	subscribe.mock.calls[0][0]();
}

/** The parts refreshed: each through both selectors, as the block reads the edited record. */
function refreshed() {
	const parts = invalidateResolution.mock.calls
		.filter(([selector]) => selector === 'getEntityRecord')
		.map(([, args]) => args[2]);

	expect(invalidateResolution.mock.calls).toEqual(
		parts.flatMap((id) => [
			['getEntityRecord', ['postType', 'wp_template_part', id]],
			['getEditedEntityRecord', ['postType', 'wp_template_part', id]],
		])
	);

	return parts;
}

function pick(slug) {
	site.edits = { [KEY]: slug };
	fire();
}

function startSave() {
	site.saving = true;
	fire();
}

function finishSave() {
	const slug = site.edits[KEY] ?? site.record[KEY];

	site.edits = {};
	site.record = { ...site.record, [KEY]: slug };
	site.saving = false;
	fire();
}

function failSave() {
	site.error = { code: 'rest_failed' };
	site.saving = false;
	fire();
}

function save() {
	startSave();
	finishSave();
}

function init(boot = {}) {
	window.theme_skin_switcher = {
		activeSlug: 'grit',
		settingKey: KEY,
		switchPayloads: {},
		...boot,
	};

	initSkinSiteEditorSave();
}

beforeEach(() => {
	jest.clearAllMocks();

	site = { edits: {}, record: { [KEY]: 'grit' }, saving: false, error: undefined };
	theme = { stylesheet: 'compass' };
	editedParts = [];
	savingParts = [];
	invalidateResolution = jest.fn();

	dispatch.mockImplementation(() => ({ invalidateResolution }));
	select.mockImplementation(() => ({
		getEntityRecordEdits: () => site.edits,
		getEntityRecord: () => site.record,
		isSavingEntityRecord: (kind, name, id) =>
			kind === 'root' ? site.saving : savingParts.includes(id),
		getLastEntitySaveError: (kind, name) =>
			kind === 'root' && name === 'site' ? site.error : undefined,
		getCurrentTheme: () => theme,
		hasEditsForEntityRecord: (kind, name, id) =>
			name === 'wp_template_part' && editedParts.includes(id),
	}));

	init();
});

afterEach(() => {
	delete window.theme_skin_switcher;
});

test('saving another skin refreshes the header and the footer once', () => {
	pick('nord');
	startSave();
	expect(refreshed()).toEqual([]);

	finishSave();
	fire();
	fire();

	expect(refreshed()).toEqual([HEADER, FOOTER]);
});

test('the parts are the active theme\'s, a child theme included', () => {
	theme = { stylesheet: 'compass-waas-trades' };

	pick('nord');
	save();

	expect(refreshed()).toEqual([
		'compass-waas-trades//header',
		'compass-waas-trades//footer',
	]);
});

test('a store that fires again during the refresh refreshes nothing more', () => {
	invalidateResolution.mockImplementation(fire);

	pick('nord');
	save();

	expect(refreshed()).toEqual([HEADER, FOOTER]);
});

test('saving the same skin refreshes nothing', () => {
	site.edits = { title: 'New title' };
	save();

	expect(refreshed()).toEqual([]);
});

test('a skin picked and picked back before saving refreshes nothing', () => {
	pick('nord');
	pick('grit');
	save();

	expect(refreshed()).toEqual([]);
});

test('a second save of the saved skin refreshes nothing more', () => {
	pick('nord');
	save();
	invalidateResolution.mockClear();

	site.edits = { title: 'New title' };
	save();

	expect(refreshed()).toEqual([]);
});

test('each saved change refreshes, going back to the first skin too', () => {
	pick('nord');
	save();
	pick('grit');
	save();

	expect(refreshed()).toEqual([HEADER, FOOTER, HEADER, FOOTER]);
});

test('a failed save refreshes nothing', () => {
	pick('nord');
	startSave();
	failSave();
	fire();

	expect(refreshed()).toEqual([]);
});

test('a save that succeeds after a failed one still refreshes', () => {
	pick('nord');
	startSave();
	failSave();

	site.error = undefined;
	save();

	expect(refreshed()).toEqual([HEADER, FOOTER]);
});

test('a failed save, discarded, leaves nothing to refresh on the next save', () => {
	pick('nord');
	startSave();
	failSave();

	site.error = undefined;
	site.edits = {};
	fire();
	save();

	expect(refreshed()).toEqual([]);
});

test('a discard refreshes nothing', () => {
	pick('nord');

	site.edits = {};
	fire();
	fire();

	expect(refreshed()).toEqual([]);
});

test.each([
	['header', HEADER, FOOTER],
	['footer', FOOTER, HEADER],
])('a %s with unsaved edits is skipped, the other part refreshes', (area, skipped, other) => {
	editedParts = [skipped];

	pick('nord');
	save();

	expect(refreshed()).toEqual([other]);
});

test.each([
	['header', HEADER, FOOTER],
	['footer', FOOTER, HEADER],
])('a %s still saving is skipped, the other part refreshes', (area, skipped, other) => {
	savingParts = [skipped];

	pick('nord');
	save();

	expect(refreshed()).toEqual([other]);
});

test('both parts with unsaved work leave nothing to refresh', () => {
	editedParts = [HEADER];
	savingParts = [FOOTER];

	pick('nord');
	save();

	expect(refreshed()).toEqual([]);
});

test.each([
	['no theme yet', null],
	['a theme without a stylesheet', {}],
	['an empty stylesheet', { stylesheet: '' }],
])('%s refreshes nothing', (label, current) => {
	theme = current;

	pick('nord');
	save();

	expect(refreshed()).toEqual([]);
});

test('with no boot slug the skin at start counts as the saved one', () => {
	subscribe.mockClear();
	init({ activeSlug: '' });

	save();
	expect(refreshed()).toEqual([]);

	pick('nord');
	save();
	expect(refreshed()).toEqual([HEADER, FOOTER]);
});
