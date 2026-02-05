<?php
declare( strict_types = 1 );

namespace App\Engine;

use Imagine\Filter\Basic\Crop;
use Imagine\Image\Box;
use Imagine\Image\Point;
use LogicException;

class Image {

	/** @var string Original URL of image. */
	private $imageUrl;

	/** @var int[] Array of floats with keys: `x`, `y`, `width`, and `height`. */
	private $crop;

	/** @var int Rotation angle in degrees. */
	private $rotate;

	/** @var string|null Image data. */
	private $data;

	/** @var int|null Image size in bytes. */
	private $size;

	/**
	 * @param string $imageUrl
	 * @param int[] $crop
	 * @param int $rotate
	 */
	public function __construct( string $imageUrl, array $crop = [], int $rotate = 0 ) {
		$this->imageUrl = $imageUrl;
		$this->crop = $crop;
		$this->rotate = $rotate;
	}

	/**
	 * @return string
	 */
	public function getUrl(): string {
		return $this->imageUrl;
	}

	public function needsCropping(): bool {
		return isset( $this->crop['width'] ) && $this->crop['width'] > 0
			&& isset( $this->crop['height'] ) && $this->crop['height'] > 0;
	}

	/**
	 * @return Crop
	 */
	public function getCrop(): Crop {
		return new Crop(
			new Point( $this->crop['x'], $this->crop['y'] ),
			new Box( $this->crop['width'], $this->crop['height'] )
		);
	}

	/**
	 * Check if rotation is needed.
	 * @return bool
	 */
	public function needsRotation(): bool {
		return $this->rotate !== 0;
	}

	/**
	 * Get the rotation angle in degrees.
	 * @return int
	 */
	public function getRotate(): int {
		return $this->rotate;
	}

	public function hasData(): bool {
		return $this->data !== null;
	}

	/**
	 * @return string
	 */
	public function getData(): string {
		if ( $this->data === null ) {
			throw new LogicException( 'Image::setData() must be called before getData()' );
		}
		return $this->data;
	}

	/**
	 * @param string $data
	 */
	public function setData( string $data ): void {
		$this->data = $data;
	}

	/**
	 * Get the image data size in bytes.
	 * @return int
	 */
	public function getSize(): int {
		if ( $this->data === null ) {
			throw new LogicException( 'Image::setData() must be called before getSize()' );
		}
		return $this->size ?? strlen( $this->data );
	}

	/**
	 * @param int $size
	 */
	public function setSize( int $size ): void {
		$this->size = $size;
	}
}
