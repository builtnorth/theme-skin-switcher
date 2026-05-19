/**
 * Mounts skin switcher controls on the Global Styles home screen (same card as core presets).
 * Design skin at the top; ADA color guidance at the bottom.
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import SkinSwitcherRootCard from './SkinSwitcherRootCard';
import StylesAccessibilityNotice from './StylesAccessibilityNotice';
import { reapplyActiveSkin } from './skin-persistence';

const SKIN_MOUNT_ID = 'theme-skin-switcher-root-mount';
const NOTICE_MOUNT_ID = 'theme-skin-switcher-accessibility-mount';

function findStylesRootStack() {
	const root = document.querySelector('.edit-site-global-styles-screen-root');

	if (!root) {
		return null;
	}

	return (
		root.querySelector('.components-card__body .components-flex-gap-4') ||
		root.querySelector('.components-card__body > .components-v-stack') ||
		root.querySelector('.components-card__body > div')
	);
}

function isMountConnected(mount) {
	return Boolean(mount?.isConnected);
}

function ensureMount(stack, id, position) {
	let mount = document.getElementById(id);

	if (mount && !stack.contains(mount)) {
		mount.remove();
		mount = null;
	}

	if (!mount) {
		mount = document.createElement('div');
		mount.id = id;

		if (position === 'first') {
			stack.insertBefore(mount, stack.firstChild);
		} else {
			stack.appendChild(mount);
		}
	}

	return mount;
}

export default function SkinSwitcherRootMount() {
	const [skinMountNode, setSkinMountNode] = useState(null);
	const [noticeMountNode, setNoticeMountNode] = useState(null);

	const tryAttach = useCallback(() => {
		const stack = findStylesRootStack();

		if (!stack) {
			return false;
		}

		setSkinMountNode(ensureMount(stack, SKIN_MOUNT_ID, 'first'));
		setNoticeMountNode(ensureMount(stack, NOTICE_MOUNT_ID, 'last'));
		reapplyActiveSkin();
		return true;
	}, []);

	const tryDetach = useCallback(() => {
		for (const id of [SKIN_MOUNT_ID, NOTICE_MOUNT_ID]) {
			const mount = document.getElementById(id);

			if (mount && isMountConnected(mount)) {
				mount.remove();
			}
		}

		setSkinMountNode(null);
		setNoticeMountNode(null);
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

	return (
		<>
			{skinMountNode && isMountConnected(skinMountNode)
				? createPortal(<SkinSwitcherRootCard />, skinMountNode)
				: null}
			{noticeMountNode && isMountConnected(noticeMountNode)
				? createPortal(
						<StylesAccessibilityNotice />,
						noticeMountNode
					)
				: null}
		</>
	);
}
