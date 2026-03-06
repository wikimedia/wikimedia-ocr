<?php
declare( strict_types=1 );

namespace App\Tests\Engine;

use App\Engine\ReferencePostProcessor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Engine\ReferencePostProcessor
 */
class ReferencePostProcessorTest extends TestCase {

	/**
	 * @dataProvider provideProcess
	 */
	public function testProcess( string $input, string $expected ): void {
		$this->assertSame( $expected, ReferencePostProcessor::process( $input ) );
	}

	public function provideProcess(): array {
		return [
			'no footnotes — text returned unchanged' => [
				"This is a simple line.\nAnother line with no markers.",
				"This is a simple line.\nAnother line with no markers.",
			],

			'numeric marker with ) delimiter' => [
				"The capital of France is Paris.1)\n" .
				"Another fact is here.2)\n" .
				"\n" .
				"1) Paris has been the capital since the 10th century.\n" .
				"2) This is fact two.",
				"The capital of France is Paris.<ref>Paris has been the capital since the 10th century.</ref>\n" .
				"Another fact is here.<ref>This is fact two.</ref>\n",
			],

			'unicode superscript markers (¹²)' => [
				"Text with a superscript¹ and another²\n" .
				"1) First footnote.\n" .
				"2) Second footnote.",
				"Text with a superscript<ref>First footnote.</ref> and another<ref>Second footnote.</ref>",
			],

			'bracket-style markers [1]' => [
				"A claim that needs a source.[1]\n" .
				"[1] The source of the claim.",
				"A claim that needs a source.<ref>The source of the claim.</ref>",
			],

			'multi-line footnote body' => [
				"Short text.1)\n" .
				"1) This footnote spans\n" .
				"more than one line.",
				"Short text.<ref>This footnote spans more than one line.</ref>",
			],

			'empty string passthrough' => [
				'',
				'',
			],

			'unicode superscript in footnote block header (¹) style)' => [
				"Word with mark¹\n" .
				"¹) The footnote text here.",
				"Word with mark<ref>The footnote text here.</ref>",
			],
		];
	}
}
