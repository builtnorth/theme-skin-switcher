/**
 * Theme design skin switcher (Site Editor → Styles).
 */
import apiFetch from '@wordpress/api-fetch';
import { registerPlugin } from '@wordpress/plugins';
import { initSiteCustomProperties } from './apply-skin-client';
import { getSkinSwitcherBootData } from './boot-data';
import { initSkinPersistence } from './skin-persistence';
import { initSkinSiteEditorSave } from './skin-site-editor-save';
import SkinSwitcherRootMount from './SkinSwitcherRootMount';
import './skin-switcher.scss';

const boot = getSkinSwitcherBootData();

if (boot.nonce) {
	apiFetch.use(apiFetch.createNonceMiddleware(boot.nonce));
}

initSkinPersistence();
initSkinSiteEditorSave();
initSiteCustomProperties();

registerPlugin('theme-skin-switcher', {
	render: SkinSwitcherRootMount,
	icon: null,
});
