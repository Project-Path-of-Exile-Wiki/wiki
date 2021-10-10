<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusSearch;
use MediaWiki\Page\ProperPageIdentity;
use Title;

/**
 * Update the weighted_tags field for a page for a specific tag.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . '/../includes/Maintenance/Maintenance.php';

class UpdateWeightedTags extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Update the weighted_tags field for a page for a specific tag." );
		$this->addOption( 'page', 'Page title', true, true );
		$this->addOption( 'tagType', "Tag type. A string such as 'recommendation.link'.", true, true );
		$this->addOption( 'tagName', "Tag name. Some tag types don't use this.", false, true, false, true );
		$this->addOption( 'weight', "Weight (0-1000). Some tag types don't use this. When used, must occur the same number of'
			. ' times as --tagName and will be matched by position.", false, true, false, true );
		$this->addOption( 'reset', 'Reset a tag type (remove all tags belonging to it). Cannot be mixed with --tagName and --weight.' );
		$this->requireExtension( 'CirrusSearch' );
	}

	public function execute() {
		$this->validateParams();
		$page = $this->getPage();
		$tagPrefix = $this->getOption( 'tagType' );
		$cirrusSearch = new CirrusSearch();
		if ( $this->hasOption( 'reset' ) ) {
			$cirrusSearch->resetWeightedTags( $page, $tagPrefix );
		} else {
			$tagNames = $this->getOption( 'tagName' );
			$tagWeights = $this->getOption( 'weight' );
			if ( $tagWeights !== null ) {
				$tagWeights = array_map( 'intval', $tagWeights );
				$tagWeights = array_combine( $tagNames, $tagWeights );
			}
			$cirrusSearch->updateWeightedTags( $page, $tagPrefix, $tagNames, $tagWeights );
		}
	}

	private function validateParams() {
		if ( strpos( $this->getOption( 'tagType' ), '/' ) !== false ) {
			$this->fatalError( 'The tag type cannot contain a / character' );
		}

		if ( $this->hasOption( 'reset' ) ) {
			if ( $this->hasOption( 'tagName' ) || $this->hasOption( 'weight' ) ) {
				$this->fatalError( '--reset cannot be used with --tagName or --weight' );
			}
		} else {
			$tagNames = $this->getOption( 'tagName' );
			$tagWeights = $this->getOption( 'weight' );

			if ( $tagNames === null ) {
				if ( $tagWeights !== null ) {
					$this->fatalError( '--weight should be used together with --tagName' );
				}
			} else {
				if ( $tagWeights && count( $tagNames ) !== count( $tagWeights ) ) {
					$this->fatalError( 'When --weight is used, it must occur the same number of times as --tagName' );
				}
				foreach ( $tagNames as $tagName ) {
					if ( strpos( $tagName, '|' ) !== false ) {
						$this->fatalError( "Wrong tag name '$tagName': cannot contain | character" );
					}
				}
				foreach ( $tagWeights ?? [] as $tagWeight ) {
					if ( !ctype_digit( $tagWeight ) || ( $tagWeight < 1 ) || ( $tagWeight > 1000 ) ) {
						$this->fatalError( "Wrong tag weight '$tagWeight': must be an integer between 1 and 1000" );
					}
				}
			}
		}
	}

	/**
	 * @return ProperPageIdentity
	 */
	private function getPage() {
		$pageName = $this->getOption( 'page' );
		$title = Title::newFromText( $pageName );
		if ( !$title ) {
			$this->fatalError( "Invalid title $pageName" );
		} elseif ( !$title->canExist() ) {
			$this->fatalError( "$pageName is not a proper page" );
		} elseif ( !$title->exists() ) {
			$this->fatalError( "$pageName does not exist" );
		}
		if ( $title->hasFragment() ) {
			$title->setFragment( '' );
		}
		return $title->toPageIdentity();
	}
}

$maintClass = UpdateWeightedTags::class;
require_once RUN_MAINTENANCE_IF_MAIN;
