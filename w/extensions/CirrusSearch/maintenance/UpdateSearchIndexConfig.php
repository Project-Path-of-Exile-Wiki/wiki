<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Connection;

/**
 * Update the search configuration on the search backend.
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

/**
 * Update the elasticsearch configuration for this index.
 */
class UpdateSearchIndexConfig extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Update the configuration or contents of all search indices. This always operates on a single cluster." );
		// Directly require this script so we can include its parameters as maintenance scripts can't use the autoloader
		// in __construct.  Lame.
		require_once __DIR__ . '/UpdateOneSearchIndexConfig.php';
		UpdateOneSearchIndexConfig::addSharedOptions( $this );
		$this->requireExtension( 'CirrusSearch' );
	}

	/**
	 * @return bool|null
	 * @suppress PhanUndeclaredMethod runChild technically returns a
	 *  \Maintenance instance but only \CirrusSearch\Maintenance\Maintenance
	 *  classes have the done method. Just allow it since we know what type of
	 *  maint class is being created
	 */
	public function execute() {
		// Use the default connection, rather than the one specified
		// by --cluster, as we are collecting cluster independent metadata.
		// Also our script specific `all` cluster fails self::getConnection.
		$conn = Connection::getPool( $this->getSearchConfig() );

		foreach ( $this->clustersToWriteTo() as $cluster ) {
			$this->outputIndented( "Updating cluster $cluster...\n" );

			$this->outputIndented( "indexing namespaces...\n" );
			$child = $this->runChild( IndexNamespaces::class );
			$child->mOptions[ 'cluster' ] = $cluster;
			$child->execute();
			$child->done();

			foreach ( $conn->getAllIndexTypes( null ) as $indexType ) {
				$this->outputIndented( "$indexType index...\n" );
				$child = $this->runChild( UpdateOneSearchIndexConfig::class );
				$child->mOptions[ 'cluster' ] = $cluster;
				$child->mOptions[ 'indexType' ] = $indexType;
				$child->execute();
				$child->done();
			}
		}

		return true;
	}

	/**
	 * Convenience method to interperet the 'all' cluster
	 * as a request to run against each of the writable clusters.
	 *
	 * @return string[]
	 */
	protected function clustersToWriteTo() {
		$cluster = $this->getOption( 'cluster', null );
		if ( $cluster === 'all' ) {
			return $this->getSearchConfig()
				->getClusterAssignment()
				->getWritableClusters();
		} else {
			// single specified cluster. May be null, which
			// indirectly selects the default search cluster.
			return [ $cluster ];
		}
	}

}

$maintClass = UpdateSearchIndexConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
