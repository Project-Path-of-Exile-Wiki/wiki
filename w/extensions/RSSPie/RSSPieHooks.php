<?php
/**
 * Curse Inc.
 * RSS Pie
 * RSS Pie Hooks
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		GNU General Public License v2.0 or later
 * @package		RSS Pie
 * @link		https://gitlab.com/hydrawiki
 *
 **/

class RSSPieHooks {
	/**
	 * SimplePie Object
	 *
	 * @var		object
	 */
	static private $rss;

	/**
	 * Mediawiki Parser Object
	 *
	 * @var		object
	 */
	static private $parser;

	/**
	 * Sets up this extensions parser functions.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return	boolean	true
	 */
	static public function onParserFirstCallInit(Parser &$parser) {
		$parser->setHook('rss', 'RSSPieHooks::displayRSSFeed');

		return true;
	}

	/**
	 * Renders and display a RSS feed for a <rss></rss> tag.
	 *
	 * @access	public
	 * @param	string	RSS Feed URL
	 * @param	array	Array of arguments passed to tag.
	 * @param	object	Parser Object
	 * @param	object	PPFrame Object
	 * @return	mixed	array|error
	 */
	static public function displayRSSFeed($url, array $args, Parser $parser, PPFrame $frame) {
		global $wgRSSPieCacheTime;

		self::$parser = $parser;

		$defaultArgs = [
			'max' => 5,
			'descriptionlength' => 200,
			'dateformat' => 'Y/m/d H:i',
			'filterin' => null,
			'filterout' => null,
			'sort' => 'newest',
			'itemtemplate' => 'Mediawiki:Rss_item_template',
		];
		$args = array_merge($defaultArgs, $args);

		//Check URL Whitelist
		if (!self::checkURLWhitelist($url)) {
			return self::error('bad_url_whitelist');
		}

		wfDebug(__METHOD__.": Fetching uncached RSS feed - ".$url);

		// Set max amount of time that the generated content can be cached
		self::$parser->getOutput()->updateCacheExpiry($wgRSSPieCacheTime);

		//Fetch the Feed from the URL.
		self::fetchFeed($url);

		$items = self::getItems(
			intval($args['max']),
			intval($args['descriptionlength']),
			$args['dateformat'],
			$args['filterin'],
			$args['filterout']
		);
		if ($args['sort'] == 'oldest') {
			$items = array_reverse($items, true);
		}
		$parsedItems = self::parseItems($items, $args['itemtemplate']);

		return $parsedItems;
	}

	/**
	 * Checks the provided feed URL against the whitelist and validates the URL structure.
	 *
	 * @access	private
	 * @param	string	RSS Feed URL
	 * @return	boolean	Valid URL
	 */
	static private function checkURLWhitelist($url) {
		$whitelistTemplate = self::fetchTemplate('Rss_Whitelist', NS_MEDIAWIKI);
		$whitelistTemplate = str_replace(["\r\n", "\n", "\r"], "\n", $whitelistTemplate);
		$whitelistEntries = explode("\n", $whitelistTemplate);

		$validUrl = false;
		if (is_array($whitelistEntries)) {
			foreach ($whitelistEntries as $filter) {
				if (!empty($filter)) {
					$filter = str_replace('\*', '.*?', preg_quote($filter, '#'));
					if (preg_match("#".$filter."#is", $url)) {
						$validUrl = true;
						break;
					}
				}
			}
		}
		return $validUrl;
	}

	/**
	 * Sets up and fetches the feed.
	 *
	 * @access	private
	 * @param	string	RSS Feed URL
	 * @return	void
	 */
	static private function fetchFeed($url) {
		global $rpCacheDuration;

		self::$rss = new SimplePie();
		self::$rss->set_feed_url($url);
		self::$rss->enable_cache(false);
		self::$rss->enable_order_by_date(true);
		self::$rss->strip_htmltags();
		self::$rss->init();
		self::$rss->handle_content_type();
	}

	/**
	 * Gets a limited amount of items from the feed and assembles them.
	 *
	 * @access	private
	 * @param	integer	Items to Fetch
	 * @param	integer	Number of characters allowed for description length.
	 * @param	string	PHP date function compatible format string.
	 * @param	string	[Optional] Comma delimited list of words to filter in with.(Show only entries with these words.)
	 * @param	string	[Optional] Comma delimited list of words to filter out with.(Remove entries with these words.)
	 * @return	array	Array of Items
	 */
	static private function getItems($limit, $descriptionLength, $dateFormat, $filterIn = null, $filterOut = null) {
		global $wgUser;

		if ($filterIn !== null && !empty($filterIn)) {
			$filterIn = explode(',', trim($filterIn, ','));
		} else {
			$filterIn = [];
		}
		if ($filterOut !== null && !empty($filterOut)) {
			$filterOut = explode(',', trim($filterOut, ','));
		} else {
			$filterOut = [];
		}

		$items = [];
		if (self::$rss && !self::$rss->error()) {
			$loopCount = 0;
			while ($loopCount < 100) {
				$foundItem = false;
				if (count($items) >= $limit) {
					//If the total items added hits the limit then we are done.
					break;
				}

				$item = self::$rss->get_item($loopCount);

				if (empty($item)) {
					//No more items in the feed.
					break;
				}

				$description = trim(strip_tags($item->get_description()));
				if ((is_array($filterIn) && count($filterIn)) || (is_array($filterOut) && count($filterOut))) {
					foreach ($filterIn as $filter) {
						if (stripos($description, $filter) !== false) {
							$foundItem = true;
							break;
						}
					}
					foreach ($filterOut as $filter) {
						if (stripos($description, $filter) !== false) {
							$foundItem = false;
							break;
						}
					}
				} else {
					$foundItem = true;
				}
				if ($foundItem == false) {
					$loopCount++;
					continue;
				}
				$description = trim(mb_substr($description, 0, $descriptionLength)).'...';

				$timeCorrection = $wgUser->getOption('timecorrection');
				if (strpos($timeCorrection, 'Offset|') !== false) {
					$timeCorrection = intval(str_replace('Offset|', '', $timeCorrection) * 60);
				} else {
					$timeCorrection = 0;
				}
				$timestamp = $item->get_date('U') + $timeCorrection;

				if ($foundItem == true) {
					$items[] = [
						'title'			=> "<nowiki>".strip_tags($item->get_title())."</nowiki>",
						'description'	=> "<nowiki>".$description."</nowiki>",
						'date'			=> date($dateFormat, $timestamp),
						'author'		=> "<nowiki>".strip_tags($item->get_author()->name)."</nowiki>",
						'link'			=> $item->get_link()
					];

					$loopCount++;
					continue;
				}
			}
		}
		return $items;
	}

	/**
	 * Parses provided items through templates and generates the feed.
	 *
	 * @access	private
	 * @param	array	Array of Items
	 * @param	string	[Optional] Item(Format) Template
	 * @return	string	Rendered HTML/WikiText
	 */
	static private function parseItems($items, $itemTemplate = 'Mediawiki:Rss_item_template') {
		$itemTemplateTag = wfMessage('rss_item_template_tag', (empty($itemTemplate) ? 'Mediawiki:Rss_item_template' : $itemTemplate))->plain();

		$tags = [];
		foreach ($items as $item) {
			$_tempTag = $itemTemplateTag;
			foreach ($item as $key => $value) {
				$_tempTag = str_replace('{{{'.$key.'}}}', $value, $_tempTag)."\n";
			}
			$tags[] = trim($_tempTag);
		}

		return self::$parser->recursiveTagParse(implode("\n", $tags));
	}

	/**
	 * Fetches the built in item template or a custom specified template.
	 *
	 * @access	private
	 * @return	string	Item Template
	 */
	static private function getItemTemplate() {
		//Custom Built In Feed Template
		$itemTemplate = trim(self::fetchTemplate('Rss-item', NS_MEDIAWIKI));

		//Fall back to language string for the template.
		if (empty($itemTemplate)) {
			$itemTemplate = wfMessage('rss_item_template')->plain();
		}
		return $itemTemplate;
	}

	/**
	 * Fetches a template for the specified title and namespace.
	 *
	 * @access	private
	 * @param	string	Template Title
	 * @param	string	Namespace
	 * @return	string	Template
	 */
	static private function fetchTemplate($templateName, $namespace) {
		$templateTitle		= Title::newFromText($templateName, $namespace);
		$templatePage		= WikiPage::factory($templateTitle);
		$templateContent	= $templatePage->getContent(Revision::RAW);
		$templateText		= ContentHandler::getContentText($templateContent);

		return $templateText;
	}

	/**
	 * Formats error message outputs.
	 *
	 * @access	private
	 * @param	string	Error key to process.
	 * @return	string	Formatted error HTML.
	 */
	static private function error($errorKey) {
		return "<span class='error'>".wfMessage($errorKey)->parse()."</span>";
	}
}
