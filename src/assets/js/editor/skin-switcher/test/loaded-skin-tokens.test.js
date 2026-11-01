/**
 * PHP prints the tokens of the skin active at page load in the canvas. Once a
 * skin has changed they must go, or a token only the old skin set outlives the
 * swap; until then they are all the canvas has. The module remembers whether a
 * switch happened, so each test loads a fresh copy.
 */
const TOKENS_ID = 'theme-skin-switcher-canvas-tokens-inline-css';

const grit = {
	slug: 'grit',
	bodyClass: 'theme-skin-grit',
	styleCssUrl: 'https://example.test/build/design-skins/grit/style.css',
};
const nord = {
	slug: 'nord',
	bodyClass: 'theme-skin-nord',
	styleCssUrl: 'https://example.test/build/design-skins/nord/style.css',
};

let client;
let clock;

function addTokens(doc) {
	const style = doc.createElement('style');
	style.id = TOKENS_ID;
	style.textContent = ':root{--wp--custom--only-old:1}';
	doc.head.appendChild(style);
}

function addCanvas(name = 'editor-canvas') {
	const frame = document.createElement('iframe');
	frame.setAttribute('name', name);
	document.body.appendChild(frame);
	addTokens(frame.contentDocument);

	return frame.contentDocument;
}

function hasTokens(doc) {
	return doc.getElementById(TOKENS_ID) !== null;
}

/** A switch pauses re-applying for a moment; step past that. */
function afterThePause() {
	clock.mockReturnValue(Date.now() + 10000);
}

beforeEach(() => {
	jest.resetModules();
	jest.useFakeTimers();

	document.head.innerHTML = '';
	document.body.innerHTML = '';
	document.body.className = '';

	client = require('../apply-skin-client');
});

afterEach(() => {
	clock?.mockRestore();
	clock = undefined;
	jest.useRealTimers();
});

test('the first apply at boot keeps them', () => {
	const canvas = addCanvas();

	client.applySkinClientSide(grit);

	expect(hasTokens(canvas)).toBe(true);
});

test('applying the same skin again keeps them', () => {
	const canvas = addCanvas();

	client.applySkinClientSide(grit);
	client.applySkinClientSide(grit);
	client.reapplyActiveSkinToDocuments();

	expect(hasTokens(canvas)).toBe(true);
});

test('a switch removes them from every canvas document', () => {
	const canvas = addCanvas();
	const preview = addCanvas('preview');

	client.applySkinClientSide(grit);
	client.applySkinClientSide(nord);

	expect(hasTokens(canvas)).toBe(false);
	expect(hasTokens(preview)).toBe(false);
});

test('a canvas not iframed loses them too', () => {
	addTokens(document);

	client.applySkinClientSide(grit);
	expect(hasTokens(document)).toBe(true);

	client.applySkinClientSide(nord);
	expect(hasTokens(document)).toBe(false);
});

test('a canvas that mounts after the switch loses them on the next apply', () => {
	addCanvas();

	client.applySkinClientSide(grit);
	client.applySkinClientSide(nord);

	const remounted = addCanvas();
	expect(hasTokens(remounted)).toBe(true);

	client.applySkinClientSide(nord);

	expect(hasTokens(remounted)).toBe(false);
});

test('a canvas that mounts after the switch loses them on a re-apply', () => {
	addCanvas();

	client.applySkinClientSide(grit);
	client.applySkinClientSide(nord);

	clock = jest.spyOn(Date, 'now');
	afterThePause();

	const remounted = addCanvas();
	client.reapplyActiveSkinToDocuments();

	expect(hasTokens(remounted)).toBe(false);
});

test('going back to the skin the page loaded with stays clear of them', () => {
	addCanvas();

	client.applySkinClientSide(grit);
	client.applySkinClientSide(nord);
	client.applySkinClientSide(grit);

	const remounted = addCanvas();
	client.applySkinClientSide(grit);

	expect(hasTokens(remounted)).toBe(false);
});

test('a payload with no slug changes nothing', () => {
	const canvas = addCanvas();

	client.applySkinClientSide(grit);
	client.applySkinClientSide({ slug: '' });

	expect(hasTokens(canvas)).toBe(true);
});

describe('a re-apply with a payload', () => {
	function canvasSkin(doc) {
		return {
			bodyClass: doc.body.className,
			style: doc
				.getElementById('theme-skin-switcher-active-skin-style')
				?.getAttribute('data-theme-skin-href'),
		};
	}

	test('for another skin removes them and records that skin', () => {
		const canvas = addCanvas();
		const preview = addCanvas('preview');

		client.applySkinClientSide(grit);
		client.reapplyActiveSkinToDocuments(nord);

		expect(hasTokens(canvas)).toBe(false);
		expect(hasTokens(preview)).toBe(false);
		expect(client.getLastAppliedSkinSlug()).toBe('nord');
		expect(canvasSkin(canvas)).toEqual({
			bodyClass: nord.bodyClass,
			style: nord.styleCssUrl,
		});
	});

	test('for the skin before a switch records it as the last applied', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);

		clock = jest.spyOn(Date, 'now');
		afterThePause();

		client.reapplyActiveSkinToDocuments(grit);

		expect(client.getLastAppliedSkinSlug()).toBe('grit');
	});

	test('a canvas that mounts after a discard gets the kept skin', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);

		jest.advanceTimersByTime(3000);

		client.reapplyActiveSkinToDocuments(grit);
		jest.advanceTimersByTime(3000);

		const remounted = addCanvas();
		client.reapplyActiveSkinToDocuments();

		expect(hasTokens(remounted)).toBe(false);
		expect(canvasSkin(remounted)).toEqual({
			bodyClass: grit.bodyClass,
			style: grit.styleCssUrl,
		});
	});

	test('a canvas the observer sees mount after a discard gets the kept skin', async () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);
		jest.advanceTimersByTime(3000);

		client.reapplyActiveSkinToDocuments(grit);
		jest.advanceTimersByTime(3000);

		const remounted = addCanvas();
		await Promise.resolve();
		jest.advanceTimersByTime(120);

		expect(canvasSkin(remounted).bodyClass).toBe(grit.bodyClass);
		expect(canvasSkin(remounted).style).toBe(grit.styleCssUrl);
	});

	test('a canvas that mounts after an outside edit loses them too', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.reapplyActiveSkinToDocuments(nord);
		jest.advanceTimersByTime(3000);

		const remounted = addCanvas();
		client.reapplyActiveSkinToDocuments();

		expect(hasTokens(remounted)).toBe(false);
		expect(canvasSkin(remounted).bodyClass).toBe(nord.bodyClass);
	});

	test('for the same skin changes nothing', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);

		const head = canvas.head.innerHTML;
		client.reapplyActiveSkinToDocuments({ ...grit });

		expect(hasTokens(canvas)).toBe(true);
		expect(canvas.head.innerHTML).toBe(head);
		expect(client.getLastAppliedSkinSlug()).toBe('grit');
	});

	test('as the first apply at boot keeps them', () => {
		const canvas = addCanvas();

		client.reapplyActiveSkinToDocuments(grit);

		expect(hasTokens(canvas)).toBe(true);
		expect(client.getLastAppliedSkinSlug()).toBe('grit');
	});

	test('as the first apply at boot does not start a switch', () => {
		addCanvas();

		client.reapplyActiveSkinToDocuments(grit);
		client.reapplyActiveSkinToDocuments();

		const remounted = addCanvas();
		client.reapplyActiveSkinToDocuments();

		expect(hasTokens(remounted)).toBe(true);
	});

	test('with no slug changes nothing', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);
		client.reapplyActiveSkinToDocuments({ slug: '' });

		expect(hasTokens(canvas)).toBe(true);
		expect(client.getLastAppliedSkinSlug()).toBe('grit');
	});
});
