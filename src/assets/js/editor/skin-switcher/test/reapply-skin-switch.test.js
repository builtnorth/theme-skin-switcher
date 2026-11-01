/**
 * A re-apply with a payload for another skin (a discard, an undo, an outside
 * edit) is a full switch, even during the pause after the last one. Anything
 * else stays a plain re-apply, which the pause holds back. The module remembers
 * the last skin, so each test loads a fresh copy.
 */
const TOKENS_ID = 'theme-skin-switcher-canvas-tokens-inline-css';
const PHP_LINK_ID = 'theme-skin-php-style-css';

function skin(slug) {
	return {
		slug,
		bodyClass: `theme-skin-${slug}`,
		styleCssUrl: `https://example.test/build/design-skins/${slug}/style.css`,
		editorExperience: { active_style: { slug } },
	};
}

const grit = skin('grit');
const nord = skin('nord');

let client;
let changed;

function addCanvas() {
	const frame = document.createElement('iframe');
	frame.setAttribute('name', 'editor-canvas');
	document.body.appendChild(frame);

	return frame.contentDocument;
}

function addPhpStylesheet(doc, payload) {
	const link = doc.createElement('link');
	link.id = PHP_LINK_ID;
	link.rel = 'stylesheet';
	link.href = payload.styleCssUrl;
	doc.head.appendChild(link);
}

function addTokens(doc) {
	const style = doc.createElement('style');
	style.id = TOKENS_ID;
	doc.head.appendChild(style);
}

function activeStyle() {
	return window.kitaris_localize.blocks.editor_experience.active_style.slug;
}

beforeEach(() => {
	jest.resetModules();
	jest.useFakeTimers();

	document.head.innerHTML = '';
	document.body.innerHTML = '';
	document.body.className = '';
	window.theme_skin_switcher = { activeSlug: 'grit' };
	delete window.kitaris_localize;

	client = require('../apply-skin-client');

	changed = jest.fn();
	document.addEventListener(client.SKIN_CHANGED_EVENT, changed);
});

afterEach(() => {
	document.removeEventListener(client.SKIN_CHANGED_EVENT, changed);
	delete window.theme_skin_switcher;
	jest.useRealTimers();
});

describe('for another skin', () => {
	test('a quick discard inside the pause applies', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);
		jest.advanceTimersByTime(1000);

		client.reapplyActiveSkinToDocuments(grit);

		expect(client.getLastAppliedSkinSlug()).toBe('grit');
		expect(canvas.body.className).toBe('theme-skin-grit');
	});

	test('a quick discard outlasts the timers of the switch before it', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);
		jest.advanceTimersByTime(1000);

		client.reapplyActiveSkinToDocuments(grit);
		jest.advanceTimersByTime(10000);

		expect(canvas.body.className).toBe('theme-skin-grit');
	});

	test('the old skin\'s stylesheet from PHP goes', () => {
		const canvas = addCanvas();
		addPhpStylesheet(canvas, grit);

		client.applySkinClientSide(grit);
		expect(canvas.getElementById(PHP_LINK_ID)).not.toBeNull();

		client.reapplyActiveSkinToDocuments(nord);

		expect(canvas.getElementById(PHP_LINK_ID)).toBeNull();
	});

	test('the switcher hears of it once', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);
		changed.mockClear();

		client.reapplyActiveSkinToDocuments(grit);

		expect(changed).toHaveBeenCalledTimes(1);
		expect(changed.mock.calls[0][0].detail.slug).toBe('grit');
	});

	test('the editor experience follows', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);
		expect(activeStyle()).toBe('nord');

		client.reapplyActiveSkinToDocuments(grit);

		expect(activeStyle()).toBe('grit');
	});

	test('the boot data follows', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.reapplyActiveSkinToDocuments(nord);

		expect(window.theme_skin_switcher.activeSlug).toBe('nord');
	});

	test('it starts the pause a switch starts', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		expect(client.isSkinPersistencePaused()).toBe(false);

		client.reapplyActiveSkinToDocuments(nord);

		expect(client.isSkinPersistencePaused()).toBe(true);
	});
});

describe('a plain re-apply', () => {
	test('for the same skin is held back by the pause', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);

		const head = canvas.head.innerHTML;
		canvas.body.className = 'marker';
		client.reapplyActiveSkinToDocuments({ ...nord });

		expect(canvas.head.innerHTML).toBe(head);
		expect(canvas.body.className).toBe('marker');
	});

	test('with no payload is held back by the pause', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);

		canvas.body.className = 'marker';
		client.reapplyActiveSkinToDocuments();

		expect(canvas.body.className).toBe('marker');
	});

	test.each([
		['the same skin', () => ({ ...grit })],
		['no payload', () => null],
	])('with %s is no switch', (label, payload) => {
		const canvas = addCanvas();
		addTokens(canvas);
		addPhpStylesheet(canvas, grit);

		client.applySkinClientSide(grit);
		changed.mockClear();
		window.kitaris_localize.blocks.editor_experience = { active_style: { slug: 'marker' } };

		client.reapplyActiveSkinToDocuments(payload());

		expect(changed).not.toHaveBeenCalled();
		expect(activeStyle()).toBe('marker');
		expect(client.isSkinPersistencePaused()).toBe(false);
		expect(canvas.getElementById(TOKENS_ID)).not.toBeNull();
		expect(canvas.getElementById(PHP_LINK_ID)).not.toBeNull();
	});

	test('as the first apply at boot is no switch', () => {
		const canvas = addCanvas();
		addTokens(canvas);
		addPhpStylesheet(canvas, grit);

		client.reapplyActiveSkinToDocuments(grit);

		expect(client.getLastAppliedSkinSlug()).toBe('grit');
		expect(changed).not.toHaveBeenCalled();
		expect(window.kitaris_localize).toBeUndefined();
		expect(client.isSkinPersistencePaused()).toBe(false);
		expect(canvas.getElementById(TOKENS_ID)).not.toBeNull();
		expect(canvas.getElementById(PHP_LINK_ID)).not.toBeNull();
	});

	test('with a payload with no slug does nothing, even after a switch', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		changed.mockClear();

		client.reapplyActiveSkinToDocuments({ slug: '' });

		expect(client.getLastAppliedSkinSlug()).toBe('grit');
		expect(changed).not.toHaveBeenCalled();
	});
});

describe('a canvas that mounts during the pause', () => {
	const styled = {
		...nord,
		customThemeProperties: { '--wp--custom--radius': '4px' },
		variationThemeJsonCss: ':root{--wp--custom--gap:1px}',
	};

	function canvasStyle(doc) {
		return doc.getElementById('theme-skin-switcher-active-skin-style')?.getAttribute('data-theme-skin-href');
	}

	function canvasWithTokens() {
		const canvas = addCanvas();
		addTokens(canvas);

		return canvas;
	}

	test('gets the kept skin after a discard', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);
		jest.advanceTimersByTime(3000);

		client.reapplyActiveSkinToDocuments(grit);
		jest.advanceTimersByTime(100);

		const canvas = canvasWithTokens();
		jest.advanceTimersByTime(10000);

		expect(canvasStyle(canvas)).toBe(grit.styleCssUrl);
		expect(canvas.body.className).toBe('theme-skin-grit');
		expect(canvas.getElementById(TOKENS_ID)).toBeNull();
	});

	test('gets the new skin after a normal switch', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);
		jest.advanceTimersByTime(100);

		const canvas = canvasWithTokens();
		jest.advanceTimersByTime(10000);

		expect(canvasStyle(canvas)).toBe(nord.styleCssUrl);
		expect(canvas.body.className).toBe('theme-skin-nord');
		expect(canvas.getElementById(TOKENS_ID)).toBeNull();
	});

	test('has it no sooner than the pause ends', () => {
		addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);

		const canvas = addCanvas();
		jest.advanceTimersByTime(2499);

		expect(canvasStyle(canvas)).toBeUndefined();

		jest.advanceTimersByTime(1201);

		expect(canvasStyle(canvas)).toBe(nord.styleCssUrl);
	});

	test.each([
		['just after the first switch', 1000],
		['just before the first retry', 2890],
		['between the retries', 3000],
	])('ends with the newest skin when another switch comes %s', (label, wait) => {
		const third = skin('third');

		addCanvas();
		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);

		const canvas = addCanvas();
		jest.advanceTimersByTime(wait);

		client.applySkinClientSide(third);
		jest.advanceTimersByTime(10000);

		expect(canvasStyle(canvas)).toBe(third.styleCssUrl);
		expect(canvas.body.className).toBe('theme-skin-third');
		expect(client.getLastAppliedSkinSlug()).toBe('third');
	});

	test('ends with the discard that follows a switch at once', () => {
		addCanvas();
		client.applySkinClientSide(grit);
		client.applySkinClientSide(nord);

		const canvas = addCanvas();
		jest.advanceTimersByTime(1000);

		client.reapplyActiveSkinToDocuments(grit);
		jest.advanceTimersByTime(10000);

		expect(canvasStyle(canvas)).toBe(grit.styleCssUrl);
	});

	test('a canvas already right is left as it is by the retries', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);
		client.applySkinClientSide(styled);
		changed.mockClear();

		const head = canvas.head.innerHTML;
		jest.advanceTimersByTime(10000);

		expect(canvas.head.innerHTML).toBe(head);
		expect(canvas.querySelectorAll('link[rel="stylesheet"]')).toHaveLength(1);
		expect(changed).not.toHaveBeenCalled();
		expect(client.isSkinPersistencePaused()).toBe(false);
	});

	test('the first apply at boot schedules no retries', () => {
		const canvas = addCanvas();

		client.applySkinClientSide(grit);
		canvas.body.className = 'marker';
		jest.advanceTimersByTime(10000);

		expect(canvas.body.className).toBe('marker');
	});
});
