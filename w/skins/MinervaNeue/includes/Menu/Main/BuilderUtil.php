<?php
/**
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
 *
 * @file
 */

namespace MediaWiki\Minerva\Menu\Main;

use FatalError;
use Hooks;
use MediaWiki\Minerva\Menu\Definitions;
use MediaWiki\Minerva\Menu\Group;
use MWException;

/**
 * Group generators shared between menu builders.
 *
 * @package MediaWiki\Minerva\Menu\Main
 */
final class BuilderUtil {
	/**
	 * Prepares donate group if available
	 * @param Definitions $definitions A menu items definitions set
	 * @return Group|null if not available
	 * @throws FatalError
	 * @throws MWException
	 */
	public static function getDonateGroup( Definitions $definitions ) {
		$group = new Group( 'p-donation' );
		$definitions->insertDonateItem( $group );
		return $group->hasEntries() ? $group : null;
	}

	/**
	 * Prepares a list of links that have the purpose of discovery in the main navigation menu
	 * @param Definitions $definitions A menu items definitions set
	 * @return Group
	 * @throws FatalError
	 * @throws MWException
	 */
	public static function getDiscoveryTools( Definitions $definitions ): Group {
		$group = new Group( 'p-navigation' );

		$definitions->insertHomeItem( $group );
		$definitions->insertRandomItem( $group );
		$definitions->insertNearbyIfSupported( $group );

		// Allow other extensions to add or override tools
		Hooks::run( 'MobileMenu', [ 'discovery', &$group ] );
		return $group;
	}

	/**
	 * Like <code>SkinMinerva#getDiscoveryTools</code> and <code>#getPersonalTools</code>, create
	 * a group of configuration-related menu items. Currently, only the Settings menu item is in the
	 * group.
	 * @param Definitions $definitions A menu items definitions set
	 * @param bool $showMobileOptions Show MobileOptions instead of Preferences
	 * @return Group
	 * @throws MWException
	 */
	public static function getConfigurationTools(
		Definitions $definitions, $showMobileOptions
	): Group {
		$group = new Group( 'pt-preferences' );

		$showMobileOptions ?
			$definitions->insertMobileOptionsItem( $group ) :
			$definitions->insertPreferencesItem( $group );

		return $group;
	}

	/**
	 * Returns an array of sitelinks to add into the main menu footer.
	 * @param Definitions $definitions A menu items definitions set
	 * @return Group Collection of site links
	 * @throws MWException
	 */
	public static function getSiteLinks( Definitions $definitions ): Group {
		$group = new Group( 'p-minerva-sitelinks' );

		$definitions->insertAboutItem( $group );
		$definitions->insertDisclaimersItem( $group );
		// Allow other extensions to add or override tools
		Hooks::run( 'MobileMenu', [ 'sitelinks', &$group ] );
		return $group;
	}
}
