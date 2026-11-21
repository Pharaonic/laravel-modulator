---
name: Modulator

action:
  label: View on Packagist
  href: "{package.packagistUrl}"

views: components.packages

breadcrumbs:
  - label: Home
    href: route:home
  - label: Packages
    href: route:packages.index
  - label: "{technology.name} Packages"
    href: "url:/packages/{technology.slug}"
  - label: "{package.name}"

card:
  topic: architecture
  icon: grid
  tags: modules modular architecture nested-modules packages override autoload generators
  description: Laravel-native modules discovered by convention, nested at any depth, shipped by Composer packages and overridden locally, autoloaded in every Composer mode.

seo:
  title: "{package.fullName} - Modular Laravel Applications"
  description: "{package.name} is a Laravel package for modular applications: convention-based modules, nested modules, package modules with local overrides, and generators. {package.downloadsShort}+ downloads, {package.license} licensed."
  keywords: laravel modules, modular laravel, nested modules, laravel package modules, module generator, module override
  author: Pharaonic
  images:
    - "{package.cover}"
  openGraph:
    type: website
    siteName: Pharaonic
  twitter:
    card: summary_large_image

schema:
  "@type": SoftwareSourceCode
  name: "{package.name}"
  description: "{package.name} is a Laravel package for modular applications discovered by convention, with nested and package-provided modules."
  image: "{package.cover}"
  codeRepository: "{package.githubUrl}"
  programmingLanguage: PHP
  runtimePlatform: "{technology.name}"
  version: "{package.version}"
  datePublished: "{package.publishedAt}"
  dateModified: "{package.updatedAt}"
  license: "https://opensource.org/licenses/{package.license}"
  isAccessibleForFree: true
  sameAs:
    - "{package.githubUrl}"
    - "{package.packagistUrl}"
  author:
    "@id": url:/#organization
  publisher:
    "@id": url:/#organization
  interactionStatistic:
    "@type": InteractionCounter
    interactionType: https://schema.org/DownloadAction
    userInteractionCount: "{package.downloads}"
---
