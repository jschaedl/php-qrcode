<?php
/**
 * Class QRImagick
 *
 * @created      04.07.2018
 * @author       smiley <smiley@chillerlan.net>
 * @copyright    2018 smiley
 * @license      MIT
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */

namespace chillerlan\QRCode\Output;

use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\Settings\SettingsContainerInterface;
use finfo, Imagick, ImagickDraw, ImagickPixel;
use function extension_loaded, is_string;
use const FILEINFO_MIME_TYPE;

/**
 * ImageMagick output module (requires ext-imagick)
 *
 * @see http://php.net/manual/book.imagick.php
 * @see http://phpimagick.com
 */
class QRImagick extends QROutputAbstract{

	/**
	 * The main image instance
	 */
	protected Imagick $imagick;

	/**
	 * The main draw instance
	 */
	protected ImagickDraw $imagickDraw;

	/**
	 * The allocated background color
	 */
	protected ImagickPixel $background;

	/**
	 * @inheritDoc
	 *
	 * @throws \chillerlan\QRCode\Output\QRCodeOutputException
	 */
	public function __construct(SettingsContainerInterface $options, QRMatrix $matrix){

		if(!extension_loaded('imagick')){
			throw new QRCodeOutputException('ext-imagick not loaded'); // @codeCoverageIgnore
		}

		if(!extension_loaded('fileinfo')){
			throw new QRCodeOutputException('ext-fileinfo not loaded'); // @codeCoverageIgnore
		}

		parent::__construct($options, $matrix);
	}

	/**
	 * @todo: check/validate possible values
	 * @see https://www.php.net/manual/imagickpixel.construct.php
	 * @inheritDoc
	 */
	public static function moduleValueIsValid($value):bool{
		return is_string($value);
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleValue($value):ImagickPixel{
		return new ImagickPixel($value);
	}

	/**
	 * @inheritDoc
	 */
	protected function getDefaultModuleValue(bool $isDark):ImagickPixel{
		return $this->getModuleValue(($isDark) ? $this->options->markupDark : $this->options->markupLight);
	}

	/**
	 * @inheritDoc
	 *
	 * @return string|\Imagick
	 */
	public function dump(string $file = null){
		$this->imagick = new Imagick;

		$this->setBgColor();

		$this->imagick->newImage($this->length, $this->length, $this->background, $this->options->imagickFormat);

		$this->drawImage();
		// set transparency color after all operations
		$this->setTransparencyColor();

		if($this->options->returnResource){
			return $this->imagick;
		}

		$imageData = $this->imagick->getImageBlob();

		$this->imagick->destroy();

		$this->saveToFile($imageData, $file);

		if($this->options->imageBase64){
			$imageData = $this->toBase64DataURI($imageData, (new finfo(FILEINFO_MIME_TYPE))->buffer($imageData));
		}

		return $imageData;
	}

	/**
	 * Sets the background color
	 */
	protected function setBgColor():void{

		if(isset($this->background)){
			return;
		}

		if($this::moduleValueIsValid($this->options->bgColor)){
			$this->background = $this->getModuleValue($this->options->bgColor);

			return;
		}

		$this->background = $this->getModuleValue('white');
	}

	/**
	 * Sets the transparency color
	 */
	protected function setTransparencyColor():void{

		if(!$this->options->imageTransparent){
			return;
		}

		$transparencyColor = $this->background;

		if($this::moduleValueIsValid($this->options->transparencyColor)){
			$transparencyColor = $this->getModuleValue($this->options->transparencyColor);
		}

		$this->imagick->transparentPaintImage($transparencyColor, 0.0, 10, false);
	}

	/**
	 * Creates the QR image via ImagickDraw
	 */
	protected function drawImage():void{
		$this->imagickDraw = new ImagickDraw;
		$this->imagickDraw->setStrokeWidth(0);

		foreach($this->matrix->matrix() as $y => $row){
			foreach($row as $x => $M_TYPE){
				$this->setPixel($x, $y, $M_TYPE);
			}
		}

		$this->imagick->drawImage($this->imagickDraw);
	}

	/**
	 * draws a single pixel at the given position
	 */
	protected function setPixel(int $x, int $y, int $M_TYPE):void{

		if(!$this->options->drawLightModules && !$this->matrix->check($x, $y)){
			return;
		}

		$this->imagickDraw->setFillColor($this->moduleValues[$M_TYPE]);

		$this->options->drawCircularModules && !$this->matrix->checkTypeIn($x, $y, $this->options->keepAsSquare)
			? $this->imagickDraw->circle(
				(($x + 0.5) * $this->scale),
				(($y + 0.5) * $this->scale),
				(($x + 0.5 + $this->options->circleRadius) * $this->scale),
				(($y + 0.5) * $this->scale)
			)
			: $this->imagickDraw->rectangle(
				($x * $this->scale),
				($y * $this->scale),
				(($x + 1) * $this->scale),
				(($y + 1) * $this->scale)
			);
	}

}
