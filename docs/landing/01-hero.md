---
view: components.packages.package-hero
badges:
  - label: Laravel Package
    color: blue
  - label: "{package.latestVersionLabel}"
    color: green
  - label: "{package.license} License"
    color: purple
  - label: "{package.downloadsShort}+ downloads"
    color: blue
  - label: AI Ready · Laravel Boost
    color: violet
eyebrow: "{package.name}"
title: Modules that
highlight: stay Laravel
buttons:
  - label: View Full Documentation
    href: "{card.docsUrl}"
    style: primary
    icon: arrow-right
  - label: View on GitHub
    href: "{package.githubUrl}"
    style: ghost
    external: true
install: "{card.install}"
labels:
  copy: Copy
  copied: Copied!
---

{package.name} turns a folder with app/ into a Laravel module: classes autoload without Composer changes, providers, routes, views and migrations load the Laravel way, and modules shipped by packages can be overridden with a local copy.
