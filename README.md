# theme-skin-switcher

WordPress library for block theme **design skins**: discovery, `theme.json` merge, Site Editor switcher, and provisioning (REST + WP-CLI).

Works with any theme that ships a `design-skins/` directory (typically a child theme).

## Requirements

- PHP 8.1+
- WordPress 6.5+ (block themes, Site Editor)

## Installation

```bash
composer require builtnorth/theme-skin-switcher
```

## Boot

From your theme `functions.php` (after Composer autoload):

```php
\ThemeSkinSwitcher\App::get_instance()->boot();
```

## Theme layout

The engine auto-registers the first active theme directory that contains `design-skins/` (child stylesheet, then parent template):

```
your-theme/
  design-skins/
    fresh/
      variation.json
      style.scss
      section-patterns/
      section-dividers/
  build/design-skins/
    fresh/style.css
  styles/              # optional core color/typography variations
```

## WP-CLI

```bash
wp theme-skin-switcher list-styles
wp theme-skin-switcher set-style fresh
wp theme-skin-switcher get-style
```

## REST API

- `GET|POST /wp-json/theme-skin-switcher/v1/style`

## Hooks

| Hook | Purpose |
|------|---------|
| `theme_skin_switcher_register` | After skin pack registration; call `SkinPackRegistry::register()` to override |
| `theme_skin_switcher_client_switch_payload` | Filter editor switch payload |
| `theme_skin_switcher_client_custom_theme_properties` | Filter CSS custom properties for client preview |
| `theme_skin_switcher_editor_experience_data` | Filter section pattern/divider paths for the active skin |
| `theme_skin_switcher_section_pattern_base_directories` | Extra base dirs when resolving section pattern SVGs |

Deprecated aliases (`compass_waas_*`, `polaris_editor_experience_data`, `polaris_section_pattern_base_directories`) remain supported when listeners are registered.

## Active skin storage

Theme mod key: `theme_skin_switcher_active_style` (migrates automatically from `compass_waas_active_style`).

Body class on front and editor: `theme-skin-{slug}`.

## Optional integrations

If Polaris is present, skin overrides still apply via the legacy `polaris_editor_experience_data` and `polaris_section_pattern_base_directories` hooks — no hard dependency.

## Development

```bash
npm install
npm run start   # watch editor bundle
npm run build
```

Editor script handle: `theme-skin-switcher-editor`.
