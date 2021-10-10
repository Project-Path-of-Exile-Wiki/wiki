MinervaNeue skin
========================

The MinervaNeue skin is a responsive mobile-first skin for your mediawiki instance.

Installation
------------

See <https://www.mediawiki.org/wiki/Skin:MinervaNeue>

Development
-----------

### Coding conventions

Please follow the coding conventions of MobileFrontend:
<https://www.mediawiki.org/wiki/MobileFrontend/Coding_conventions>

### Config

The following configuration options will apply only to the default mobile skin - Minerva.

#### $wgMinervaAlwaysShowLanguageButton

* Type: `Boolean`
* Default: `true`

Whether to show the language switcher button even if no languages are available
for the page.

#### $wgMinervaEnableSiteNotice

* Type: `Boolean`
* Default: `false`

Controls whether site notices should be shown.
See <https://www.mediawiki.org/wiki/Manual:$wgSiteNotice>.

#### $wgMinervaShowCategoriesButton

* Type: `Array`
* Default:
```php
  [
    'base' => false,
    'beta' => true,
  ]
```
Controls whether the category button should be displayed.

#### $wgMinervaApplyKnownTemplateHacks

* Type: `Boolean`
* Default: `false`

When enabled and hacks.less exists, hacks.less workarounds are included in stylesheet. These should only be needed for Wikimedia based wikis or wikis using common templates such as Template:Infobox on those wikis.

#### $wgMinervaDonateLink

* Type: `Array`
* Default:
```php
  [
    'base' => true,
  ]
```

When enabled a donate link will be added to the main menu. The donate link uses the `sitesupport` and `sitesupport-url` mediawiki messages.

#### $wgMinervaPageActions

* Type: `Array`
* Default: `['edit', 'talk', 'watch', 'switch-language']`

Controls which page actions, if any, are displayed. Allowed: `edit`, `watch`, `talk`, and
`switch-language`.


#### $wgMinervaPageIssuesNewTreatment

* Type: `Array`
* Default:
```php
  [
    'base' => false,
    'beta' => true,
  ]
```
Controls whether page issues should be replaced with a "Page issues" link (false) or displayed inline (true).

#### $wgMinervaTalkAtTop

* Type: `Array`
* Default:
```php
  [
    'beta' => false,
    'base' => false,
    'amc' => true,
  ]
```
Controls whether the talk option should be displayed at the top of the page.
This will work for all pages except the main page.

#### $wgMinervaHistoryInPageActions

* Type: `Array`
* Default:
```php
  [
    'beta' => false,
    'base' => false,
    'amc' => true,
  ]
```
Controls whether the history link appears in the page actions menu.

#### $wgMinervaAdvancedMainMenu
* Type: `Array`
* Default:
```php
  [
    'beta' => false,
    'base' => false,
    'amc' => true,
  ]
```
Controls whether the main menu is expanded to contain recent changes and various options
that require login are removed. Note, should be enabled alongside `$wgMinervaPersonalMenu` to avoid losing access to features (in particular logout button).

#### $wgMinervaPersonalMenu
* Type: `Array`
* Default:
```php
  [
    'beta' => false,
    'base' => false,
    'amc' => true,
  ]
```
Controls whether a personal menu appears in the header chrome. This menu contains pages such as Special:Watchlist. Note, should be enabled alongside `$wgMinervaAdvancedMainMenu` to avoid duplicating links to functionality as many of the links duplicate links in the standard main menu. Note a sandbox link will be present if the extension `SandboxLink` is enabled.

#### $wgMinervaOverflowInPageActions

* Type: `Array`
* Default:
```php
  [
    'beta' => false,
    'base' => false,
    'amc' => false,
  ]
```
Controls whether the overflow link appears in the page actions menu.

```
Controls whether the share feature should be added to the page actions menu.


#### $wgMinervaAlwaysShowLanguageButton

* Type: `Boolean`
* Default: `true`

Whether to show the language switcher button even if no languages are available for the page.

#### $wgMinervaABSamplingRate

* Type: `Number`
* Default: `0`

On a scale of 0 to 1, determines the chance a user has of entering an AB test.
A test is divided into 3 buckets, "control" "A" and "B". Users that are selected for the
test have an equal chance of entering bucket "A" or "B", the remaining users fall into the
"control" bucket and are excluded from the test.

1    - would run test on 100% of users (50% in A and 50% in B, 0 in control).
0.5  - would run test on 50% of users (25% in A, 25% in B, 50% in control).
0.05 - would run test on 5% of users (2.5% in A, 2.5% in B, 95% in control).
0 would disable the test and place all users in "control".

Group assignment is universal no matter how many tests are running since both
`wgMinervaABSamplingRate` and `mw.user.sessionId()` are globals.

Group membership can be debugged from the console via:

```js
  const AB = mw.mobileFrontend.require('skins.minerva.scripts/AB')
  new AB({
    testName: 'WME.PageIssuesAB',
    samplingRate: mw.config.get( 'wgMinervaABSamplingRate', 0 ),
    sessionId: mw.user.sessionId()
  }).getBucket()
```

And since session ID is an input in calculating the group, reassignment occurs
when clearing it: `mw.storage.session.remove('mwuser-sessionId')`.

### Components

Components may be shared between server and client. Keeping all code for a single component only in
one directory makes it easier to understand the complete domain of a component, all of its implicit
dependencies, and also what it is independent of. The structure does not hint at ResourceLoader
module bundling of resources and code. That is the domain of skin.json.

New components are stored under components/. Potential older components are stored under includes/
and resources/, and those directory structures imperfectly represent ResourceLoader module
divisions.

#### Mustache

Mustache templates at the root components/ directory, like components/PageActionsMenu.mustache or
components/ToggleList.mustache, are designed to be rendered as root templates not partials. E.g.:

```lang=php
// 🆗

$templatesDir = __DIR__ . '/../../components';
$templateParser = new TemplateParser( $templatesDir );

// Render components/ToggleList.mustache not components/ToggleList/ToggleList.mustache.
$html = $templateParser->processTemplate( 'ToggleList',  $data );
```

Attempting to render a partial as a template root will fail because of components/ root path
assumptions:

```lang=php
// 🚫

$templatesDir = __DIR__ . '/../../components/ToggleList';
$templateParser = new TemplateParser( $templatesDir );

// Error: components/ToggleList/ToggleList.mustache references
// components/ToggleList/ToggleList/ToggleListItem.mustache which does not exist.
$html = $templateParser->processTemplate( 'ToggleList',  $data );
```

Partials in components/ subdirectories, like components/PageActionsMenu/PageActionsMenu.mustache or
components/ToggleList/ToggleList.mustache, are for in-template partial composition only as their
paths assume the render root is components/. E.g.:

```lang=mustache
{{! Include components/ToggleList/ToggleList.mustache, not components/ToggleList.mustache. }}
{{> ToggleList/ToggleList}}
```
