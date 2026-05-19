# Theme Skin Switcher

A WordPress library for **block themes** that need more than core style variations: packaged **design skins** (tokens, CSS, section patterns, dividers) with a Site Editor switcher and optional CLI provisioning.

Use it in any theme or child theme that defines a `design-skins/` directory. No proprietary stack required.

## Features

- Discovers skins from `design-skins/{slug}/` on the active theme (child stylesheet first, then parent)
- Merges each skin’s `variation.json` into `theme.json` data (front end and editor)
- Site Editor UI to preview and select a skin without a full reload
- Persists the active skin via a theme mod and Site Settings
- Read-only REST endpoint for editor boot data when inline scripts are incomplete
- WP-CLI commands to list, read, and set the active skin

## Requirements

- PHP 8.1 or later
- WordPress 6.5 or later (block theme, Site Editor)

## Installation

```bash
composer require builtnorth/theme-skin-switcher
```

Register the autoloader in your theme, then boot once from `functions.php`:

```php
\ThemeSkinSwitcher\App::get_instance()->boot();
```

### Public assets (editor bundle)

The package ships compiled JavaScript and CSS under `build/`. Your theme or host must expose that directory at a URL WordPress can enqueue (for example by vendoring the package under the theme’s `vendor/` directory, or via a documented symlink in local development).

## Theme structure

Place skins on the theme that should own them (usually a **child theme**):

```text
your-theme/
├── design-skins/
│   ├── _default/                 # optional shared assets
│   │   ├── section-patterns/
│   │   └── section-dividers/
│   └── fresh/                    # one directory per skin slug
│       ├── variation.json        # theme.json-style variation payload
│       ├── style.scss            # source (optional if you only ship CSS)
│       ├── section-patterns/     # optional SVGs for pattern pickers
│       ├── section-dividers/
│       └── images/
│           └── preview.svg       # optional switcher preview
├── build/design-skins/
│   └── fresh/
│       └── style.css             # compiled skin stylesheet
└── styles/                       # optional core color/typography JSON
    ├── color/
    └── typography/
```

**`variation.json`** — Same general shape as a WordPress global style variation (settings, styles, presets). The package merges it into the active theme layer.

**`build/design-skins/{slug}/style.css`** — Enqueued on the front end and in the editor canvas when that skin is active.

Slugs are derived from directory names under `design-skins/` (lowercase, hyphenated). No manual registry is required.

## Site Editor

After boot, editors with `edit_theme_options` see a **Design skin** control in the Global Styles experience. Choosing a skin:

1. Applies preview CSS and tokens client-side
2. Marks the Site Editor as dirty so the user can save
3. On save, persists `theme_skin_switcher_active_style` in theme mods

The editor script handle is `theme-skin-switcher-editor`.

## Active skin storage

| Item | Value |
|------|--------|
| Theme mod | `theme_skin_switcher_active_style` |
| Body class | `theme-skin-{slug}` (front end and admin editor screens) |
| Block editor settings | `themeSkinSwitcherActiveSlug`, `themeSkinSwitcherActiveClass` |

Older sites that stored the active slug under a different theme mod key are migrated automatically on read.

## WP-CLI

```bash
wp theme-skin-switcher list-styles
wp theme-skin-switcher get-style
wp theme-skin-switcher set-style fresh
```

Use CLI for installs, imports, and automation. It is the supported way to **set** a skin outside the Site Editor.

## REST API

Read-only endpoint for editor boot data (styles list, switch payloads, active slug):

```http
GET /wp-json/theme-skin-switcher/v1/style
```

| Requirement | Detail |
|-------------|--------|
| Authentication | Logged-in user |
| Capability | `edit_theme_options` |
| Methods | `GET` only |
| Discovery | Omitted from the public REST route index |

There is **no** REST endpoint to change the active skin. Use WP-CLI, theme mods, or Site Editor save.

Clients should send the standard WordPress REST nonce (`X-WP-Nonce` / `wp_rest`), as with other block editor requests.

## Hooks

| Hook | When | Use |
|------|------|-----|
| `theme_skin_switcher_register` | After auto-registration of the theme pack | Register a custom pack path with `SkinPackRegistry::register()` |
| `theme_skin_switcher_client_switch_payload` | Building editor switch payload | Adjust URLs, classes, or metadata per skin |
| `theme_skin_switcher_client_custom_theme_properties` | Client-side token preview | Filter flattened `--wp--custom--*` variables |
| `theme_skin_switcher_editor_experience_data` | Editor experience config array | Point pattern/divider pickers at skin subdirectories |
| `theme_skin_switcher_section_pattern_base_directories` | Resolving section pattern SVG paths | Add filesystem base directories |

Example — force a custom pack root:

```php
add_action('theme_skin_switcher_register', function ( $registry ) {
    $registry->register([
        'id'   => 'my-pack',
        'path' => get_stylesheet_directory(),
        'url'  => get_stylesheet_directory_uri(),
    ]);
});
```

## Development

From the package root:

```bash
npm install
npm run start    # watch editor assets
npm run build    # production bundle → build/assets/editor/
```

PHP classes live under `inc/classes/` with namespace `ThemeSkinSwitcher\`.

## License

GPL-2.0-or-later
