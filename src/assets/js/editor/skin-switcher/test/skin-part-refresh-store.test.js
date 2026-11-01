/**
 * The header and footer refresh against the real core-data store, read the way
 * the template part block reads them: through the edited record only. A mocked
 * store can't show this: whether a read fetches again is the store's own
 * resolver bookkeeping. Only the network is mocked here.
 */
import { TextDecoder, TextEncoder } from 'util';
import apiFetch from '@wordpress/api-fetch';
import { dispatch, resolveSelect, select } from '@wordpress/data';

jest.mock('@wordpress/api-fetch', () => {
	const mock = jest.fn();
	mock.use = jest.fn();
	mock.setFetchHandler = jest.fn();

	return { __esModule: true, default: mock };
});

// core-data pulls these in for parts of it that don't run here; they don't load under Jest.
jest.mock('@wordpress/block-editor', () => ({}));
jest.mock('@wordpress/components', () => ({}));
jest.mock('marked', () => ({ Marked: class {} }));
jest.mock('../apply-skin-client', () => ({ reapplyActiveSkinToDocuments: jest.fn() }));
jest.mock('../skin-base-styles', () => ({ syncBaseStyles: jest.fn() }));

const KEY = 'theme_skin_switcher_active_style';
const PART = ['postType', 'wp_template_part', 'compass//header'];
const FOOTER = ['postType', 'wp_template_part', 'compass//footer'];

let fetches;
let failSave;
let site;

function respond(options, body) {
	return options.parse === false
		? { json: async () => body, headers: { get: () => null } }
		: body;
}

function part(path, options) {
	const slug = path.includes('footer') ? 'footer' : 'header';

	fetches[slug] += 1;

	return respond(options, {
		id: `compass//${slug}`,
		slug,
		area: slug,
		type: 'wp_template_part',
		theme: 'compass',
		source: 'theme',
		title: { raw: slug },
		content: { raw: `<!-- ${slug} ${fetches[slug]} -->` },
	});
}

async function fakeFetch(options) {
	const path = String(options.path ?? options.url);

	if (path.includes('/wp/v2/types')) {
		return respond(options, {
			wp_template_part: {
				slug: 'wp_template_part',
				name: 'Template Parts',
				labels: { singular_name: 'Template Part' },
				rest_base: 'template-parts',
				rest_namespace: 'wp/v2',
				supports: {},
				taxonomies: [],
			},
		});
	}

	if (path.includes('template-parts')) {
		return part(path, options);
	}

	if (path.includes('/wp/v2/themes')) {
		return respond(options, [{ stylesheet: 'compass' }]);
	}

	if (path.includes('/wp/v2/settings')) {
		if (options.method === 'POST' || options.method === 'PUT') {
			if (failSave) {
				throw { code: 'rest_failed', message: 'No.' };
			}

			site = { ...site, ...options.data };
		}

		return respond(options, site);
	}

	return respond(options, []);
}

function wait() {
	return new Promise((resolve) => setTimeout(resolve, 20));
}

/** What the block shows: asking again is what a re-render does. */
async function shown(args = PART) {
	select('core').getEditedEntityRecord(...args);
	await wait();

	return select('core').getEditedEntityRecord(...args).content;
}

async function saveSkin(slug) {
	dispatch('core').editEntityRecord('root', 'site', undefined, { [KEY]: slug });
	await wait();
	await dispatch('core').saveEditedEntityRecord('root', 'site', undefined);
	await wait();
}

beforeAll(async () => {
	global.TextEncoder = TextEncoder;
	global.TextDecoder = TextDecoder;

	apiFetch.mockImplementation(fakeFetch);
	fetches = { header: 0, footer: 0 };
	failSave = false;
	site = { [KEY]: 'grit' };
	window.theme_skin_switcher = { activeSlug: 'grit', settingKey: KEY, switchPayloads: {} };

	require('@wordpress/core-data');

	await resolveSelect('core').getEntityRecord('root', 'site', undefined);
	await resolveSelect('core').getCurrentTheme();
	await resolveSelect('core').getEditedEntityRecord(...PART);
	await resolveSelect('core').getEditedEntityRecord(...FOOTER);

	require('../skin-site-editor-save').initSkinSiteEditorSave();
});

afterAll(() => {
	delete window.theme_skin_switcher;
});

// One store, one subscriber: these run in order, each from where the last left off.
test('the store starts with the first fetch of the part', async () => {
	expect(await shown()).toBe('<!-- header 1 -->');
});

test('a read with nothing saved fetches nothing', async () => {
	await shown();

	expect(fetches.header).toBe(1);
});

test('saving the same skin fetches nothing', async () => {
	await saveSkin('grit');

	expect(await shown()).toBe('<!-- header 1 -->');
	expect(fetches.header).toBe(1);
});

test('a failed save fetches nothing', async () => {
	failSave = true;

	await saveSkin('nord');

	expect(await shown()).toBe('<!-- header 1 -->');
	expect(fetches.header).toBe(1);
});

test('the save that then succeeds gives an edited-record reader new content', async () => {
	failSave = false;

	await dispatch('core').saveEditedEntityRecord('root', 'site', undefined);
	await wait();

	expect(await shown()).toBe('<!-- header 2 -->');
	expect(await shown(FOOTER)).toBe('<!-- footer 2 -->');
});

test('a part with unsaved edits keeps them, the other part refreshes', async () => {
	dispatch('core').editEntityRecord(...PART, { content: 'mine' });
	await wait();

	await saveSkin('grit');

	expect(await shown()).toBe('mine');
	expect(fetches.header).toBe(2);
	expect(await shown(FOOTER)).toBe('<!-- footer 3 -->');
});
