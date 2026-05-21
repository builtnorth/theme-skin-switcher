/**
 * Preview skin in the editor immediately; persist when the user saves the site.
 */
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { getSkinSwitcherBootData } from './boot-data';
import { getSwitchPayload, mergeBootFromResponse } from './client-switch-payload';
import { applySkinClientSide, getLastAppliedSkinSlug } from './apply-skin-client';
import { markSkinEditInSiteEditor } from './skin-site-editor-save';

export default function useApplySkin({ onApplied } = {}) {
	const [error, setError] = useState('');

	const applySkin = async (slug) => {
		if (!slug || slug === getLastAppliedSkinSlug()) {
			return;
		}

		const boot = getSkinSwitcherBootData();
		const previousSlug = getLastAppliedSkinSlug();

		setError('');

		try {
			const response = await apiFetch({
				path: `/${boot.restNamespace}${boot.restRoute}`,
			});

			mergeBootFromResponse(boot, response);
		} catch {
			// Fall back to boot payloads from page load.
		}

		const payload = getSwitchPayload(slug, previousSlug, boot);

		if (!payload?.styleCssUrl) {
			setError(
				__('Could not switch design skin. Please reload the editor.', 'theme-skin-switcher')
			);
			return;
		}

		applySkinClientSide(payload);
		markSkinEditInSiteEditor(slug);
		onApplied?.({ slug });
	};

	return {
		applySkin,
		isPending: false,
		error,
	};
}
