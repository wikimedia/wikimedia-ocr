<?php
declare(strict_types=1);

namespace App\Engine;

/**
 * Post-processes OCR text to convert footnote markers and footnote text into
 * Wikitext <ref> tags.
 *
 * Books commonly use patterns like "text¹" or "text1)" with corresponding
 * footnote text at the bottom of the page (e.g. "1) footnote text"). This
 * class detects such patterns and replaces the inline markers with
 * <ref>footnote text</ref>, removing the footnote block from the bottom.
 *
 * Supported footnote block formats:
 *   - Numeric:           1) note text   or   1. note text
 *   - Bracket:           [1] note text
 *   - Unicode superscript: ¹) note text
 *
 * Supported inline marker formats:
 *   - Trailing digits followed by ) :  word1)   → replaced by <ref>
 *   - Trailing Unicode superscripts:   word¹    → replaced by <ref>
 *   - Bracket-wrapped digits:          word[1]  → replaced by <ref>
 */
class ReferencePostProcessor
{

	/**
	 * Map of Unicode superscript characters to their ASCII digit equivalents.
	 * @var string[]
	 */
	private const SUPERSCRIPT_MAP = [
		'⁰' => '0',
		'¹' => '1',
		'²' => '2',
		'³' => '3',
		'⁴' => '4',
		'⁵' => '5',
		'⁶' => '6',
		'⁷' => '7',
		'⁸' => '8',
		'⁹' => '9',
	];

	/**
	 * Regex pattern matching a single footnote block line.
	 * Group 1 captures the marker key (digit string or bracket-wrapped digits).
	 */
	private const FOOTNOTE_LINE_PATTERN =
		'/^(?:' .
		'\[(\d+)\]' .        // [1] style
		'|(\d+)[.)]\s' .     // 1) or 1. style (space required after punctuation)
		'|([⁰¹²³⁴⁵⁶⁷⁸⁹]+)[.)]\s' . // ¹) style
		')\s*.+/u';

	/**
	 * Process OCR text and insert Wikitext <ref> tags for detected footnotes.
	 *
	 * @param string $text Raw OCR output text.
	 * @return string Text with footnote markers replaced by <ref> tags, and
	 *   the footnote block removed from the bottom.
	 */
	public static function process(string $text): string
	{
		$lines = explode("\n", $text);

		[$footnotes, $footnoteStartIndex] = self::extractFootnotes($lines);

		if (!$footnotes) {
			return $text;
		}

		// Keep only the body lines (above the footnote block).
		$bodyLines = array_slice($lines, 0, $footnoteStartIndex);

		// Replace inline markers in body lines.
		$bodyLines = self::replaceInlineMarkers($bodyLines, $footnotes);

		return implode("\n", $bodyLines);
	}

	/**
	 * Scan to find and extract the footnote block at the end of the text.
	 *
	 * @param string[] $lines All lines of the OCR text.
	 * @return array{array<string,string>, int} Tuple of [marker=>text map, start line index].
	 *   Returns [[], 0] if no footnote block is detected.
	 */
	private static function extractFootnotes(array $lines): array
	{
		// A footnote block is a contiguous block of text at the end of the document.
		// It consists of footnote start lines, continuation lines, and optionally blank lines.
		// The block must start with a valid footnote line.

		$footnoteStartIndex = -1;

		// 1. Scan from top to bottom, finding every valid footnote start line.
		for ($i = 0; $i < count($lines); $i++) {
			$line = trim($lines[$i]);
			if ($line === '') {
				continue;
			}

			[$key,] = self::parseFootnoteLine($line);

			if ($key !== null) {
				// This line is a valid footnote start.
				// Let's check if the REST of the document forms a valid footnote block
				// starting from this line.
				// A valid footnote block means every non-blank line from here to the end
				// is either a footnote start, or a continuation of a preceding footnote.
				// (Since any line can be a continuation, the only requirement is that the
				// block starts with a footnote line, which we already know is true for $i).
				// We want the *highest* $i that forms a plausible block. But wait, if any line
				// can be a continuation, then the very first footnote start we find could claim
				// the rest of the document! That's too aggressive.

				// Better approach: Work upwards from the bottom.
				// The last non-blank line of the document must be either:
				// - A footnote start
				// - A continuation of a footnote start higher up
			}
		}

		// Let's do a strict bottom-up scan. 
		// We collect lines. When we hit a line, it is either a footnote start or a continuation.
		// We keep going up until we find a line that is NEITHER a footnote start NOR a valid continuation.
		// What is a valid continuation? ANY line is a valid continuation, UNTIL we hit the line
		// that actually started that footnote. Once we hit a non-footnote line and we aren't 
		// inside a footnote, we break.

		$footnotes = [];
		$currentFootnoteLines = [];
		$footnoteStartIndex = count($lines);

		// We'll collect the blocks from bottom to top.
		for ($i = count($lines) - 1; $i >= 0; $i--) {
			$line = trim($lines[$i]);

			if ($line === '') {
				if (!empty($currentFootnoteLines)) {
					// Blank line within a footnote
					$currentFootnoteLines[] = '';
				}
				continue;
			}

			[$key, $footnoteText] = self::parseFootnoteLine($line);

			if ($key !== null) {
				// We found the start of the current footnote (or a new one).
				$normKey = self::normaliseSuperscript($key);

				// Combine the text
				$fullText = $footnoteText;
				if (!empty($currentFootnoteLines)) {
					// $currentFootnoteLines were collected bottom-up, so reverse them.
					$currentFootnoteLines = array_reverse($currentFootnoteLines);
					$fullText .= ' ' . implode(' ', array_filter($currentFootnoteLines));
				}

				// Prepend to our list of footnotes
				$footnotes = [$normKey => trim($fullText)] + $footnotes;

				// Reset continuation collector
				$currentFootnoteLines = [];
				$footnoteStartIndex = $i;
			} else {
				// This is not a footnote start.
				// Are we collecting a footnote?
				// Just treat it as a continuation line of the footnote we are currently looking for the start of.
				$currentFootnoteLines[] = $line;
			}
		}

		// If $currentFootnoteLines is not empty after the loop, it means we scanned all the way
		// to the top of the file without finding a footnote start for these lines.
		// Or, when we stopped scanning (if we could break early), we had leftover lines.
		// But wait! If we just treat non-footnote lines as continuations, the scan will consume 
		// the ENTIRE document up to the first footnote start it finds!
		// That's incorrect. A footnote block only exists if the text at the bottom is a footnote.
		// We should break the loop when we find a line that is NOT a footnote start AND we are 
		// NOT currently seeking the start of a footnote. BUT wait, if we are at the bottom, 
		// the first line we see is non-footnote. We immediately collect it into $currentFootnoteLines.
		// Then we scan up. We find body text. We keep collecting it. Oh no.

		// Okay, here is the rule: 
		// We scan from top to bottom.
		// 1. Gather all line indices that are footnote starts.
		$starts = [];
		for ($i = 0; $i < count($lines); $i++) {
			if (trim($lines[$i]) !== '' && self::parseFootnoteLine(trim($lines[$i]))[0] !== null) {
				$starts[] = $i;
			}
		}

		if (empty($starts)) {
			return [[], 0];
		}

		// 2. We want to find a split point index S in the document such that:
		// - S is in $starts
		// - From S to the end of the document, the density of footnote starts is "high",
		//   or more simply: the block from S to the end looks like footnotes.
		// For a block to look like footnotes:
		// S is a footprint start.
		// Every line from S to the end is either:
		// a) A footnote start
		// b) A continuation line (i.e. we don't allow too many lines without a start).

		// Let's use a simple heuristic:
		// The footnote block starts at the *last* contiguous block of footnotes at the end of the file.
		// A block is "broken" if we see a footnote, and then too many lines pass without another footnote.
		// Actually, even simpler: just scan upwards from the bottom.

		$footnotesReversed = [];
		$footnoteStartIndex = count($lines);

		// Break the document from bottom to top into chunks separated by footnote starts.
		$currentChunk = [];
		$inFootnoteBlock = true; // Assume the end of the document could be a footnote.

		for ($i = count($lines) - 1; $i >= 0; $i--) {
			$line = trim($lines[$i]);

			if ($line === '') {
				if (!empty($currentChunk)) {
					$currentChunk[] = $line;
				}
				continue;
			}

			[$key, $footnoteText] = self::parseFootnoteLine($line);

			if ($key !== null) {
				// We found a footnote start!
				$normKey = self::normaliseSuperscript($key);
				$chunkLines = array_reverse($currentChunk);
				$fullText = trim($footnoteText . ' ' . implode(' ', array_filter($chunkLines)));

				$footnotesReversed[] = [
					'key' => $normKey,
					'text' => $fullText,
					'lineIndex' => $i
				];

				$currentChunk = [];
				$footnoteStartIndex = $i;
				$inFootnoteBlock = true;
			} else {
				// Not a footnote start.
				if ($inFootnoteBlock && count($currentChunk) < 3) {
					// We allow a few lines of continuation logic.
					$currentChunk[] = $line;
				} else {
					// We hit a line that breaks the footnote block.
					// Either we've been scanning body text and never hit a footnote,
					// or we hit too many non-footnote lines.
					// Stop the block extraction here.
					break;
				}
			}
		}

		// If we reached here, and we never found any footnotes inside the block we scanned from the bottom:
		if (empty($footnotesReversed)) {
			return [[], 0];
		}

		// Reconstruct the footnotes map in the correct order (top to bottom)
		$footnotes = [];
		for ($i = count($footnotesReversed) - 1; $i >= 0; $i--) {
			$fn = $footnotesReversed[$i];
			$footnotes[$fn['key']] = $fn['text'];
		}

		// Note: we might have broken the loop, meaning $currentChunk contains body text that
		// was positioned just ABOVE the highest footnote we found. We don't care, because
		// $footnoteStartIndex is already correctly set to the index of the highest footnote we found.

		return [$footnotes, $footnoteStartIndex];
	}

	/**
	 * Try to parse a line as the start of a footnote entry.
	 *
	 * @param string $line A trimmed text line.
	 * @return array{string|null, string} Tuple of [marker key, footnote text].
	 *   Returns [null, ''] if the line is not a footnote entry.
	 */
	private static function parseFootnoteLine(string $line): array
	{
		// [1] style
		if (preg_match('/^\[(\d+)\]\s*(.+)$/u', $line, $m)) {
			return [$m[1], trim($m[2])];
		}

		// 1) or 1. style
		if (preg_match('/^(\d+)[.)]\s+(.+)$/u', $line, $m)) {
			return [$m[1], trim($m[2])];
		}

		// ¹) Unicode superscript style
		$superscriptChars = implode('', array_keys(self::SUPERSCRIPT_MAP));
		if (preg_match('/^([' . $superscriptChars . ']+)[.)]\s+(.+)$/u', $line, $m)) {
			return [$m[1], trim($m[2])];
		}

		return [null, ''];
	}

	/**
	 * Replace inline footnote markers in body lines with <ref> tags.
	 *
	 * @param string[] $bodyLines The lines of body text (no footnote block).
	 * @param array<string,string> $footnotes Map of normalised marker key → footnote text.
	 * @return string[] Body lines with markers replaced.
	 */
	private static function replaceInlineMarkers(array $bodyLines, array $footnotes): array
	{
		// Build a sorted list of keys (longest first to avoid partial replacements).
		$keys = array_keys($footnotes);
		usort($keys, static function (string $a, string $b): int {
			return strlen((string) $b) - strlen((string) $a);
		});

		$superscriptChars = implode('', array_keys(self::SUPERSCRIPT_MAP));

		foreach ($bodyLines as &$line) {
			foreach ($keys as $key) {
				$keyString = (string) $key;
				$refTag = '<ref>' . $footnotes[$key] . '</ref>';
				$escapedKey = preg_quote($keyString, '/');

				// Replace trailing digit(s) followed by ) — e.g. word1)
				$line = preg_replace('/(' . $escapedKey . ')\)(?=\s|$)/u', $refTag, $line);

				// Replace bracket-wrapped — e.g. word[1]
				$line = preg_replace('/\[' . $escapedKey . '\]/u', $refTag, $line);

				// Replace Unicode superscripts — e.g. word¹
				$superscriptKey = self::digitToSuperscript($keyString);
				if ($superscriptKey !== $keyString) {
					$escapedSuper = preg_quote($superscriptKey, '/');
					$line = preg_replace('/[' . $superscriptChars . ']*' . $escapedSuper . '[' . $superscriptChars . ']*/u', $refTag, $line);
				}
			}
		}
		unset($line);

		return $bodyLines;
	}

	/**
	 * Normalise a string of Unicode superscript digits to ASCII digit string.
	 *
	 * @param string $str A string potentially containing Unicode superscript chars.
	 * @return string ASCII digit equivalent.
	 */
	private static function normaliseSuperscript(string $str): string
	{
		return strtr($str, self::SUPERSCRIPT_MAP);
	}

	/**
	 * Convert an ASCII digit string to its Unicode superscript equivalent.
	 *
	 * @param string $digits ASCII digit string.
	 * @return string Unicode superscript string, or the original if no mapping exists.
	 */
	private static function digitToSuperscript(string $digits): string
	{
		$map = array_flip(self::SUPERSCRIPT_MAP);
		$result = '';
		foreach (str_split($digits) as $ch) {
			$result .= $map[$ch] ?? $ch;
		}
		return $result;
	}
}
