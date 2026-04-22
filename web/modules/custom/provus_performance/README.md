# Provus Performance

Fixes **Largest Contentful Paint (LCP)** on Drupal sites built with Canvas (or any theme) without touching theme templates.

## What it does

On every non-admin HTML response, the module:

1. Finds the first above-the-fold `<img>` in the rendered HTML (using configurable anchors like `<main`, `role="main"`, `class="hero`).
2. Injects a `<link rel="preload" as="image" fetchpriority="high">` into `<head>` — including `imagesrcset` and `imagesizes` for responsive images — so the browser fetches the hero image immediately, in parallel with CSS/JS.
3. Removes `loading="lazy"` from that image and sets `fetchpriority="high"` and `decoding="async"` on it.
4. Emits `<link rel="preload" as="font" crossorigin>` for any font URLs you configure.

It is **theme-agnostic** and works with Canvas-placed hero images because the detection happens on the rendered HTML, not on render arrays.

## Why this fixes a 26s LCP

A 26s LCP almost always means the hero image is discovered only after render-blocking CSS/JS finishes parsing. The preload hint tells the browser about the image during initial HTML parse, so the download starts in the first round-trip. Removing `loading="lazy"` ensures the browser doesn't defer the critical image.

## Install

```bash
drush en provus_performance -y
drush cr
```

## Configure

Go to **Administration » Configuration » System » Provus Performance**
(`/admin/config/system/provus-performance`).

Recommended starting config:

- **Enable LCP optimization**: on
- **Remove `loading="lazy"` on LCP image**: on
- **Set `fetchpriority="high"` on LCP image**: on
- **Content anchors** (one per line):
  ```
  <main
  role="main"
  id="main-content"
  class="canvas
  class="region-hero
  class="hero
  ```
- **Skip if image tag contains** (one per line):
  ```
  data:
  class="logo
  id="logo
  sprite
  icon-
  /favicon
  ```
- **Font URLs to preload**: list the 1-2 `.woff2` files your theme uses for above-the-fold text, for example:
  ```
  /themes/contrib/provus_edu_theme/fonts/inter-variable.woff2
  ```

## Verifying the fix

1. Open DevTools → Network. Reload with cache disabled.
2. In the `<head>` of the document, confirm you see:
   ```html
   <link rel="preload" as="image" href="..." fetchpriority="high">
   ```
3. In the Network tab, the hero image should start downloading in the **first** wave of requests, not after CSS/JS.
4. Run PageSpeed Insights again — LCP should drop dramatically.

If the preload isn't appearing, check:

- The page isn't cached with an old version — run `drush cr`.
- The LCP element isn't actually text (if so, font preload is the answer, not image preload).
- The image isn't inside a `<noscript>` block or an excluded path.
- The `<img>` tag doesn't match one of the **Skip** substrings.

## Same-origin note

Preload works for cross-origin images too. But if your hero is served from a third-party CDN that needs its own TCP + TLS handshake, you'll still pay for that. Options:

- Serve hero images from the same origin, or
- Add a `<link rel="preconnect" href="https://cdn.example.com" crossorigin>` in your theme's `html.html.twig`, or
- Add a `hook_page_attachments()` in a site module that adds a preconnect.

## Disable temporarily

Uncheck **Enable LCP optimization** on the settings form, or `drush pmu provus_performance -y`.
