---
view: components.packages.features
variant: compact
badge: Key Features
title: Everything a modular Laravel app needs
subtitle: Create a folder with app/ and it is a module; the commands do the rest.
items:
  - icon: search
    title: Convention Discovery
    text: "`Modules/Shop/app/Models/Product.php` is `Modules\\Shop\\Models\\Product`, with no `composer.json` per module."
  - icon: folder
    title: Nested Modules
    text: "Child modules at any depth (`Commerce/Checkout`), with a disabled parent disabling its children."
  - icon: package
    title: Package Modules
    text: "Packages declare module pools; `module:publish` copies a module locally and the local copy wins."
  - icon: bolt
    title: Every Composer Mode
    text: "Tested under the default, `--optimize` and `--classmap-authoritative` autoloaders."
  - icon: terminal
    title: Generators
    text: "`module:make` plus a `module:make:*` for every Laravel `make:*`, with stubs you can replace."
  - icon: shield-check
    title: Safe By Default
    text: "`module:enable` edits your config in place, `module:delete` confirms, and `module:migrate:fresh` never drops your database."
---
