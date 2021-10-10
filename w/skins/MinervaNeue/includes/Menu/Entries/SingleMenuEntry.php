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

use Message;
use MinervaUI;

/**
 * Model for a simple menu entries with label and icon
 */
class SingleMenuEntry implements IMenuEntry {
	/**
	 * @var string
	 */
	private $name;
	/**
	 * @var array
	 */
	private $attributes;
	/**
	 * @var bool
	 */
	private $isJSOnly;

	/**
	 * Create a simple menu element with one component
	 *
	 * @param string $name An unique menu element identifier
	 * @param string $text Text to show on menu element
	 * @param string $url URL menu element points to
	 * @param string $className Additional CSS class names.
	 */
	public function __construct( $name, $text, $url, $className = '' ) {
		$this->name = $name;
		if ( $className ) {
			$className .= ' ';
		}
		$className .= 'menu__item--' . $name;

		$this->attributes = [
			'text' => $text,
			'href' => $url,
			'class' => $className
		];
	}

	/**
	 * Create a Single Menu entry with text, icon and active click tracking
	 *
	 * @param string $name Entry identifier
	 * @param string $text Entry label
	 * @param string $url The URL entry points to
	 * @param string $className Optional HTML classes
	 * @return static
	 */
	public static function create( $name, $text, $url, $className = '' ) {
		$entry = new static( $name, $text, $url, $className );
		$entry->trackClicks( $name );
		$entry->setIcon( $name );
		return $entry;
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
		return $this->isJSOnly ? [ 'jsonly' ] : [];
	}

	/**
	 * @inheritDoc
	 */
	public function getComponents(): array {
		return [ $this->attributes ];
	}

	/**
	 * @param string $eventName Should clicks be tracked. To override the tracking code
	 * pass the tracking code as string
	 * @return $this
	 */
	public function trackClicks( $eventName ) {
		$this->attributes['data-event-name'] = 'menu.' . $eventName;
		return $this;
	}

	/**
	 * Set the Menu entry icon
	 * @param string|null $iconName Icon name
	 * @param string $iconType Icon type
	 * @param string $additionalClassNames Additional classes
	 * @param string $iconPrefix either `wikimedia` or `minerva`
	 * @return $this
	 */
	public function setIcon( $iconName, $iconType = 'before',
		$additionalClassNames = '', $iconPrefix = 'minerva'
	) {
		$this->attributes['class'] .= ' '
			. MinervaUI::iconClass( $iconName, $iconType, $additionalClassNames, $iconPrefix );
		return $this;
	}

	/**
	 * Set the menu entry title
	 * @param Message $message Title message
	 * @return $this
	 */
	public function setTitle( Message $message ): self {
		$this->attributes['title'] = $message->escaped();
		return $this;
	}

	/**
	 * Set the Menu entry ID html attribute
	 * @param string $nodeID
	 * @return $this
	 */
	public function setNodeID( $nodeID ): self {
		$this->attributes['id'] = $nodeID;
		return $this;
	}

	/**
	 * Mark entry as JS only.
	 */
	public function setJSOnly() {
		$this->isJSOnly = true;
	}

}
