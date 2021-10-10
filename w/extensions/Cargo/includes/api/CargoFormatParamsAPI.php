<?php
/**
 * Adds the 'cargoformatparams' action to the MediaWiki API.
 *
 * @ingroup Cargo
 *
 */
class CargoFormatParamsAPI extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$queryFormat = $params['queryformat'];
		$formatClasses = CargoQueryDisplayer::getAllFormatClasses();
		if ( !array_key_exists( $queryFormat, $formatClasses ) ) {
			$this->dieWithError( "Format \"$queryFormat\" not found." );
		}
		$formatClass = $formatClasses[$queryFormat];
		$data = $formatClass::allowedParameters();

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $data, 'p' );
		$result->addValue( null, $this->getModuleName(), $data );
	}

	protected function getAllowedParams() {
		return [
			'queryformat' => null
		];
	}

	protected function getExamples() {
		return [
			'api.php?action=cargoformatparams&format=json&queryformat=calendar'
		];
	}

}
