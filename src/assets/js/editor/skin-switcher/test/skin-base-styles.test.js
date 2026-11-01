/**
 * After a skin switch, core gets the theme's styles with that skin merged in,
 * so it rebuilds the editor's global stylesheet. The module keeps its fetched
 * skins and the latest slug, so each test loads a fresh copy.
 */
jest.mock('@wordpress/api-fetch', () => ({ __esModule: true, default: jest.fn() }));
jest.mock('@wordpress/data', () => ({ select: jest.fn(), dispatch: jest.fn() }));

const STYLESHEET = 'compass-waas-trades';

let apiFetch;
let receive;
let core;
let syncBaseStyles;

function deferred() {
	const handle = {};
	handle.promise = new Promise((resolve, reject) => {
		handle.resolve = resolve;
		handle.reject = reject;
	});

	return handle;
}

beforeEach(() => {
	jest.resetModules();

	window.theme_skin_switcher = {
		restNamespace: 'theme-skin-switcher/v1',
		restRoute: '/style',
	};

	const data = require('@wordpress/data');

	apiFetch = require('@wordpress/api-fetch').default;
	receive = jest.fn();
	core = { getCurrentTheme: jest.fn(() => ({ stylesheet: STYLESHEET })) };

	data.select.mockImplementation((store) => (store === 'core' ? core : undefined));
	data.dispatch.mockImplementation(() => ({
		__experimentalReceiveThemeBaseGlobalStyles: receive,
	}));

	({ syncBaseStyles } = require('../skin-base-styles'));
});

afterEach(() => {
	delete window.theme_skin_switcher;
});

test('fetches the skin and hands the response to core for the current theme', async () => {
	const response = { settings: { custom: {} }, styles: {} };
	apiFetch.mockResolvedValue(response);

	await syncBaseStyles('grit');

	expect(apiFetch).toHaveBeenCalledTimes(1);
	expect(apiFetch).toHaveBeenCalledWith({ path: '/theme-skin-switcher/v1/style/base?slug=grit' });
	expect(receive).toHaveBeenCalledTimes(1);
	expect(receive).toHaveBeenCalledWith(STYLESHEET, response);
});

test('encodes the slug in the query', async () => {
	apiFetch.mockResolvedValue({});

	await syncBaseStyles('a b&c=d');

	expect(apiFetch).toHaveBeenCalledWith({
		path: '/theme-skin-switcher/v1/style/base?slug=a%20b%26c%3Dd',
	});
});

test('a skin already fetched is not fetched again, and still reaches core', async () => {
	const grit = { styles: { color: { text: 'grit' } } };
	const nord = { styles: { color: { text: 'nord' } } };
	apiFetch.mockImplementation(async ({ path }) => (path.endsWith('grit') ? grit : nord));

	await syncBaseStyles('grit');
	await syncBaseStyles('nord');
	await syncBaseStyles('grit');

	expect(apiFetch).toHaveBeenCalledTimes(2);
	expect(receive).toHaveBeenCalledTimes(3);
	expect(receive).toHaveBeenLastCalledWith(STYLESHEET, grit);
});

test('an older response landing after a newer switch is dropped', async () => {
	const older = deferred();
	const newer = deferred();
	apiFetch.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);

	const first = syncBaseStyles('grit');
	const second = syncBaseStyles('nord');

	newer.resolve({ styles: 'nord' });
	await second;
	older.resolve({ styles: 'grit' });
	await first;

	expect(receive).toHaveBeenCalledTimes(1);
	expect(receive).toHaveBeenCalledWith(STYLESHEET, { styles: 'nord' });
});

test('an older response landing before the newer one is dropped too', async () => {
	const older = deferred();
	const newer = deferred();
	apiFetch.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);

	const first = syncBaseStyles('grit');
	const second = syncBaseStyles('nord');

	older.resolve({ styles: 'grit' });
	await first;

	expect(receive).not.toHaveBeenCalled();

	newer.resolve({ styles: 'nord' });
	await second;

	expect(receive).toHaveBeenCalledTimes(1);
	expect(receive).toHaveBeenCalledWith(STYLESHEET, { styles: 'nord' });
});

test('a dropped response is kept, so going back to that skin needs no fetch', async () => {
	const older = deferred();
	const newer = deferred();
	apiFetch.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);

	const first = syncBaseStyles('grit');
	const second = syncBaseStyles('nord');

	older.resolve({ styles: 'grit' });
	newer.resolve({ styles: 'nord' });
	await Promise.all([first, second]);

	await syncBaseStyles('grit');

	expect(apiFetch).toHaveBeenCalledTimes(2);
	expect(receive).toHaveBeenLastCalledWith(STYLESHEET, { styles: 'grit' });
});

test('a switch back to a cached skin beats a request still running', async () => {
	apiFetch.mockResolvedValueOnce({ styles: 'grit' });
	await syncBaseStyles('grit');

	const running = deferred();
	apiFetch.mockReturnValueOnce(running.promise);

	const nord = syncBaseStyles('nord');
	await syncBaseStyles('grit');

	running.resolve({ styles: 'nord' });
	await nord;

	expect(receive).toHaveBeenLastCalledWith(STYLESHEET, { styles: 'grit' });
});

test('a failed request dispatches nothing and does not throw', async () => {
	apiFetch.mockRejectedValue(new Error('offline'));

	await expect(syncBaseStyles('grit')).resolves.toBeUndefined();

	expect(receive).not.toHaveBeenCalled();
});

test('a failed request is tried again next time', async () => {
	apiFetch.mockRejectedValueOnce(new Error('offline'));
	await syncBaseStyles('grit');

	apiFetch.mockResolvedValueOnce({ styles: 'grit' });
	await syncBaseStyles('grit');

	expect(apiFetch).toHaveBeenCalledTimes(2);
	expect(receive).toHaveBeenCalledWith(STYLESHEET, { styles: 'grit' });
});

test.each([[''], [undefined], [null]])('no slug (%p) does nothing', async (slug) => {
	await syncBaseStyles(slug);

	expect(apiFetch).not.toHaveBeenCalled();
	expect(receive).not.toHaveBeenCalled();
});

test('no slug also cancels a request still running', async () => {
	const running = deferred();
	apiFetch.mockReturnValueOnce(running.promise);

	const first = syncBaseStyles('grit');
	await syncBaseStyles('');

	running.resolve({ styles: 'grit' });
	await first;

	expect(receive).not.toHaveBeenCalled();
});

test.each([
	['no current theme yet', () => null],
	['a theme without a stylesheet', () => ({})],
])('%s does nothing', async (_, theme) => {
	core.getCurrentTheme.mockImplementation(theme);

	await syncBaseStyles('grit');

	expect(apiFetch).not.toHaveBeenCalled();
	expect(receive).not.toHaveBeenCalled();
});

test('no core store does nothing', async () => {
	require('@wordpress/data').select.mockImplementation(() => undefined);

	await syncBaseStyles('grit');

	expect(apiFetch).not.toHaveBeenCalled();
	expect(receive).not.toHaveBeenCalled();
});
