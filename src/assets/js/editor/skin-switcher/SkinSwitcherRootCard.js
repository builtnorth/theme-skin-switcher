/**
 * Design skin field on the Global Styles home screen.
 */
import { useMemo } from '@wordpress/element';
import { CustomSelectControl, __experimentalText as Text } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useActiveSkin from './useActiveSkin';
import useApplySkin from './useApplySkin';
import SkinPreviewImage from './SkinPreviewImage';
import { withNoSkinPlaceholder } from './skin-select-options';

export default function SkinSwitcherRootCard() {
	const { activeSlug, styles, isLoading, setActiveSlug } = useActiveSkin();
	const { applySkin, error } = useApplySkin({
		onApplied: (response) => {
			if (response?.slug) {
				setActiveSlug(response.slug);
			}
		},
	});

	const selectOptions = useMemo(
		() =>
			withNoSkinPlaceholder(
				styles.map((style) => ({
					key: style.slug,
					name: style.title,
					icon: <SkinPreviewImage style={style} />,
					className: `theme-skin-switcher__option theme-skin-switcher__option--${style.slug}`,
				})),
				activeSlug,
				__('Choose a design skin', 'theme-skin-switcher')
			),
		[styles, activeSlug]
	);

	const selectedOption = useMemo(() => {
		if (!selectOptions.length) {
			return undefined;
		}

		return (
			selectOptions.find((option) => option.key === activeSlug) ||
			selectOptions[0]
		);
	}, [selectOptions, activeSlug]);

	const handleChange = ({ selectedItem }) => {
		if (!selectedItem?.key) {
			return;
		}

		applySkin(selectedItem.key);
	};

	return (
		<div className="theme-skin-switcher__field">
			<CustomSelectControl
				__next40pxDefaultSize
				className="theme-skin-switcher__select"
				label={__('Design skin', 'theme-skin-switcher')}
				help={__(
					'Section patterns, dividers, and skin CSS. Your Global Styles colors and fonts stay as you set them.',
					'theme-skin-switcher'
				)}
				options={selectOptions}
				value={selectedOption}
				onChange={handleChange}
				disabled={isLoading || !selectOptions.length}
				showSelectedHint
			/>

			{error && (
				<Text
					as="p"
					className="theme-skin-switcher__error"
					role="alert"
				>
					{error}
				</Text>
			)}
		</div>
	);
}
