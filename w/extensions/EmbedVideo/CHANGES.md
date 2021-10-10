## Patch Notes
### v3.0.0
* Added explicit consent setting
* Code Cleanup

### v2.8.0
* Renamed hitbox.tv to smashcast.tv.
* Updated Nico Video embed code.
* Added support for Twitch clips.
* Fix issues with Twitch VODs.
* Removed unused $wgFFmpegLocation that was interferring with TimedMediaHandler.
* Added Polish translation.

### v2.7.4
* Added support for playlist to evlplayer
* Added support for youtube video list
* Updated Documentation for evlplayer
* Added missing dependency for evlplayer in extension.json

### v2.7.3
* Default Twitch VOD to autoplay=false by default
* Allow videos to be sized in 1:1 aspect ratios for special use cases.

### v2.7.2
* Added feature to evlplayer to allow default video content

### v2.7.1
* Fixed issue with youku videos not embedding properly on https enabled wikis.

### v2.7.0
* Added SoundCloud support
* Added ability to use service name as a parser tag (if not defined previously)

### v2.6.1
* Added new configuration options to disable adding media handlers and file extensions.
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/issues/76
* Fixed an uninitialized array.
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/issues/77
* Fixed undefined indexes in the AudioHandler class.
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/issues/78
* Fixed class properties that were unintentionally declared as static.
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/issues/75
* Fixed RTL language issues with CSS.
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/pull/73

### v2.6.0
* Added support for vertically aligning videos.
* Improved sizing of video and audio tags in Chrome and Firefox when using the media handler tags.
* Fixed an undefined variable.
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/issues/71

### v2.5.2
* If ffmpeg is not installed on the server the FFProbe class will no longer attempt to use and instead just return generic descriptions.
* Fixed properties on FFProbe being incorrectly declared as static.
* Fixed issues with not returning generic descriptions when the local file being accessed by ffprobe is not readable.

### v2.5.1
* Added URL argument support to Twitch services.

### v2.5.0
* Added support for VideoLink tags
* Support for Disclose.tv added
* Twitch VOD support updated.
* Added #evu parser tag

### v2.4.1
* Merged Sophivorus' improvements and fixes.
 * Support for TubiTV.com with the tubitv service identifier.
 * Fixed vimeo aspect ratio.
 * Fixed dailymotion ID regex.
* Fixed error messages being double parsed.

### v2.4.0
* New media handlers to embed locally uploaded video and audio files.
 * Requires ffmpeg and ffprobe binaries to be installed.
 * Uses HTML5 <video> and <audio> tags.
* Two new settings:
 * $wgFFmpegLocation - Set the location of the ffmpeg binary.
 * $wgFFprobeLocation - Set the location of the ffprobe binary.

### v2.3.3
* Support for JW Player.

### v2.3.2
* Fix auto resize breaking when leaving full screen.

### v2.3.1
* Fixed issue #54 (EmbedVideo doesn't work with mw-collapsible class)
* Fixed issue #50 ("arg" should be "args" in addService)
* Added "inline" alignment option
* Fixed center alignment option css
* Auto Resize now targets mobile as well

### v2.3.0
* Hard cut off of support for versions older then MediaWiki 1.25
* Auto Resize attribute added
* Reverted array_key_exists() regression to fix the `<embedvideo>` tag being broken.

### v2.2.9
* Fixed issue with Twitch.tv switching over to HTTPS.
* Added support for http://media.ccc.de
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/pull/52
* New services can now be added from other extensions programmatically.
 * https://gitlab.com/hydrawiki/extensions/EmbedVideo/pull/46

### v2.2.8
* Support for Daum TVPot
 * https://github.com/Alexia/mediawiki-embedvideo/pull/38
* Fix for URL arguments for youtube and youtubeplaylist.
 * https://github.com/Alexia/mediawiki-embedvideo/pull/40
* Support for Beam.pro.
* Support for Hitbox.tv.

### v2.2.7
* Compatible with Mediawiki 1.24.0+
 * https://github.com/Alexia/mediawiki-embedvideo/pull/35

### v2.2.6
* NicoNico Video ID fixes; will work with new ID formats.

### v2.2.5
* XSS flaws reported by [Mischanix](https://github.com/Mischanix/).

### v2.2.4
* Fix Bing to work with their new URLs.
* Remove MSN as their new video service does not support embedding.
* Standardize Tudou support per their wiki.

### v2.2.3
* Added support for Youku and Tudou.

### v2.2.2
* Updated regular expression replacement pattern for Twitch URLs.  Old Twitch embed URLs do not automatically redirect.

### v2.2.1
* Fixed E_NOTICE being thrown for [undefined array indexes](https://github.com/Alexia/mediawiki-embedvideo/issues/25).
* Back ported some [PHP 5.3 compatibility changes](https://github.com/Alexia/mediawiki-embedvideo/issues/23).  Please note that future releases of EmbedVideo may not support PHP 5.3 as it is an outdated version.  Upgrading to PHP 5.4 at a minimum is recommended.

### v2.2.0
* Fixed a bug with alignment that would cause the left align to not work similar to how Mediawiki handles images and other media.
* New parser tag better suited for templates; #evt.
* New HTML like tag format that can take parameters.

### v2.1.8
* Translations updated.
* Fixed a PHP notice being thrown for the new mobile check.

### v2.1.7
* German translation thanks to [[User:Messerjokke79]].

### v2.1.6
* Added to the ability to add optional URL arguments to the generated embed URL.

### v2.1.5
* Fixed context in which resource modules are loaded.  This resolves an issue with CSS not always applying.

### v2.1.4
* [Problem with Dailymotion videos // EmbedVideo 2.1.3 (running on MediaWiki 1.23.5)](https://github.com/Alexia/mediawiki-embedvideo/issues/16)  Thanks to [Pierre-Yves](https://github.com/gentilvirus) for reporting this issue.

### v2.1.3
* [Accidental usage of PHP 5.4+ array syntax would cause a fatal error for older Mediawiki installations.](https://github.com/Alexia/mediawiki-embedvideo/pull/14)  Thanks to [Rich Bowen](https://github.com/rbowen) for reporting and submitting a patch for this issue.
* Fix for a CSS loading order issue on some wiki configurations.

### v2.1.2
* [Missing CSS for right alignment on the default container.](https://github.com/Alexia/mediawiki-embedvideo/issues/12)
* [Parameters were not being reset between parses.](https://github.com/Alexia/mediawiki-embedvideo/issues/13)

### v2.1.1
* Fixed a logic issue where the $wgEmbedVideoDefaultWidth global override was not obeyed if the video service specified a default width.
* Actually bumped the version number this time.

### v2.1
* The width parameter was changed to dimensions.  See parameter documentation above.
* New container parameter to use a standard Mediawiki thumb frame or default to a generic container.
* The description parameter no longer forces the thumb frame to be used.
* Added support for Archive.org, Blip.tv, CollegeHumor, Gfycat, Nico Nico Video, TED Talks, and Vine.
* Ability to center align embeds.
* CSS resource module.

### v2.0
* URLs from the player pages that contain the raw video ID can now be used as the ID parameter.
* Validation of the raw IDs is improved.
* Code base rewritten to have a VideoService class for future extensibility.
* Switched to HTML5 iframes wherever possible for embeds.
* All services overhauled to be up to date and working.
* The 'auto' and 'center' alignment values were removed as they were not working.  They are planned to be implement properly in the future.
