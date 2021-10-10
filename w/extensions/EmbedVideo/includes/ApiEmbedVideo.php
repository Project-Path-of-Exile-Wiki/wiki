<?php
/**
 * EmbedVideo
 * ApiEmbedVideo class
 *
 * @license MIT
 * @package EmbedVideo
 * @link    https://gitlab.com/hydrawiki/extensions/EmbedVideo
 */

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo;

use ApiBase;

class ApiEmbedVideo extends ApiBase {
	/**
	 * Execute the API call.
	 */
	public function execute(): bool {
		$ev = new EmbedVideo( null, [
			sprintf( 'service=%s', $this->getMain()->getVal( 'service' ) ),
			sprintf( 'id=%s', $this->getMain()->getVal( 'id' ) ),
			sprintf( 'dimensions=%s', $this->getMain()->getVal( 'dimensions' ) ),
			sprintf( 'alignment=%s', $this->getMain()->getVal( 'alignment' ) ),
			sprintf( 'description=%s', $this->getMain()->getVal( 'description' ) ),
			sprintf( 'container=%s', $this->getMain()->getVal( 'container' ) ),
			sprintf( 'urlargs=%s', $this->getMain()->getVal( 'urlargs' ) ),
			sprintf( 'autoresize=%s', $this->getMain()->getVal( 'autoresize' ) ),
			sprintf( 'valignment=%s', $this->getMain()->getVal( 'valignment' ) ),
		] );

		$getHTML = $ev->output();

		if ( is_array( $getHTML ) ) {
			$HTML = $getHTML[0];
		} else {
			$HTML = "Unable to load video from API.";
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'html' => $HTML ] );
		return true;
	}

	/**
	 * Setup the allowed and required parameters
	 *
	 * @return array
	 */
	public function getAllowedParams(): array {
		return array_merge( parent::getAllowedParams(), [
			'service' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'id' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'dimensions' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'alignment' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'description' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'container' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'urlargs' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'autoresize' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
		] );
	}
}
