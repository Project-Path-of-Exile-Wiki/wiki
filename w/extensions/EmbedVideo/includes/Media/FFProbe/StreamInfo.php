<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EmbedVideo\Media\FFProbe;

class StreamInfo {
	/**
	 * Stream Info
	 *
	 * @var array
	 */
	private $info;

	/**
	 * Main Constructor
	 *
	 * @access public
	 * @param  array	Stream Info from FFProbe
	 * @return void
	 */
	public function __construct( $info ) {
		$this->info = $info;
	}

	/**
	 * Simple helper instead of repeating an if statement everything.
	 *
	 * @private
	 * @param  string	Field Name
	 * @return mixed
	 */
	private function getField( $field ) {
		return $this->info[$field] ?? false;
	}

	/**
	 * Return the codec type.
	 *
	 * @access public
	 * @return string Codec type or false if unavailable.
	 */
	public function getType() {
		return $this->getField( 'codec_type' );
	}

	/**
	 * Return the codec name.
	 *
	 * @access public
	 * @return string Codec name or false if unavailable.
	 */
	public function getCodecName() {
		return $this->getField( 'codec_name' );
	}

	/**
	 * Return the codec long name.
	 *
	 * @access public
	 * @return string Codec long name or false if unavailable.
	 */
	public function getCodecLongName() {
		return $this->getField( 'codec_long_name' );
	}

	/**
	 * Return the width of the stream.
	 *
	 * @access public
	 * @return int Width or false if unavailable.
	 */
	public function getWidth() {
		return $this->getField( 'width' );
	}

	/**
	 * Return the height of the stream.
	 *
	 * @access public
	 * @return int Height or false if unavailable.
	 */
	public function getHeight() {
		return $this->getField( 'height' );
	}

	/**
	 * Return bit depth for a video or thumbnail.
	 *
	 * @access public
	 * @return int Bit Depth or false if unavailable.
	 */
	public function getBitDepth() {
		return $this->getField( 'bits_per_raw_sample' );
	}

	/**
	 * Get the duration in seconds.
	 *
	 * @access public
	 * @return mixed Duration in seconds or false if unavailable.
	 */
	public function getDuration() {
		return $this->getField( 'duration' );
	}

	/**
	 * Bit rate in bPS.
	 *
	 * @access public
	 * @return mixed Bite rate in bPS or false if unavailable.
	 */
	public function getBitRate() {
		return $this->getField( 'bit_rate' );
	}
}
