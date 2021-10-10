<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MediaWiki\Minerva\Menu\Entries;

use MinervaUI;

/**
 * Class for defining a home menu entry in Special:MobileMenu
 */
final class HomeMenuEntry implements IMenuEntry {

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var array
	 */
	private $component;

	/**
	 * Override the text used in the home menu entry.
	 *
	 * @param string $text
	 * @return $this
	 */
	public function overrideText( $text ) {
		$this->component['text'] = $text;
		return $this;
	}

	/**
	 * Override the CSS class used in the home menu entry.
	 *
	 * @param string $cssClass
	 * @return $this
	 */
	public function overrideCssClass( $cssClass ) {
		$this->component['class'] = $cssClass;
		return $this;
	}

	/**
	 * Create a home menu element with one component
	 *
	 * @param string $name An unique menu element identifier
	 * @param string $text Text to show on menu element
	 * @param string $url URL menu element points to
	 * @param bool|string $trackClicks Should clicks be tracked. To override the tracking code
	 * pass the tracking code as string
	 */
	public function __construct( $name, $text, $url, $trackClicks = true ) {
		$this->name = $name;
		$this->component = [
			'text' => $text,
			'href' => $url,
			'class' => trim( MinervaUI::iconClass( $name, 'before' ) )
		];
		if ( $trackClicks !== false ) {
			$eventName = $trackClicks === true ? $name : $trackClicks;
			$this->component['data-event-name'] = 'menu.' . $eventName;

		}
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @inheritDoc
	 */
	public function getCSSClasses(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getComponents(): array {
		return [ $this->component ];
	}

}
