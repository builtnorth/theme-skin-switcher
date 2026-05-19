/**
 * Design skin field on the Global Styles home screen.
 */
import { useMemo } from '@wordpress/element';
import { CustomSelectControl, __experimentalText as Text } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useActiveSkin from './useActiveSkin';
import useApplySkin from './useApplySkin';
import SkinPreviewImage from './SkinPreviewImage';

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
			styles.map((style) => ({
				key: style.slug,
				name: style.title,
				icon: <SkinPreviewImage style={style} />,
				className: `compass-waas-skin-switcher__option compass-waas-skin-switcher__option--${style.slug}`,
			})),
		[styles]
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
		<div className="compass-waas-skin-switcher__field">
			<CustomSelectControl
				__next40pxDefaultSize
				className="compass-waas-skin-switcher__select"
				label={__('Design skin', 'compass-waas')}
				help={__(
					'Section patterns, dividers, and skin CSS. Your Global Styles colors and fonts stay as you set them.',
					'compass-waas'
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
					className="compass-waas-skin-switcher__error"
					role="alert"
				>
					{error}
				</Text>
			)}
		</div>
	);
}
