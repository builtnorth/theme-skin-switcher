/**
 * Preview thumbnail beside skin names in the select list.
 */
export default function SkinPreviewImage({ style }) {
	const src = style?.previewImageUrl;

	if (!src) {
		return (
			<span
				className="compass-waas-skin-switcher__option-preview compass-waas-skin-switcher__option-preview--fallback"
				aria-hidden="true"
			/>
		);
	}

	return (
		<img
			className="compass-waas-skin-switcher__option-preview"
			src={src}
			alt=""
			width={32}
			height={32}
			loading="lazy"
			decoding="async"
			draggable={false}
		/>
	);
}
