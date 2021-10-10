<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2015 various MediaWiki contributors
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Math;

use CurlHttpRequest;
use Exception;
use Hooks;
use Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use PhpHttpRequest;
use Psr\Log\LoggerInterface;
use SpecialPage;
use stdClass;
use Title;
use Xml;
use XmlTypeCheck;

/**
 * Converts LaTeX to MathML using the mathoid-server
 */
class MathMathML extends MathRenderer {

	/** @var string[] */
	protected $defaultAllowedRootElements = [ 'math' ];
	/** @var string[] */
	protected $restbaseInputTypes = [ 'tex', 'inline-tex', 'chem' ];
	/** @var string[] */
	protected $restbaseRenderingModes = [ 'mathml', 'png' ];
	/** @var string[] */
	protected $allowedRootElements = [];
	/** @var string|string[] */
	protected $hosts;

	/** @var LoggerInterface */
	private $logger;

	/** @var bool if false MathML output is not validated */
	private $XMLValidation = true;

	/**
	 * @var string|bool
	 */
	private $svgPath = false;

	/** @var string|false */
	private $pngPath = false;

	/** @var string|null */
	private $mathoidStyle;

	public function __construct( $tex = '', $params = [] ) {
		global $wgMathMathMLUrl;
		parent::__construct( $tex, $params );
		$this->setMode( 'mathml' );
		$this->hosts = $wgMathMathMLUrl;
		if ( isset( $params['type'] ) ) {
			$allowedTypes = [ 'pmml', 'ascii', 'chem' ];
			if ( in_array( $params['type'], $allowedTypes ) ) {
				$this->inputType = $params['type'];
			}
			if ( $params['type'] == 'pmml' ) {
				$this->setMathml( '<math>' . $tex . '</math>' );
			}
		}
		if ( !isset( $params['display'] ) && $this->getMathStyle() == 'inlineDisplaystyle' ) {
			// default preserve the (broken) layout as it was
			$this->tex = '{\\displaystyle ' . $tex . '}';
		}
		$this->logger = LoggerFactory::getInstance( 'Math' );
	}

	/**
	 * @inheritDoc
	 */
	public function addTrackingCategories( $parser ) {
		parent::addTrackingCategories( $parser );
		if ( $this->hasWarnings() ) {
			foreach ( $this->warnings as $warning ) {
				if ( isset( $warning->type ) ) {
					switch ( $warning->type ) {
						case 'mhchem-deprecation':
							$parser->addTrackingCategory( 'math-tracking-category-mhchem-deprecation' );
							break;
						case 'texvc-deprecation':
							$parser->addTrackingCategory( 'math-tracking-category-texvc-deprecation' );
					}
				}
			}
		}
	}

	/**
	 * @param array[] $tags
	 */
	public static function batchEvaluate( array $tags ) {
		$rbis = [];
		foreach ( $tags as $key => $tag ) {
			/** @var MathRenderer $renderer */
			$renderer = $tag[0];
			$rbi = new MathRestbaseInterface( $renderer->getTex(), $renderer->getInputType() );
			$renderer->setRestbaseInterface( $rbi );
			$rbis[] = $rbi;
		}
		MathRestbaseInterface::batchEvaluate( $rbis );
	}

	/**
	 * Gets the allowed root elements the rendered math tag might have.
	 *
	 * @return string[]
	 */
	public function getAllowedRootElements() {
		if ( $this->allowedRootElements ) {
			return $this->allowedRootElements;
		} else {
			return $this->defaultAllowedRootElements;
		}
	}

	/**
	 * Sets the XML validation.
	 * If set to false the output of MathML is not validated.
	 * @param bool $validation
	 */
	public function setXMLValidation( $validation = true ) {
		$this->XMLValidation = $validation;
	}

	/**
	 * Sets the allowed root elements the rendered math tag might have.
	 * An empty value indicates to use the default settings.
	 * @param string[] $settings
	 */
	public function setAllowedRootElements( $settings ) {
		$this->allowedRootElements = $settings;
	}

	/**
	 * @see MathRenderer::render()
	 * @param bool $forceReRendering
	 * @return bool
	 */
	public function render( $forceReRendering = false ) {
		global $wgMathFullRestbaseURL;
		try {
			if ( $forceReRendering ) {
				$this->setPurge( true );
			}
			if ( in_array( $this->inputType, $this->restbaseInputTypes ) &&
				 in_array( $this->mode, $this->restbaseRenderingModes )
			) {
				if ( !$this->rbi ) {
					$this->rbi =
						new MathRestbaseInterface( $this->getTex(), $this->getInputType() );
					$this->rbi->setPurge( $this->isPurge() );
				}
				$rbi = $this->rbi;
				if ( $rbi->getSuccess() ) {
					$this->mathml = $rbi->getMathML();
					$this->mathoidStyle = $rbi->getMathoidStyle();
					$this->svgPath = $rbi->getFullSvgUrl();
					$this->pngPath = $rbi->getFullPngUrl();
					$this->warnings = $rbi->getWarnings();
				} elseif ( $this->lastError === '' ) {
					$this->doCheck();
				}
				$this->changed = false;
				return $rbi->getSuccess();
			}
			if ( $this->renderingRequired() ) {
				return $this->doRender();
			}
			return true;
		} catch ( Exception $e ) {
			$this->lastError = $this->getError( 'math_mathoid_error',
				$wgMathFullRestbaseURL, $e->getMessage() );
			$this->logger->error( $e->getMessage(), [ $e, $this ] );
			return false;
		}
	}

	/**
	 * Helper function to checks if the math tag must be rendered.
	 * @return bool
	 */
	private function renderingRequired() {
		if ( $this->isPurge() ) {
			$this->logger->debug( 'Rerendering was requested.' );
			return true;
		}

		$dbres = $this->isInDatabase();
		if ( $dbres ) {
			if ( $this->isValidMathML( $this->getMathml() ) ) {
				$this->logger->debug( 'Valid MathML entry found in database.' );
				if ( $this->getSvg( 'cached' ) ) {
					$this->logger->debug( 'SVG-fallback found in database.' );
					return false;
				} else {
					$this->logger->debug( 'SVG-fallback missing.' );
					return true;
				}
			} else {
				$this->logger->debug( 'Malformatted entry found in database' );
				return true;
			}
		} else {
			$this->logger->debug( 'No entry found in database.' );
			return true;
		}
	}

	/**
	 * Performs a HTTP Post request to the given host.
	 * Uses $wgMathLaTeXMLTimeout as timeout.
	 * Generates error messages on failure
	 * @see Http::post()
	 *
	 * @param string $host
	 * @param string $post the encoded post request
	 * @param mixed &$res the result
	 * @param mixed &$error the formatted error message or null
	 * @param string $httpRequestClass class name of MWHttpRequest (needed for testing only)
	 * @return bool success
	 */
	public function makeRequest(
			$host, $post, &$res, &$error = '', $httpRequestClass = 'MWHttpRequest'
		) {
		// TODO: Change the timeout mechanism.
		global $wgMathLaTeXMLTimeout;

		$error = '';
		$res = null;
		if ( !$host ) {
			$host = $this->pickHost();
		}
		if ( !$post ) {
			$this->getPostData();
		}
		$options = [ 'method' => 'POST', 'postData' => $post, 'timeout' => $wgMathLaTeXMLTimeout ];
		/** @var CurlHttpRequest|PhpHttpRequest $req the request object */
		$req = $httpRequestClass::factory( $host, $options );
		$status = $req->execute();
		if ( $status->isGood() ) {
			$res = $req->getContent();
			return true;
		} else {
			if ( $status->hasMessage( 'http-timed-out' ) ) {
				$error = $this->getError( 'math_timeout', $this->getModeStr(), $host );
				$res = false;
				$this->logger->warning( 'Timeout:' . var_export( [
						'post' => $post,
						'host' => $host,
						'timeout' => $wgMathLaTeXMLTimeout
					], true ) );
			} else {
				// for any other unkonwn http error
				$errormsg = $status->getHtml();
				$error =
					$this->getError( 'math_invalidresponse', $this->getModeStr(), $host, $errormsg,
						$this->getModeStr() );
				$this->logger->warning( 'NoResponse:' . var_export( [
						'post' => $post,
						'host' => $host,
						'errormsg' => $errormsg
					], true ) );
			}
			return false;
		}
	}

	/**
	 * Return a MathML daemon host.
	 *
	 * If more than one demon is available, one is chosen at random.
	 *
	 * @return string
	 * @deprecated
	 */
	protected function pickHost() {
		if ( is_array( $this->hosts ) ) {
			$host = $this->hosts[array_rand( $this->hosts )];
			$this->hosts = $host; // Use the same host for this class instance
		} else {
			$host = $this->hosts;
		}
		$this->logger->debug( 'Picking host ' . $host );
		return $host;
	}

	/**
	 * Calculates the HTTP POST Data for the request. Depends on the settings
	 * and the input string only.
	 * @return string HTTP POST data
	 */
	public function getPostData() {
		$input = $this->getTex();
		if ( $this->inputType == 'pmml' ||
			 $this->getMode() == 'latexml' && $this->getMathml() ) {
			$out = 'type=mml&q=' . rawurlencode( $this->getMathml() );
		} elseif ( $this->inputType == 'ascii' ) {
			$out = 'type=asciimath&q=' . rawurlencode( $input );
		} else {
			if ( $this->getMathStyle() === 'inlineDisplaystyle' ) {
				// default preserve the (broken) layout as it was
				$out = 'type=inline-TeX&q=' . rawurlencode( '{\\displaystyle ' . $input . '}' );
			} elseif ( $this->getMathStyle() === 'inline' ) {
				$out = 'type=inline-TeX&q=' . rawurlencode( $input );
			} else {
				$out = 'type=tex&q=' . rawurlencode( $input );
			}
		}
		$this->logger->debug( 'Get post data: ' . $out );
		return $out;
	}

	/**
	 * Does the actual web request to convert TeX to MathML.
	 * @return bool
	 */
	protected function doRender() {
		if ( $this->isEmpty() ) {
			return false;
		}
		$res = '';
		$host = $this->pickHost();
		$post = $this->getPostData();
		$this->lastError = '';
		$requestResult = $this->makeRequest( $host, $post, $res, $this->lastError );
		if ( $requestResult ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
			$jsonResult = json_decode( $res );
			if ( $jsonResult && json_last_error() === JSON_ERROR_NONE ) {
				if ( $jsonResult->success ) {
					return $this->processJsonResult( $jsonResult, $host );
				} else {
					if ( property_exists( $jsonResult, 'log' ) ) {
						$log = $jsonResult->log;
					} else {
						$log = wfMessage( 'math_unknown_error' )->inContentLanguage()->escaped();
					}
					$this->lastError = $this->getError( 'math_mathoid_error', $host, $log );
					$this->logger->warning(
						'Mathoid conversion error:' . var_export( [
							'post' => $post,
							'host' => $host,
							'result' => $res
						], true ) );
					return false;
				}
			} else {
				$this->lastError = $this->getError( 'math_invalidjson', $host );
				$this->logger->error(
					'MathML InvalidJSON:' . var_export( [
						'post' => $post,
						'host' => $host,
						'res' => $res
					], true ) );
				return false;
			}
		} else {
			// Error message has already been set.
			return false;
		}
	}

	/**
	 * Checks if the input is valid MathML,
	 * and if the root element has the name math
	 * @param string $XML
	 * @return bool
	 */
	public function isValidMathML( $XML ) {
		$out = false;
		if ( !$this->XMLValidation ) {
			return true;
		}

		$xmlObject = new XmlTypeCheck( $XML, null, false );
		if ( !$xmlObject->wellFormed ) {
			$this->logger->error(
				'XML validation error: ' . var_export( $XML, true ) );
		} else {
			$name = $xmlObject->getRootElement();
			$elementSplit = explode( ':', $name );
			$localName = end( $elementSplit );
			if ( in_array( $localName, $this->getAllowedRootElements() ) ) {
				$out = true;
			} else {
				$this->logger->error( "Got wrong root element: $name" );
			}
		}
		return $out;
	}

	/**
	 * @param bool $noRender
	 * @return Title|string
	 */
	private function getFallbackImageUrl( $noRender = false ) {
		if ( $this->getMode() === 'png' && $this->pngPath ) {
			return $this->pngPath;
		}
		if ( $this->svgPath ) {
			return $this->svgPath;
		}
		return SpecialPage::getTitleFor( 'MathShowImage' )->getLocalURL( [
				'hash' => $this->getMd5(),
				'mode' => $this->getMode(),
				'noRender' => $noRender
			]
		);
	}

	/**
	 * Helper function to correct the style information for a
	 * linked SVG image.
	 * @param string &$style current style information to be updated
	 */
	public function correctSvgStyle( &$style ) {
		if ( preg_match( '/style="([^"]*)"/', $this->getSvg(), $styles ) ) {
			$style .= ' ' . $styles[1]; // merge styles
			if ( $this->getMathStyle() === 'display' ) {
				// TODO: Improve style cleaning
				$style = preg_replace(
					'/margin\-(left|right)\:\s*\d+(\%|in|cm|mm|em|ex|pt|pc|px)\;/', '', $style
				);
			}
			$style = trim( preg_replace( '/position:\s*absolute;\s*left:\s*0px;/', '', $style ),
				"; \t\n\r\0\x0B" ) . '; ';

		}
		// TODO: Figure out if there is a way to construct
		// a SVGReader from a string that represents the SVG
		// content
		if ( preg_match( "/height=\"(.*?)\"/", $this->getSvg(), $matches ) ) {
			$style .= 'height: ' . $matches[1] . '; ';
		}
		if ( preg_match( "/width=\"(.*?)\"/", $this->getSvg(), $matches ) ) {
			$style .= 'width: ' . $matches[1] . ';';
		}
	}

	/**
	 * Gets img tag for math image
	 * @param bool $noRender if true no rendering will be performed
	 * if the image is not stored in the database
	 * @param false|string $classOverride if classOverride
	 * is false the class name will be calculated by getClassName
	 * @return string XML the image html tag
	 */
	protected function getFallbackImage( $noRender = false, $classOverride = false ) {
		$attribs = [
			'src' => $this->getFallbackImageUrl( $noRender )
		];
		if ( $classOverride === false ) { // $class = '' suppresses class attribute
			$class = $this->getClassName( true );
		} else {
			$class = $classOverride;
		}
		if ( !$this->mathoidStyle ) {
			$this->correctSvgStyle( $this->mathoidStyle );
		}
		if ( $class ) {
			$attribs['class'] = $class;
		}

		return Html::element( 'img', $this->getAttributes( 'span', $attribs, [
			'aria-hidden' => 'true',
			'style' => $this->mathoidStyle,
			'alt' => $this->tex
		] ) );
	}

	/**
	 * @return string
	 */
	protected function getMathTableName() {
		return 'mathoid';
	}

	/**
	 * Calculates the default class name for a math element
	 * @param bool $fallback
	 * @return string the class name
	 */
	private function getClassName( $fallback = false ) {
		$class = 'mwe-math-';
		if ( $fallback ) {
			$class .= 'fallback-image-';
		} else {
			$class .= 'mathml-';
		}
		if ( $this->getMathStyle() == 'display' ) {
			$class .= 'display';
		} else {
			$class .= 'inline';
		}
		if ( !$fallback ) {
			$class .= ' mwe-math-mathml-a11y';
		}
		return $class;
	}

	/**
	 * @return string Html output that is embedded in the page
	 */
	public function getHtmlOutput() {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$enableLinks = $config->get( "MathEnableFormulaLinks" );
		if ( $this->getMathStyle() == 'display' ) {
			$element = 'div';
		} else {
			$element = 'span';
		}
		$attribs = [ 'class' => 'mwe-math-element' ];
		if ( $this->getID() !== '' ) {
			$attribs['id'] = $this->getID();
		}
		$hyperlink = null;
		if ( isset( $this->params['qid'] ) && preg_match( '/Q\d+/', $this->params['qid'] ) ) {
			$attribs['data-qid'] = $this->params['qid'];
			// TODO: SpecialPage::getTitleFor uses the depcrated method Title::newFromTitleValue and
			// declares a never thrown MWException. Once this SpecialPage is updated, update the next line.
			$titleObj = Title::newFromLinkTarget( SpecialPage::getTitleValueFor( 'MathWikibase' ) );
			$hyperlink = $titleObj->getLocalURL( [ 'qid' => $this->params['qid'] ] );
		}
		$output = Html::openElement( $element, $attribs );
		if ( $hyperlink && $enableLinks ) {
			$output .= Html::openElement( 'a', [ 'href' => $hyperlink, 'style' => 'color:inherit;' ] );
		}
		// MathML has to be wrapped into a div or span in order to be able to hide it.
		// Remove displayStyle attributes set by the MathML converter
		// (Beginning from Mathoid 0.2.5 block is the default layout.)
		$mml = preg_replace(
			'/(<math[^>]*)(display|mode)=["\'](inline|block)["\']/', '$1', $this->getMathml()
		);
		if ( $this->getMathStyle() == 'display' ) {
			$mml = preg_replace( '/<math/', '<math display="block"', $mml );
		}
		$output .= Xml::tags( $element, [
			'class' => $this->getClassName(), 'style' => 'display: none;'
		], $mml );
		$output .= $this->getFallbackImage();
		if ( $hyperlink && $enableLinks ) {
			$output .= Html::closeElement( 'a' );
		}
		$output .= Html::closeElement( $element );
		return $output;
	}

	protected function dbOutArray() {
		$out = parent::dbOutArray();
		if ( $this->getMathTableName() == 'mathoid' ) {
			$out['math_input'] = $out['math_inputtex'];
			unset( $out['math_inputtex'] );
			$out['math_png'] = $this->png;
		}
		return $out;
	}

	protected function dbInArray() {
		$out = parent::dbInArray();
		if ( $this->getMathTableName() == 'mathoid' ) {
			$out = array_diff( $out, [ 'math_inputtex' ] );
			$out[] = 'math_input';
		}
		return $out;
	}

	protected function initializeFromDatabaseRow( $rpage ) {
		// mathoid allows different input formats
		// therefore the column name math_inputtex was changed to math_input
		if ( $this->getMathTableName() == 'mathoid' && !empty( $rpage->math_input ) ) {
			$this->userInputTex = $rpage->math_input;
		}
		parent::initializeFromDatabaseRow( $rpage );
	}

	/**
	 * @param stdClass $jsonResult json result
	 * @param string $host name
	 *
	 * @return bool
	 */
	protected function processJsonResult( $jsonResult, $host ) {
		if ( $this->getMode() == 'latexml' || $this->inputType == 'pmml' ||
			 $this->isValidMathML( $jsonResult->mml )
		) {
			if ( isset( $jsonResult->svg ) ) {
				$xmlObject = new XmlTypeCheck( $jsonResult->svg, null, false );
				if ( !$xmlObject->wellFormed ) {
					$this->lastError = $this->getError( 'math_invalidxml', $host );
					return false;
				} else {
					$this->setSvg( $jsonResult->svg );
				}
			} else {
				$this->logger->error(
					'Missing SVG property in JSON result.' );
			}
			if ( $this->getMode() != 'latexml' && $this->inputType != 'pmml' ) {
				$this->setMathml( $jsonResult->mml );
			}
			// Avoid PHP 7.1 warning from passing $this by reference
			$renderer = $this;
			Hooks::run( 'MathRenderingResultRetrieved',
				[ &$renderer, &$jsonResult ]
			); // Enables debugging of server results
			return true;
		} else {
			$this->lastError = $this->getError( 'math_unknown_error', $host );
			return false;
		}
	}

	/**
	 * @return bool
	 */
	protected function isEmpty() {
		if ( $this->userInputTex === '' ) {
			$this->logger->debug( 'Rendering was requested, but no TeX string is specified.' );
			$this->lastError = $this->getError( 'math_empty_tex' );
			return true;
		}
		return false;
	}
}

class_alias( MathMathML::class, 'MathMathML' );
