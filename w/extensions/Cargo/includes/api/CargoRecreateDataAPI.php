<?php
/**
 * Adds and handles the 'cargorecreatedata' action to the MediaWiki API.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

class CargoRecreateDataAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$user = $this->getUser();

		if ( !$user->isAllowed( 'recreatecargodata' ) || $user->isBlocked() ) {
			$this->dieWithError( [ 'badaccess-groups' ] );
		}

		$params = $this->extractRequestParams();
		$templateStr = $params['template'];
		$tableStr = $params['table'];

		if ( $templateStr == '' ) {
			$this->dieWithError( 'The template must be specified', 'param_substr' );
		}

		if ( $tableStr == '' ) {
			$this->dieWithError( 'The table must be specified', 'param_substr' );
		}

		// Create the jobs.
		$jobParams = [
			'dbTableName' => $tableStr,
			'replaceOldRows' => $params['replaceOldRows']
		];
		$jobs = [];
		$templateTitle = Title::makeTitleSafe( NS_TEMPLATE, $templateStr );
		$titlesWithThisTemplate = $templateTitle->getTemplateLinksTo( [
			'LIMIT' => 500, 'OFFSET' => $params['offset'] ] );
		foreach ( $titlesWithThisTemplate as $titleWithThisTemplate ) {
			$jobs[] = new CargoPopulateTableJob( $titleWithThisTemplate, $jobParams );
		}
		JobQueueGroup::singleton()->push( $jobs );

		// Set top-level elements.
		$result = $this->getResult();
		$result->addValue( null, 'success', true );
	}

	protected function getAllowedParams() {
		return [
			'template' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'table' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'offset' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_DFLT => 0,
			],
			'replaceOldRows' => [
				ApiBase::PARAM_TYPE => 'boolean',
			],
		];
	}

	protected function getParamDescription() {
		return [
			'template' => 'The template whose data to use',
			'table' => 'The Cargo database table to repopulate',
			'offset' => 'Of the pages that call this template, the number at which to start querying',
			'replaceOldRows' => 'Whether to replace old rows for each page while repopulating the table',
		];
	}

	protected function getDescription() {
		return 'An API module to recreate data for the Cargo extension '
			. '(https://www.mediawiki.org/Extension:Cargo)';
	}

	protected function getExamples() {
		return [
			'api.php?action=cargorecreatedata&template=City&table=Cities'
		];
	}

}
