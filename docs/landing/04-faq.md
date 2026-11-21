---
view: components.home.faq
badge: FAQ
title: "{package.name}"
highlight: Questions
subtitle: "Quick answers about installing and using {package.name}."
---

## What is {package.name}?

{card.description} It's a free, open-source {technology.name} package by Pharaonic.

## How do I install {package.name}?

Run `composer require {package.composer}` in your project's root directory.

## What does {package.name} require?

The latest release requires {package.requiresText}.

## Do modules need their own composer.json?

No. A directory with `app/` in a pool is a module, and its classes autoload from it, including with `composer dump-autoload --classmap-authoritative` after `php artisan module:cache`.

## Can a Composer package ship modules?

Yes. The package declares its pools under `extra.pharaonic.modulator.pools` in its `composer.json`, and `php artisan module:publish` copies a module into your application, where the local copy overrides the package.

## Do my 1.x modules keep working?

Yes. Modules in `app/Modules` are discovered by the `app` pool with the 1.x rules. The documentation has an upgrade guide for moving them to the new layout.

## Is {package.name} free to use?

Yes. {package.name} is open source under the {package.license} license, so you can use it in personal and commercial projects.

## Where can I find the {package.name} documentation?

Read the [{package.name} documentation]({package.docsUrl}) for setup, configuration, and usage examples.

## How do I report a bug or contribute to {package.name}?

Open an issue or a pull request on [GitHub]({package.githubUrl}), ask in [GitHub Discussions](https://github.com/orgs/Pharaonic/discussions), or chat with us on [Discord](https://discord.gg/XQG9RhvEvf).
