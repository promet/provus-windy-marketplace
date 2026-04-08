This is a starter kit for creating a Drupal CMS-based site template. The idea is to customize it into a fantastic site template using Drupal CMS's basic feature set, plus your own additions.

To use it, you'll:

* Copy this project into your own repository
* Follow the instructions below to set up a working Drupal site
* Make changes to that site (via the UI, Drush, or any other way)
* Use Drush to export the changes back into your repository
* Publish your repository

Now others will be able to pull that repository and use it spin up a Drupal site exactly like the one you exported.

## Getting Started

**Prerequisites**: [DDEV](https://ddev.com) 1.25.0 or later. DDEV is Drupal CMS's Docker-based local development environment of choice; you don't _have_ to use it, but we strongly recommend it because it provides everything you need and configures it for you. These instructions will assume you have DDEV [installed](https://docs.ddev.com/en/stable).

This starter kit is included with Drupal CMS version 2.0.1 or later! [Follow DDEV's quick-start instructions to create a new Drupal CMS project](https://docs.ddev.com/en/stable/users/quickstart/#drupal-drupal-cms), then run `ddev launch` and install the "Blank" site template.

You're now ready to build your Drupal site however you like!

## Publishing
When you're happy with your site, you can export it as a recipe:

```shell
ddev drush site:export
```

This will export the recipe at `./recipes/site_export` by default (you can override that with the `--destination` option). After that, you can safely delete this starter kit; you don't need it anymore.

Do a final round of customization to prepare for release:
- Update the `name` field in `composer.json`.
- Review `recipe.yml` and make any desired changes; at least make sure the `name` and `description` fields are correct.
- Customize `README.md`'s documentation and installation instructions.

We also recommend that you:
- Replace `screenshot.webp` with a screenshot of your site template. For compatibility with the Drupal CMS installer, it should be 632x363 and named `screenshot.webp`.
- Review and customize `recommended.yml` if you want to suggest additional add-ons to end users.
- Rename `.gitattributes.example` to `.gitattributes`, and customize it as needed.

Finally, [publish the site template as a general project on drupal.org](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/creating-a-new-project/how-to-create-a-new-project#s-project-types). We recommend publishing on drupal.org, but you don't _have_ to; you can, for instance, host your site template's repository on GitHub and publish it directly to Packagist, but you'll need to manually set up any necessary CI workflows and web hooks.

You can, of course, make any changes you like to your site template; use `drush site:export`, as described above, to export everything again. Site templates are starting points for new sites and don't support upgrade paths, so any changes you make will _not_ affect any sites in the wild that were created from your site template.

## Things to Remember

You have a tremendous amount of freedom in how you build your site template and how it looks, but there are a few ironclad rules you need to follow:

- In `recipe.yml`, the `type` field must be `Site` (case sensitive).
- In `composer.json`, the `type` field must be `drupal-recipe`.
- Site templates **cannot** patch dependencies in any way (e.g., with the `cweagans/composer-patches` plugin). Patching dependencies can introduce maintenance problems for end users that require significant technical skills to fix.
- A site template **cannot** depend on any particular install profile. It can do light integration with certain install profiles if it so chooses (and indeed, [Recipe Installer Kit](https://www.drupal.org/project/recipe_installer_kit) takes advantage of this), but it cannot _depend_ on them.
- If you're publishing your site template on Drupal.org (or not, but _especially_ if you are), don't prefix its name with `drupal_cms_` or `drupal-cms-`. This is because, although your site template may be built _on top of_ Drupal CMS, it is not _part of_ Drupal CMS. We want to avoid creating confusion around which packages are part of Drupal CMS.
- Site templates **cannot** pin to specific versions of their dependencies (e.g., `"drupal/token": "1.13"`), because it can cause consumers of the site template to get stuck on outdated or insecure dependencies. Instead, use version constraint operators like `^1` (recommended) or `~2.1.4` (more restrictive). [See Composer's documentation](https://getcomposer.org/doc/articles/versions.md#next-significant-release-operators) for more information.
- You **MUST** have the legal right to all of the content included in your site template. Any copyrighted material which you didn't create -- stock images, for example -- needs to be properly licensed. If in doubt, consult a lawyer.

## Resources
* [The complete list of Drupal CMS recipes](https://www.drupal.org/project/cms#recipes): You can use any of these in your site template, to provide basic configuration and/or content types. It's recommended, but not required.
* [RFC: The architecture and philosophy of site templates](https://git.drupalcode.org/project/drupal_cms/-/wikis/Architecture-Decision-Records/Site-Templates): Specifies the guiding principles and limitations of site templates, in quasi-technical (but mostly informal) language.
* [Recipes Initiative documentation](https://project.pages.drupalcode.org/distributions_recipes): The technical documentation for the recipe system, how to use it, its syntax, and available config actions.
* [Supplemental documentation](https://gist.github.com/phenaproxima/dda3edf5173a99d68cef92a2f08e5927) about config files versus config actions, and how recipes should use both.
* [Recipes: It's About Time](https://www.youtube.com/watch?v=mDikN0bxcpY&list=PLpeDXSh4nHjSb2nYlToJvaD84PKoouf7c&index=31): Recorded at DrupalCon Nara 2025, this is a very clear and thorough explainer on the basics of recipes.
* The [`#recipes`](https://drupal.slack.com/archives/C2THUBAVA) and [`#drupal-cms-templates`](https://drupal.slack.com/archives/C08KVJKPUBS) channels on [Drupal Slack](https://www.drupal.org/join-slack) can be very helpful if you're stuck or have questions.

## Glossary
So we're clear on terminology:

site template
: A recipe that, when applied, sets up a nearly ready-to-launch Drupal site, including all relevant functionality, default content, and a theme. Site templates are meant to be applied at the beginning of a project, then disposed of.

recipe
: A type of Drupal extension which automates site building tasks. Recipes are declarative, disposable, and "applied" to a Drupal site, rather than installed. They contain no code and do not support updates. A site template is a type of recipe.
