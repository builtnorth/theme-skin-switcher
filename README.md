# theme-skin-switcher

Composer library for Compass WaaS design skins: discovery, `theme.json` merge, Site Editor switcher, and provisioning (REST + WP-CLI).

## Usage

Required by [`compass-waas`](../../wp-content/themes/compass-waas) and booted from the parent theme `functions.php`:

```php
\ThemeSkinSwitcher\App::get_instance()->boot();
```

Skin assets (`design-skins/`, `styles/`, fonts) live on the **active child theme** (e.g. `compass-waas-trades`). The engine auto-registers the first theme directory that contains `design-skins/`.

## Dev monorepo

- PHP autoload: root `vendor/builtnorth/theme-skin-switcher` → `composer_packages/theme-skin-switcher`
- Public assets: symlink `wp-content/packages/theme-skin-switcher` → `../../composer_packages/theme-skin-switcher` (for editor `build/` URLs)
- npm workspace: `composer_packages/theme-skin-switcher` (`npm run watch:theme-skin-switcher` from project root)

## WP-CLI

```bash
lando wp theme-skin-switcher list-styles
lando wp theme-skin-switcher set-style fresh
lando wp theme-skin-switcher get-style
```

## REST

- `GET|POST /wp-json/theme-skin-switcher/v1/style`
