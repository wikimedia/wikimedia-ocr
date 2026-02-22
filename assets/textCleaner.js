
//basic cleanup such as whitespace, punctuation spacing, and dash normalization
export function cleanUpBasic(text) {
    if (!text || typeof text !== "string") return text;

    return text
        
        //trim front on back whitespace
        .trim()

        //remove trailing spaces at line ends
        .replace(/ +\n/g, '\n')

        //dash normalization
        .replace(/([^\!])--([^>])/g, '$1—$2')   //double hyphen to emdash
        .replace(/\s+—\s+/g, '—')               //emdash spacing
        .replace(/\s+–\s+/g, '–')               //endash spacing
        .replace(/ +— +/g, '—')

        //hyphenated line joins but leave |- alone for tables
        .replace(/([^\|])-\n/g, '$1')

        //remove punctuation spacing
        .replace(/ ([;:\?!,])/g, '$1')

        //quote normalization
        .replace(/[“”]/g, '"')
        .replace(/[‘’`]/g, '\'')
}


//OCR specific cleanup, such as common OCR errors and broken ligatures.
export function cleanUpOCR(text) {
    if (!text || typeof text !== "string") return text;

    return text
        //"tlie" = "the" 
        .replace(/tlie/g, 'the')

        // "would" = "could"
		.replace(/woidd/g, 'would')
		.replace(/coidd/g, 'could')
		.replace(/shoidd/g, 'should')

        //specific apostrophe errors, including common contractions
        .replace(/\bwouldve\b/gi, "would've")
        .replace(/\bcouldve\b/gi, "could've")
        .replace(/\bshouldve\b/gi, "should've")
        .replace(/\bmightve\b/gi, "might've")
        .replace(/\bmustve\b/gi, "must've")

        .replace(/\bwouldnt\b/gi, "wouldn't")
        .replace(/\bcouldnt\b/gi, "couldn't")
        .replace(/\bshouldnt\b/gi, "shouldn't")
        .replace(/\bmustnt\b/gi, "mustn't")

        .replace(/\bdidnt\b/gi, "didn't")
        .replace(/\bdoesnt\b/gi, "doesn't")
        .replace(/\bdont\b/gi, "don't")
        .replace(/\bisnt\b/gi, "isn't")
        .replace(/\barent\b/gi, "aren't")
        .replace(/\bwasnt\b/gi, "wasn't")
        .replace(/\bwerent\b/gi, "weren't")

        .replace(/\bhavent\b/gi, "haven't")
        .replace(/\bhasnt\b/gi, "hasn't")
        .replace(/\bhadnt\b/gi, "hadn't")

        .replace(/\bim\b/gi, "I'm")
        .replace(/\bive\b/gi, "I've")
        .replace(/\bid\b/gi, "I'd")

        .replace(/\byoure\b/gi, "you're")
        .replace(/\btheyre\b/gi, "they're")

        //full stop before lowercase letter changed to a comma
		.replace(/\. ([a-z])/g, ', $1')

        //broken ligatures
        .replace(/ﬁ/g, 'fi')
        .replace(/ﬂ/g, 'fl');
}

//for some specific linebreak cleanup
export function softLineBreaks(text) {
    if (!text || typeof text !== "string") return text;

    return text        
        // lines that start with " should probably be new lines,
		// if the previous line ends in punctuation,
		// other than a comma or semicolon
		// and let's get rid of trailing space while we're at it
		.replace(/([^\n\w,;])\n\" */g, '$1\n\n"')

        // lines that end with " should probably precede a new line,
		// unless preceded by a comma,
		// or unless the new line starts with a lower-case letter;
		// and let's get rid of preceding space while we're at it
		.replace(/([^,])\ *\"\n([^a-z\n])/g, '$1"\n\n$2')

        // remove single line breaks; preserve multiple.
		// but not if there's a tag, template or table syntax either side of the line break
		.replace(/([^>}\|\n])\n([^:#\*<{\|\n])/g, '$1 $2')

        // collapse sequences of spaces into a single space
		.replace(/  +/g, ' ');
}

//janitor
export function cleanText(text, options = {}) {
    const {
        basic = true,
        ocr = false,
        linebreaks = false
    } = options;

    let output = text;

    if (basic) output = cleanUpBasic(output);
    if (ocr) output = cleanUpOCR(output);
    if (linebreaks) output = softLineBreaks(output);

    return output;
}