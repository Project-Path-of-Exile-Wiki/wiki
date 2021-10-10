<?php

use MediaWiki\MediaWikiServices;

/**
 * @author Yaron Koren
 * @ingroup Cargo
 *
 * Defines the 'slideshow' format, which displays a slideshow of images,
 * using the Slick JS library.
 */

class CargoSlideshowFormat extends CargoDisplayFormat {

	public static function allowedParameters() {
		return [
			'caption field' => [ 'type' => 'string' ],
			'link field' => [ 'type' => 'string' ],
			'slides per screen' => [ 'type' => 'int' ]
		];
	}

	private function getFileTitles( $valuesTable, $fieldDescriptions, $captionField, $linkField ) {
		$fileField = null;
		foreach ( $fieldDescriptions as $field => $fieldDesc ) {
			if ( $fieldDesc->mType == 'File' ) {
				$fileField = $field;
				break;
			}
		}

		// If there's no 'File' field in the schema, just use the
		// page name.
		if ( $fileField == null ) {
			$usingPageName = true;
			$fileField = '_pageName';
		} else {
			$usingPageName = false;
		}

		$fileNames = [];
		foreach ( $valuesTable as $row ) {
			if ( array_key_exists( $fileField, $row ) ) {
				$caption = ( $captionField == null ) ? null : $row[$captionField];
				$link = ( $linkField == null ) ? null : Title::newFromText( $row[$linkField] );
				$fileNames[] = [
					'title' => $row[$fileField],
					'caption' => $caption,
					'link' => $link
				];
			}
		}

		$files = [];
		foreach ( $fileNames as $f ) {
			if ( $usingPageName ) {
				$title = Title::newFromText( $f['title'] );
				if ( $title == null || $title->getNamespace() != NS_FILE ) {
					continue;
				}
			} else {
				$title = Title::makeTitleSafe( NS_FILE, $f['title'] );
				if ( $title == null ) {
					continue;
				}
			}

			$files[] = [
				'title' => $title,
				'caption' => CargoUtils::smartParse( $f['caption'], null ),
				'link' => ( $f['link'] !== null ) ? $f['link']->getLinkURL() : null
			];

		}

		return $files;
	}

	/**
	 * @param array $valuesTable Unused
	 * @param array $formattedValuesTable
	 * @param array $fieldDescriptions
	 * @param array $displayParams Unused
	 * @return string HTML
	 */
	public function display( $valuesTable, $formattedValuesTable, $fieldDescriptions, $displayParams ) {
		$this->mOutput->addModules( 'ext.cargo.slick' );

		if ( array_key_exists( 'caption field', $displayParams ) ) {
			$captionField = str_replace( '_', ' ', $displayParams['caption field'] );
			if ( $captionField[0] == ' ' ) {
				$captionField[0] = '_';
			}
			if ( count( $valuesTable ) > 0 && !array_key_exists( $captionField, $valuesTable[0] ) ) {
				throw new MWException( wfMessage( "cargo-query-specifiedfieldmissing", $captionField, "caption field" )->parse() );
			}
			$this->undisplayedFields[] = $captionField;
		} else {
			$captionField = null;
		}
		if ( array_key_exists( 'link field', $displayParams ) ) {
			$linkField = str_replace( '_', ' ', $displayParams['link field'] );
			if ( $linkField[0] == ' ' ) {
				$linkField[0] = '_';
			}
			if ( count( $valuesTable ) > 0 && !array_key_exists( $linkField, $valuesTable[0] ) ) {
				throw new MWException( wfMessage( "cargo-query-specifiedfieldmissing", $linkField, "link field" )->parse() );
			}
			$this->undisplayedFields[] = $linkField;
		} else {
			$linkField = null;
		}

		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$localRepo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		} else {
			$localRepo = RepoGroup::singleton()->getLocalRepo();
		}
		$bodyText = '';
		$files = self::getFileTitles( $valuesTable, $fieldDescriptions, $captionField, $linkField );
		foreach ( $files as $file ) {
			$fileTitle = $file['title'];
			$actualFile = $localRepo->newFile( $fileTitle->getText() );
			$imageHTML = '<img src="' . $actualFile->getURL() . '" />';
			if ( $file['link'] != '' ) {
				$imageHTML = Html::rawElement( 'a', [ 'href' => $file['link'] ], $imageHTML );
			}
			$slideText = '<div class="image">' . $imageHTML .
				"</div>\n";
			if ( $file['caption'] != '' ) {
				$slideText .= '<span class="cargoSliderCaption">' . $file['caption'] . '</span>';
			}
			$bodyText .= "<div>$slideText</div>\n";
		}

		$sliderAttrs = [ 'class' => 'cargoSlider' ];

		$slickData = [];
		if ( array_key_exists( 'slides per screen', $displayParams ) ) {
			$slickData['slidesToShow'] = $displayParams['slides per screen'];
			// @TODO - add this? Add a separate param for it?
			// $slickData['slidesToScroll'] = $displayParams['slides per screen'];
		}
		if ( array_key_exists( 'autoplay speed', $displayParams ) && $displayParams['autoplay speed'] != '' ) {
			$slickData['autoplay'] = 'true';
			// Cargo's value is in seconds, not milliseconds.
			$slickData['autoplaySpeed'] = 1000 * $displayParams['autoplay speed'];
		}

		$text = '<div class="cargoSlider"';
		if ( count( $slickData ) > 0 ) {
			// Slick requires the inline data to be encoded in a
			// JSON-like way, but it's not quite JSON, and it has
			// to be done in the exact right format, so we just
			// create it manually.
			$slickDataStr = '{';
			$firstVal = true;
			foreach ( $slickData as $key => $val ) {
				if ( !$firstVal ) {
					$slickDataStr .= ', ';
				} else {
					$firstVal = false;
				}
				$slickDataStr .= "\"$key\": $val";
			}
			$slickDataStr .= '}';
			$text .= " data-slick='$slickDataStr'";
		}
		$text .= ">$bodyText</div>";

		return $text;
	}

}
