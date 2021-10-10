<?php

/**
 * Static functions for dealing with the "_fileData" table.
 *
 * @author Yaron Koren
 */
class CargoFileData {

	/**
	 * Set the schema based on what has been entered in LocalSettings.php.
	 *
	 * @return CargoTableSchema
	 */
	public static function getTableSchema() {
		global $wgCargoFileDataColumns;

		$fieldTypes = [];

		if ( in_array( 'mediaType', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_mediaType'] = [ 'type' => 'String' ];
		}
		if ( in_array( 'path', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_path'] = [ 'type' => 'String', 'hidden' => true ];
		}
		if ( in_array( 'lastUploadDate', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_lastUploadDate'] = [ 'type' => 'Datetime' ];
		}
		if ( in_array( 'fullText', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_fullText'] = [ 'type' => 'Searchtext' ];
		}
		if ( in_array( 'numPages', $wgCargoFileDataColumns ) ) {
			$fieldTypes['_numPages'] = [ 'type' => 'Integer' ];
		}

		$tableSchema = new CargoTableSchema();
		foreach ( $fieldTypes as $field => $fieldVals ) {
			$fieldDesc = new CargoFieldDescription();
			foreach ( $fieldVals as $fieldKey => $fieldVal ) {
				if ( $fieldKey == 'type' ) {
					$fieldDesc->mType = $fieldVal;
				} elseif ( $fieldKey == 'list' ) {
					// Not currently used.
					$fieldDesc->mIsList = true;
					$fieldDesc->setDelimiter( '|' );
				} elseif ( $fieldKey == 'hidden' ) {
					$fieldDesc->mIsHidden = true;
				}
			}
			$tableSchema->mFieldDescriptions[$field] = $fieldDesc;
		}

		return $tableSchema;
	}

	/**
	 * @param Title|null $title
	 * @param bool $createReplacement
	 */
	public static function storeValuesForFile( $title, $createReplacement ) {
		global $wgCargoFileDataColumns, $wgLocalFileRepo;

		if ( $title == null ) {
			return;
		}

		// Exit if we're not in the File namespace.
		if ( $title->getNamespace() != NS_FILE ) {
			return;
		}

		$fileDataTable = $createReplacement ? '_fileData__NEXT' : '_fileData';

		// If there is no _fileData table, getTableSchemas() will
		// throw an error.
		try {
			$tableSchemas = CargoUtils::getTableSchemas( [ $fileDataTable ] );
		} catch ( MWException $e ) {
			return;
		}

		$repo = new LocalRepo( $wgLocalFileRepo );
		$file = LocalFile::newFromTitle( $title, $repo );

		$fileDataValues = [];

		if ( in_array( 'mediaType', $wgCargoFileDataColumns ) ) {
			$fileDataValues['_mediaType'] = $file->getMimeType();
		}

		if ( in_array( 'path', $wgCargoFileDataColumns ) ) {
			$fileDataValues['_path'] = $file->getLocalRefPath();
		}

		if ( in_array( 'lastUploadDate', $wgCargoFileDataColumns ) ) {
			$fileDataValues['_lastUploadDate'] = $file->getTimestamp();
		}

		if ( in_array( 'fullText', $wgCargoFileDataColumns ) ) {
			global $wgCargoPDFToText;

			if ( $wgCargoPDFToText == '' ) {
				// Display an error message?
			} elseif ( $file->getMimeType() != 'application/pdf' ) {
				// We only handle PDF files.
			} else {
				// Copied in part from the PdfHandler extension.
				$filePath = $file->getLocalRefPath();
				$cmd = wfEscapeShellArg( $wgCargoPDFToText ) . ' ' . wfEscapeShellArg( $filePath ) . ' - ';
				$retval = '';
				$txt = wfShellExec( $cmd, $retval );
				if ( $retval == 0 ) {
					$txt = str_replace( "\r\n", "\n", $txt );
					$txt = str_replace( "\f", "\n\n", $txt );
					$fileDataValues['_fullText'] = $txt;
				}
			}
		}

		if ( in_array( 'numPages', $wgCargoFileDataColumns ) ) {
			global $wgCargoPDFInfo;
			if ( $wgCargoPDFInfo == '' ) {
				// Display an error message?
			} elseif ( $file->getMimeType() != 'application/pdf' ) {
				// We only handle PDF files.
			} else {
				$filePath = $file->getLocalRefPath();
				$cmd = wfEscapeShellArg( $wgCargoPDFInfo ) . ' ' . wfEscapeShellArg( $filePath );
				$retval = '';
				$txt = wfShellExec( $cmd, $retval );
				if ( $retval == 0 ) {
					$lines = explode( PHP_EOL, $txt );
					$matched = preg_grep( '/^Pages\:/', $lines );
					foreach ( $matched as $line ) {
						$fileDataValues['_numPages'] = intval( trim( substr( $line, 7 ) ) );
					}
				}
			}
		}

		CargoStore::storeAllData( $title, $fileDataTable, $fileDataValues, $tableSchemas[$fileDataTable] );
	}

}
