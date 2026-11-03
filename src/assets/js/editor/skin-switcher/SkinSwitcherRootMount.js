/**
 * Mounts skin switcher controls at the top of the Global Styles home screen (same card as core presets).
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import SkinSwitcherRootCard from './SkinSwitcherRootCard';
import { reapplyActiveSkin } from './skin-persistence';

const SKIN_MOUNT_ID = 'theme-skin-switcher-root-mount';

function findStylesRootStack() {
	// WP 7.0+: Global Styles UI moved to @wordpress/global-styles-ui (new root class).
	// Pre-7.0: .edit-site-global-styles-screen-root in @wordpress/edit-site.
	const root =
		document.querySelector('.global-styles-ui-screen-root') ||
		document.querySelector('.edit-site-global-styles-screen-root');

	if (!root) {
		return null;
	}

	const cardBody = root.querySelector('.components-card__body');

	if (!cardBody) {
		return null;
	}

	return (
		cardBody.querySelector('.components-v-stack') ||
		cardBody.querySelector('.components-flex-gap-4') ||
		cardBody.querySelector(':scope > div')
	);
}

function isMountConnected(mount) {
	return Boolean(mount?.isConnected);
}

function ensureMount(stack) {
	let mount = document.getElementById(SKIN_MOUNT_ID);

	if (mount && !stack.contains(mount)) {
		mount.remove();
		mount = null;
	}

	if (!mount) {
		mount = document.createElement('div');
		mount.id = SKIN_MOUNT_ID;
		stack.insertBefore(mount, stack.firstChild);
	}

	return mount;
}

export default function SkinSwitcherRootMount() {
	const [skinMountNode, setSkinMountNode] = useState(null);

	const tryAttach = useCallback(() => {
		const stack = findStylesRootStack();

		if (!stack) {
			return false;
		}

		setSkinMountNode(ensureMount(stack));
		reapplyActiveSkin();
		return true;
	}, []);

	const tryDetach = useCallback(() => {
		const mount = document.getElementById(SKIN_MOUNT_ID);

		if (mount && isMountConnected(mount)) {
			mount.remove();
		}

		setSkinMountNode(null);
	}, []);

	useEffect(() => {
		let cancelled = false;
		let observer = null;
		let retryTimer = null;

		const sync = () => {
			if (cancelled) {
				return;
			}

			if (findStylesRootStack()) {
				tryAttach();
			} else {
				tryDetach();
			}
		};

		sync();

		observer = new MutationObserver(sync);
		observer.observe(document.body, {
			childList: true,
			subtree: true,
		});

		retryTimer = window.setInterval(sync, 400);

		return () => {
			cancelled = true;
			observer?.disconnect();
			window.clearInterval(retryTimer);
			tryDetach();
		};
	}, [tryAttach, tryDetach]);

	return skinMountNode && isMountConnected(skinMountNode)
		? createPortal(<SkinSwitcherRootCard />, skinMountNode)
		: null;
}
