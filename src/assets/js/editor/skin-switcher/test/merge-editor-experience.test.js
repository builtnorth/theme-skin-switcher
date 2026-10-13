/**
 * The editor experience after a client-side skin switch matches what PHP
 * localizes with that skin active (the payload carries the skin's full state).
 */
import { mergeEditorExperience } from '../apply-skin-client';

const flowPatterns = {
	enabled: true,
	available_patterns: { 'pattern-1': 'Pattern 1' },
	pattern_directory: 'design-skins/flow/section-patterns',
	pattern_urls: { 'pattern-1': 'https://example.test/flow/pattern-1.svg' },
	pattern_extensions: { 'pattern-1': 'svg' },
};

beforeEach(() => {
	window.kitaris_localize = {
		blocks: {
			editor_experience: {
				patterns: flowPatterns,
				section_divider: { enabled: true, divider_directory: 'design-skins/flow/section-dividers' },
				active_style: { slug: 'flow' },
			},
		},
	};
});

test('a skin without section patterns drops the previous skin pattern config', () => {
	mergeEditorExperience({
		patterns: { enabled: true, available_patterns: [] },
		active_style: { slug: 'arch' },
	});

	expect(window.kitaris_localize.blocks.editor_experience.patterns).toEqual({
		enabled: true,
		available_patterns: [],
	});
	expect(window.kitaris_localize.blocks.editor_experience.active_style).toEqual({ slug: 'arch' });
});

test('a skin with section patterns gets exactly its own pattern config', () => {
	const craftsman = {
		enabled: true,
		available_patterns: { 'pattern-2': 'Pattern 2' },
		pattern_directory: 'design-skins/craftsman/section-patterns',
		pattern_urls: { 'pattern-2': 'https://example.test/craftsman/pattern-2.svg' },
	};

	mergeEditorExperience({ patterns: craftsman });

	expect(window.kitaris_localize.blocks.editor_experience.patterns).toEqual(craftsman);
});

test('a patch without patterns keeps the current patterns', () => {
	mergeEditorExperience({ active_style: { slug: 'arch' } });

	expect(window.kitaris_localize.blocks.editor_experience.patterns).toEqual(flowPatterns);
});
