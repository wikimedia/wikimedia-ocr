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

	/** @var string|null Image data. */
	private $data;

	/** @var int|null Image size in bytes. */
	private $size;

	/**
	 * @param int[] $crop
	 */
	public function __construct( string $imageUrl, array $crop = [] ) {
		$this->imageUrl = $imageUrl;
		$this->crop = $crop;
	}

	public function getUrl(): string {
		return $this->imageUrl;
	}

	public function needsCropping(): bool {
		return isset( $this->crop['width'] ) && $this->crop['width'] > 0
			&& isset( $this->crop['height'] ) && $this->crop['height'] > 0;
	}

	public function getCrop(): Crop {
		return new Crop(
			new Point( $this->crop['x'], $this->crop['y'] ),
			new Box( $this->crop['width'], $this->crop['height'] )
		);
	}

	public function hasData(): bool {
		return null !== $this->data;
	}

	public function getData(): string {
		if ( null === $this->data ) {
			throw new LogicException( 'Image::setData() must be called before getData()' );
		}
		return $this->data;
	}

	public function setData( string $data ): void {
		$this->data = $data;
	}

	/**
	 * Get the image data size in bytes.
	 * @return int
	 */
	public function getSize(): int {
		if ( null === $this->data ) {
			throw new LogicException( 'Image::setData() must be called before getSize()' );
		}
		return $this->size ?? strlen( $this->data );
	}

	public function setSize( int $size ): void {
		$this->size = $size;
	}
}
