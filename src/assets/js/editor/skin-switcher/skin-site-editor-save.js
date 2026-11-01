/**
 * Tie skin changes to the Site Editor save flow (dirty state + persist on Save).
 */
import { dispatch, select, subscribe } from '@wordpress/data';
import { getSkinSwitcherBootData } from './boot-data';
import { getSwitchPayload } from './client-switch-payload';
import { reapplyActiveSkinToDocuments } from './apply-skin-client';
import { syncBaseStyles } from './skin-base-styles';

const SKIN_PART_AREAS = ['header', 'footer'];

function getSettingKey() {
	return getSkinSwitcherBootData()?.settingKey || 'theme_skin_switcher_active_style';
}

/**
 * Read the saved or in-progress skin slug from the site settings entity.
 */
export function getSiteEditorSkinSlug() {
	const boot = getSkinSwitcherBootData();
	const key = getSettingKey();
	const core = select('core');

	if (!core) {
		return boot?.activeSlug || '';
	}

	const edits = core.getEntityRecordEdits?.('root', 'site', undefined);
	const record = core.getEntityRecord?.('root', 'site', undefined);

	if (edits && Object.prototype.hasOwnProperty.call(edits, key)) {
		return edits[key] || '';
	}

	return record?.[key] ?? boot?.activeSlug ?? '';
}

/**
 * Mark the Site Editor as dirty so the Save button enables.
 *
 * @param {string} slug
 */
export function markSkinEditInSiteEditor(slug) {
	const key = getSettingKey();

	try {
		dispatch('core').editEntityRecord('root', 'site', undefined, {
			[key]: slug,
		});
	} catch {
		// core store may not be ready yet.
	}
}

/**
 * Preview-only apply when the site entity reverts (discard / undo).
 *
 * @param {string} slug
 */
function previewSkinSlug(slug) {
	const boot = getSkinSwitcherBootData();
	const payload = getSwitchPayload(slug, '', boot);

	if (payload?.styleCssUrl) {
		reapplyActiveSkinToDocuments(payload);
	}

	if (boot) {
		boot.activeSlug = slug;
	}
}

/**
 * Show the header and footer the server gave the saved skin. A part with
 * unsaved edits keeps them; the server never replaces a part its owner changed.
 *
 * @param {Object} core The core store's selectors.
 */
function refreshSkinParts(core) {
	const stylesheet = core.getCurrentTheme?.()?.stylesheet;

	if (!stylesheet) {
		return;
	}

	SKIN_PART_AREAS.forEach((area) => {
		const id = `${stylesheet}//${area}`;

		if (
			core.hasEditsForEntityRecord?.('postType', 'wp_template_part', id) ||
			core.isSavingEntityRecord?.('postType', 'wp_template_part', id)
		) {
			return;
		}

		// The template part block reads the edited record, whose resolver core
		// tracks separately: both have to be invalidated for it to fetch again.
		['getEntityRecord', 'getEditedEntityRecord'].forEach((selector) => {
			dispatch('core').invalidateResolution(selector, [
				'postType',
				'wp_template_part',
				id,
			]);
		});
	});
}

/**
 * Subscribe to site settings saves and discard reverts.
 */
export function initSkinSiteEditorSave() {
	const boot = getSkinSwitcherBootData();
	let lastPreviewSlug = getSiteEditorSkinSlug();
	let savedSlug = boot?.activeSlug || lastPreviewSlug;
	let wasSavingSite = false;

	subscribe(() => {
		const core = select('core');

		if (!core) {
			return;
		}

		const slug = getSiteEditorSkinSlug();
		const isSaving = Boolean(
			core.isSavingEntityRecord?.('root', 'site', undefined)
		);

		if (slug && slug !== lastPreviewSlug) {
			lastPreviewSlug = slug;

			const payload = getSwitchPayload(slug, '', boot);

			if (payload?.styleCssUrl) {
				reapplyActiveSkinToDocuments(payload);
			}

			if (boot) {
				boot.activeSlug = slug;
			}

			syncBaseStyles(slug);
		}

		if (wasSavingSite && !isSaving) {
			const saved = getSiteEditorSkinSlug();

			if (boot) {
				boot.activeSlug = saved;
			}

			lastPreviewSlug = saved;

			if (
				saved !== savedSlug &&
				!core.getLastEntitySaveError?.('root', 'site', undefined)
			) {
				savedSlug = saved;
				refreshSkinParts(core);
			}
		}

		wasSavingSite = isSaving;
	});
}
