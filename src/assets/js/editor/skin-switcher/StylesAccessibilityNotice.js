/**
 * Color contrast guidance on the Global Styles home screen.
 */
import { ExternalLink, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

const ADA_URL = "https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum";

export default function StylesAccessibilityNotice() {
	return (
		<Notice
			className="theme-skin-switcher-accessibility-notice"
			status="warning"
			isDismissible={false}
		>
			{__(
				"Colors are paired as name and name-contrast (e.g. primary and primary-contrast for text on primary background). When changing to custom colors, set enough contrast in each pair so text stays readable.",
				"theme-skin-switcher",
			)}{" "}
			<ExternalLink href={ADA_URL}>
				{__("More about WCAG 2.2 guidelines", "theme-skin-switcher")}
			</ExternalLink>
		</Notice>
	);
}
