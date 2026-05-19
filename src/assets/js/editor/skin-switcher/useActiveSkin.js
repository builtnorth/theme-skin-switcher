/**
 * Active skin state from localized boot data and REST.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { getSkinSwitcherBootData } from './boot-data';
import { mergeBootFromResponse } from './client-switch-payload';
import { getLastAppliedSkinSlug, SKIN_CHANGED_EVENT } from './apply-skin-client';

export default function useActiveSkin() {
	const boot = getSkinSwitcherBootData();
	const [activeSlug, setActiveSlug] = useState(boot.activeSlug || '');
	const [styles, setStyles] = useState(boot.styles || []);
	const [isLoading, setIsLoading] = useState(
		!boot.styles?.length || !boot.switchPayloads
	);

	const handleSkinChanged = useCallback((event) => {
		if (event?.detail?.slug) {
			setActiveSlug(event.detail.slug);
		}
	}, []);

	useEffect(() => {
		document.addEventListener(SKIN_CHANGED_EVENT, handleSkinChanged);

		return () => {
			document.removeEventListener(SKIN_CHANGED_EVENT, handleSkinChanged);
		};
	}, [handleSkinChanged]);

	useEffect(() => {
		if (boot.styles?.length && boot.switchPayloads) {
			return undefined;
		}

		let cancelled = false;

		apiFetch({
			path: `/${boot.restNamespace}${boot.restRoute}`,
		})
			.then((response) => {
				if (cancelled) {
					return;
				}

				mergeBootFromResponse(boot, response);
				setActiveSlug(
					getLastAppliedSkinSlug() ||
						response.slug ||
						boot.activeSlug ||
						''
				);
				setStyles(response.styles || boot.styles || []);
			})
			.catch(() => {
				if (!cancelled) {
					setStyles(boot.styles || []);
				}
			})
			.finally(() => {
				if (!cancelled) {
					setIsLoading(false);
				}
			});

		return () => {
			cancelled = true;
		};
	}, [boot.restNamespace, boot.restRoute, boot.styles?.length, boot.switchPayloads]);

	return { activeSlug, styles, isLoading, setActiveSlug };
}
