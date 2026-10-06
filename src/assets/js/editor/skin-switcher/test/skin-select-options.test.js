/**
 * The design skin select can choose any skin, including the first one when
 * no skin is active yet (CustomSelectControl only reports a changed selection).
 */
import { withNoSkinPlaceholder } from '../skin-select-options';

const skins = [
	{ key: 'arch', name: 'Arch' },
	{ key: 'fresh', name: 'Fresh' },
];

test('no active skin: a placeholder leads, so the first skin is a change', () => {
	const options = withNoSkinPlaceholder(skins, '', 'Choose');

	expect(options).toEqual([{ key: '', name: 'Choose' }, ...skins]);
});

test('an active slug that is no longer a skin gets the placeholder too', () => {
	expect(withNoSkinPlaceholder(skins, 'deleted', 'Choose')[0]).toEqual({ key: '', name: 'Choose' });
});

test('an active skin: options are unchanged', () => {
	expect(withNoSkinPlaceholder(skins, 'fresh', 'Choose')).toBe(skins);
});

test('no skins: no placeholder', () => {
	expect(withNoSkinPlaceholder([], '', 'Choose')).toEqual([]);
});
