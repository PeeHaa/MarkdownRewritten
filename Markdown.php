<?php
/**
 * Markdown Rewritten - A rewrite of the PHP Markdown code
 *
 * PHP 5.3
 *
 * @author     Pieter Hordijk <info@pieterhordijk.com> (Markdown Rewritten)
 * @copyright  Copyright (c) 2012 Pieter Hordijk
 * @author     Michel Fortin <http://michelf.com/projects/php-markdown> (PHP Markdown)
 * @copyright  Copyright (c) 2012 Michel Fortin
 * @author     John Gruber <http://daringfireball.net/projects/markdown> (Original Markdown)
 * @copyright  Copyright (c) 2012 John Gruber
 * @license    Markdown is free software, available under the terms of the
 *             BSD-style open source license reproduced below, or, at your
 *             option, under the GNU General Public License version 2
 *             or a later version.
 *
 *             Redistribution and use in source and binary forms, with or without
 *             modification, are permitted provided that the following conditions
 *             are met:
 *
 *             - Redistributions of source code must retain the above copyright
 *               notice, this list of conditions and the following disclaimer.
 *
 *             - Redistributions in binary form must reproduce the above copyright
 *               notice, this list of conditions and the following disclaimer in
 *               the documentation and/or other materials provided with the
 *               distribution.
 *
 *             - Neither the name �PHP Markdown� nor the names of its contributors
 *               may be used to endorse or promote products derived from this
 *               software without specific prior written permission.
 *
 *             This software is provided by the copyright holders and
 *             contributors �as is� and any express or implied warranties,
 *             including, but not limited to, the implied warranties of
 *             merchantability and fitness for a particular purpose are
 *             disclaimed. In no event shall the copyright owner or contributors
 *             be liable for any direct, indirect, incidental, special, exemplary,
 *             or consequential damages (including, but not limited to,
 *             procurement of substitute goods or services; loss of use, data,
 *             or profits; or business interruption) however caused and on any
 *             theory of liability, whether in contract, strict liability, or
 *             tort (including negligence or otherwise) arising in any way out
 *             of the use of this software, even if advised of the possibility
 *             of such damage.
 * @version    1.0.0
 */
class Markdown
{
    /**
     * @const int The number of spaces representing one tab
     */
    const TAB_WIDTH = 4;

    /**
     * @const string The string used to close empty elements, '>' for HTML
     */
    const EMPTY_ELEMENT_SUFFIX = '>';

    /**
     * @const int Max nested brackets
     */
    const MAX_NESTED_BRACKETS = 6;

    /**
     * @const string Chars to be scaped in regex patterns
     */
    const ESCAPE_CHARS = '\`*_{}[]()>#+-.!';

    /**
     * @const int Max nested URL parenthesis
     */
    const MAX_NESTED_URL_PARENTHESIS = 4;

    /**
     * @var array List of possible em tag regex patterns
     */
    protected $emRegexList = array(''  => '(?:(?<!\*)\*(?!\*)|(?<!_)_(?!_))(?=\S|$)(?![\.,:;]\s)',
                                   '*' => '(?<=\S|^)(?<!\*)\*(?!\*)',
                                   '_' => '(?<=\S|^)(?<!_)_(?!_)',
                                   );

    /**
     * @var array List of possible strong tag regex patterns
     */
    protected $strongRegexList = array(''   => '(?:(?<!\*)\*\*(?!\*)|(?<!_)__(?!_))(?=\S|$)(?![\.,:;]\s)',
                                        '**' => '(?<=\S|^)(?<!\*)\*\*(?!\*)',
                                        '__' => '(?<=\S|^)(?<!_)__(?!_)',
                                        );

    /**
     * @var array List of possible strong tag regex patterns
     */
    protected $emAndStrongRegexBaseList = array(''    => '(?:(?<!\*)\*\*\*(?!\*)|(?<!_)___(?!_))(?=\S|$)(?![\.,:;]\s)',
                                                '***' => '(?<=\S|^)(?<!\*)\*\*\*(?!\*)',
                                                '___' => '(?<=\S|^)(?<!_)___(?!_)',
                                                );

    /**
     * @var array List of document level elements gamut
     *            The gamut is defined by method to run and the priority
     *            so it can easily be sorted
     */
    protected $documentGamut = array('stripLinkDefinitions'     => 20,
                                     'processBasicBlockGamut'   => 30,
                                     );

    /**
     * @var array List of block level elements gamut
     *            The gamut is defined by method to run and the priority
     *            so it can easily be sorted
     */
    protected $blockGamut = array('processHeaders'          => 10,
                                  'processHorizontalRules'  => 20,
                                  'doLists'                 => 40,
                                  'doCodeBlocks'            => 50,
                                  'doBlockQuotes'           => 60,
                                  );

    /**
     * @var array List of inline elements gamut
     *            The gamut is defined by method to run and the priority
     *            so it can easily be sorted
     */
    protected $inlineGamut = array('parseSpan'           => -30,
                                   'doImages'            =>  10,
                                   'doAnchors'           =>  20,
                                   'doAutoLinks'         =>  30,
                                   'encodeAmpsAndAngles' =>  40,
                                   'doItalicsAndBold'    =>  50,
                                   'doHardBreaks'        =>  60,
                                   );

    /**
     * @var array List of al lregex patterns to match em and strong tags
     */
    protected $emAndStrongRegexList;

    /**
     * @var array The URL found in the text
     */
    protected $urls;

    /**
     * @var array The titles found in the text
     */
    protected $titles;

    /**
     * @var array Used to keep track of hashed HTML blocks
     */
    protected $htmlHashes;

    /**
     * @var int The current index of the $htmlHashes array
     *
     * @todo Check the performance / functionality when dropping this
     *       and use count(self::htmlHashes) instead
     */
    protected $currentHashIndex;

    /**
     * @var bool Status flag to avoid invalid nesting.
     */
    protected $inAnchor;

    /**
     * @var string Nested brackets regex pattern
     */
    protected $nestedBracketsRegex;

    /**
     * @var string Nested brackets regex pattern
     */
    protected $nestedUrlParenthesisRegex;

    /**
     * @var string Escaped chars regex pattern
     */
    protected $escapeCharsRegex;

    /**
     * @var bool Whether HTML markup is disabled
     */
    protected $disabledHtml;

    /**
     * @var bool Whether HTML entities are disabled
     */
    protected $disabledEntities;

    /**
     * Initializes variables used throughout the class
     *
     * @param bool $htmlEnabled Whether HTML is allowed
     * @param bool $entitiesEnabled Whether entities are allowed
     *
     * @return void
     */
    public function __construct($htmlEnabled = true, $entitiesEnabled = true)
    {
        $this->initializeAndCleanUp();

        $this->emAndStrongRegexList = $this->prepareItalicsAndBold();

        $this->nestedBracketsRegex = str_repeat('(?>[^\[\]]+|\[', self::MAX_NESTED_BRACKETS);
        $this->nestedBracketsRegex.= str_repeat('\])*', self::MAX_NESTED_BRACKETS);

        $this->nestedUrlParenthesisRegex = str_repeat('(?>[^()\s]+|\(', self::MAX_NESTED_URL_PARENTHESIS);
        $this->nestedUrlParenthesisRegex.= str_repeat('(?>\)))*', self::MAX_NESTED_URL_PARENTHESIS);

        $this->escapeCharsRegex = '[' . preg_quote(self::ESCAPE_CHARS) . ']';

        asort($this->documentGamut);
        asort($this->blockGamut);
        asort($this->inlineGamut);

        $this->disabledHtml = !$htmlEnabled;
        $this->disabledEntities = !$entitiesEnabled;
    }

    /**
     * Cleanup or initialize all used variables both for memory consumption
     * as for the next run of the parser using the same instance
     *
     * @return void
     */
    protected function initializeAndCleanUp()
    {
        $this->urls = array();
        $this->titles = array();
        $this->hashes = array();

        $this->inAnchor = false;

        $this->currentHashIndex = 0;
    }

    /**
     * Builds the regex patterns to match em and strong tags
     *
     * @return array The list of regex patterns
     */
    protected function prepareItalicsAndBold()
    {
        $emAndStrongRegexList = array();
        foreach ($this->emRegexList as $emIdentifier => $emRegex) {
            foreach ($this->strongRegexList as $strongIdentifier => $strongRegex) {
                $tokenRegexList = array();
                if (isset($this->emAndStrongRegexBaseList[$emIdentifier . $strongIdentifier])) {
                    $tokenRegexList[] = $this->emAndStrongRegexBaseList[$emIdentifier . $strongIdentifier];
                }
                $tokenRegexList[] = $emRegex;
                $tokenRegexList[] = $strongRegex;

                $emAndStrongRegexList[$emIdentifier . $strongIdentifier] = '{(' . implode('|', $tokenRegexList) . ')}';
            }
        }

        return $emAndStrongRegexList;
    }

    /**
     * Parses the text from Markdown to nice HTML
     * First it normalizes the text and after that process the text
     *
     * @param string $text The text to parse
     *
     * @return string The parsed text
     */
    public function parse($text)
    {
        $text = $this->normalizeText($text);

        // hash HTML elements
        $text = $this->hashHTMLBlocks($text);

        # Strip any lines consisting only of spaces and tabs.
        # This makes subsequent regexen easier to write, because we can
        # match consecutive blank lines with /\n+/ instead of something
        # contorted like /[ ]*\n+/ .
        $text = preg_replace('/^[ ]+$/m', '', $text);

        # Run document gamut methods.
        foreach ($this->documentGamut as $method => $priority) {
            $text = $this->$method($text);
        }

        $this->initializeAndCleanUp();

        return $text . "\n";
    }

    /**
     * Normalizes the provided text by removing BOM characters, normalizing
     * line breaks and normalizing tabs
     *
     * @param string $text The text to normalize
     *
     * @return string The normalized text
     */
    protected function normalizeText($text)
    {
        $text = $this->removeBom($text);
        $text = $this->normalizeLinebreaks($text);
        $text = $this->normalizeTabs($text);

        return $text;
    }

    /**
     * Removes the BOM characters
     *
     * @param string $text The text
     *
     * @return string The text without a BOM character
     */
    public function removeBom($text)
    {
        return preg_replace('{^\xEF\xBB\xBF|\x1A}', '', $text);
    }

    /**
     * Normalizes linebreaks
     * Replaces all linebreaks with UNIX style linebreaks and adds some linebreaks
     * at the end of the text
     *
     * @param string $text The text
     *
     * @return string The text with normalized linebreaks
     */
    public function normalizeLinebreaks($text)
    {
        return preg_replace('{\r\n?}', "\n", $text) . "\n\n";
    }

    /**
     * Normalizes tabs
     * Replaces all tab characters with spaces defined in the TAB_WIDTH const
     *
     * @param string $text The text
     *
     * @return string The text with normalized tab characters
     */
    protected function normalizeTabs($text)
    {
        return preg_replace_callback('/^.*\t.*$/m', array(&$this, 'normalizeTabsCallback'), $text);
    }

    /**
     * Callback function to replace tabs by spaces
     *
     * @param array $matches The matches returned by the preg_replace call
     *
     * @return string The text with normalized tab characters
     */
    protected function normalizeTabsCallback($matches)
    {
        $line = $matches[0];

        $blocks = explode("\t", $line);
        $line = $blocks[0];

        // prevent adding the first block twice
        unset($blocks[0]);

        foreach ($blocks as $block) {
            $amount = self::TAB_WIDTH - mb_strlen($line, 'UTF-8') % self::TAB_WIDTH;

            $line.= str_repeat(' ', $amount) . $block;
        }

        return $line;
    }

    /**
     * Hash all block level HTML tags (header, ul, table etc)
     *
     * @param string $text The text in which we want to hash elements
     *
     * @return string The text with hahsed elements
     */
    protected function hashHTMLBlocks($text) {
        if ($this->disabledHtml) {
            return $text;
        }

        $lessThanTab = self::TAB_WIDTH - 1;

        // define elements which can both be block or inline level
        $possibleBlockElements = 'ins|del';

        // define elements which can are always block level
        $definiteBlockElements = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|address|';
        $definiteBlockElements.= 'script|noscript|form|fieldset|iframe|math';

        # Regular expression for the content of a block tag.
        $nested_tags_level = 4;
        $attr = '
            (?>             # optional tag attributes
              \s            # starts with whitespace
              (?>
                [^>"/]+     # text outside quotes
              |
                /+(?!>)     # slash not followed by ">"
              |
                "[^"]*"     # text inside double quotes (tolerate ">")
              |
                \'[^\']*\'  # text inside single quotes (tolerate ">")
              )*
            )?
            ';
        $content =
            str_repeat('
                (?>
                  [^<]+         # content without tag
                |
                  <\2           # nested opening tag
                    '.$attr.'   # attributes
                    (?>
                      />
                    |
                      >', $nested_tags_level).  // end of opening tag
                      '.*?'.                    // last level nested tag content
            str_repeat('
                      </\2\s*>  # closing nested tag
                    )
                  |
                    <(?!/\2\s*> # other tags with a different name
                  )
                )*',
                $nested_tags_level);

        $content2 = str_replace('\2', '\3', $content);

        /**
         * First, look for nested blocks, e.g.:
         *  <div>
         *    <div>
         *      tags for inner block must be indented.
         *    </div>
         *  </div>
         *
         * The outermost tags must start at the left margin for this to match, and
         * the inner nested divs must be indented.
         * We need to do this before the next, more liberal match, because the next
         * match will start at the first `<div>` and stop at the first `</div>`.
         */
        $text = preg_replace_callback('{(?>
            (?>
                (?<=\n\n)       # Starting after a blank line
                |               # or
                \A\n?           # the beginning of the doc
            )
            (                   # save in $1

              # Match from `\n<tag>` to `</tag>\n`, handling nested tags
              # in between.

                        [ ]{0,'.$lessThanTab.'}
                        <('.$definiteBlockElements.')   # start tag = $2
                        '.$attr.'>                      # attributes followed by > and \n
                        '.$content.'                    # content, support nesting
                        </\2>                           # the matching end tag
                        [ ]*                            # trailing spaces/tabs
                        (?=\n+|\Z)                      # followed by a newline or end of document

            | # Special version for tags of group a.

                        [ ]{0,'.$lessThanTab.'}
                        <('.$possibleBlockElements.')   # start tag = $3
                        '.$attr.'>[ ]*\n                # attributes followed by >
                        '.$content2.'                   # content, support nesting
                        </\3>                           # the matching end tag
                        [ ]*                            # trailing spaces/tabs
                        (?=\n+|\Z)                      # followed by a newline or end of document

            | # Special case just for <hr />. It was easier to make a special
              # case than to make the other regex more complicated.

                        [ ]{0,'.$lessThanTab.'}
                        <(hr)               # start tag = $2
                        '.$attr.'           # attributes
                        /?>                 # the matching end tag
                        [ ]*
                        (?=\n{2,}|\Z)       # followed by a blank line or end of document

            | # Special case for standalone HTML comments:

                    [ ]{0,'.$lessThanTab.'}
                    (?s:
                        <!-- .*? -->
                    )
                    [ ]*
                    (?=\n{2,}|\Z)       # followed by a blank line or end of document

            | # PHP and ASP-style processor instructions (<? and <%)

                    [ ]{0,'.$lessThanTab.'}
                    (?s:
                        <([?%])         # $2
                        .*?
                        \2>
                    )
                    [ ]*
                    (?=\n{2,}|\Z)       # followed by a blank line or end of document

            )
            )}Sxmi', array(&$this, 'hashHTMLBlocksCallback'), $text);

        return $text;
    }

    /**
     * Callback function for the preg function in hashHtmlBlocks
     *
     * @param array $matches The matches we want callback function to replace
     *
     * @return string The text with replaced data
     */
    protected function hashHTMLBlocksCallback($matches)
    {
        $text = $matches[1];
        $key  = $this->hashBlockElement($text);

        return "\n\n$key\n\n";
    }

    /**
     * Strips link definitions from text and adds them them together with the
     * titles to array
     *
     * @param string $text The text to find the links in
     *
     * @return string The text without the links
     */
    protected function stripLinkDefinitions($text)
    {
        $lessThanTab = self::TAB_WIDTH - 1;

        $text = preg_replace_callback('{
                            ^[ ]{0,'.$lessThanTab.'}\[(.+)\][ ]?: # id = $1
                              [ ]*
                              \n?               # maybe *one* newline
                              [ ]*
                            (?:
                              <(.+?)>           # url = $2
                            |
                              (\S+?)            # url = $3
                            )
                              [ ]*
                              \n?               # maybe one newline
                              [ ]*
                            (?:
                                (?<=\s)         # lookbehind for whitespace
                                ["(]
                                (.*?)           # title = $4
                                [")]
                                [ ]*
                            )?  # title is optional
                            (?:\n+|\Z)
            }xm', array(&$this, 'stripLinkDefinitionsCallback'), $text);

        return $text;
    }

    /**
     * Callback function for the preg function in stripLinkDefinitions
     *
     * @param array $matches The matches we want callback function to replace
     *
     * @return string Empty string because we want to remove the links
     */
    protected function stripLinkDefinitionsCallback($matches)
    {
        $linkId = strtolower($matches[1]);

        $url = $matches[2] == '' ? $matches[3] : $matches[2];
        $this->urls[$linkId] = $url;
        $this->titles[$linkId] =& $matches[4];

        return '';
    }

    /**
     * Hashes block level elements (this function is a shortcut
     * of doing self::hashPart($text, 'B'))
     *
     * @param string $text The text representation of the element
     *
     * @return string The hashed element
     */
    protected function hashBlockElement($text)
    {
        return $this->hashPart($text, 'B');
    }

    /**
     * Hashes a part to a unique text token so we don't have to process it again
     * The original text is stored in an array for future use and the unique
     * token is returned to replace in the whole text.
     * The boundary is used to distinguish between block elements (B),
     * word separators (:) and general use (X)
     *
     * @param string $text The text to add to the hashes
     * @param string $boundary The boundary to use in the key when adding
     *                         to hashes
     *
     * @return string The hashed element
     */
    protected function hashPart($text, $boundary = 'X')
    {
        static $i = 0;

        // First we are going to unhash any hashes found in the text to prevent
        // the need to unhash multiple times at the end
        $text = $this->unHash($text);

        $key = $boundary . "\x1A" . ++$this->currentHashIndex . $boundary;
        $this->htmlHashes[$key] = $text;

        return $key;
    }

    /**
     * Unhashes the text using the stored elements in the array with hashes
     *
     * @param string $text The text to unhash
     *
     * @return string The unhashed text
     */
    protected function unHash($text)
    {
        return preg_replace_callback('/(.)\x1A[0-9]+\1/', array(&$this, 'unHashCallback'), $text);
    }

    /**
     * Replaces the matches (from the unhash regex) with the original values
     *
     * @param array $matches The matches from the unhash regex
     *
     * @return string The unhashed text
     */
    protected function unHashCallback($matches)
    {
        return $this->htmlHashes[$matches[0]];
    }

    /**
     * First all raw HTML gets hashed and after that we are going through the
     * block gamut as defined in self::blockGamut
     *
     * @param string $text The text we are going to process
     *
     * @return string The text with the parsed block elements
     */
    protected function processBlockGamut($text)
    {
        $text = $this->hashHTMLBlocks($text);

        return $this->processBasicBlockGamut($text);
    }

    /**
     * Parse the block gamut using the methods defined in self::blockGamut
     *
     * @param string $text The text we are going to process
     *
     * @return string The text with the parsed block elements
     */
    protected function processBasicBlockGamut($text)
    {
        foreach ($this->blockGamut as $method => $priority) {
            $text = $this->$method($text);
        }

        $text = $this->formParagraphs($text);

        return $text;
    }

    /**
     * Parse headers in the text of both styles: Setext and atx
     * Setext supports to levels of headers:
     * Header 1
     * ========
     *
     * Header 2
     * --------
     *
     * Atx style headers:
     * # Header 1
     * ## Header 2
     * ## Header 2 with closing hashes ##
     * ...
     * ###### Header 6
     *
     * @param string $text The text we are going to process
     *
     * @return string The text with the parsed headers
     */
    protected function processHeaders($text)
    {
        $text = preg_replace_callback('{ ^(.+?)[ ]*\n(=+|-+)[ ]*\n+ }mx', array(&$this, 'processSetextHeadersCallback'), $text);

        $text = preg_replace_callback('{
                ^(\#{1,6})  # $1 = string of #\'s
                [ ]*
                (.+?)       # $2 = Header text
                [ ]*
                \#*         # optional closing #\'s (not counted)
                \n+
            }xm',
            array(&$this, 'processAxtHeadersCallback'), $text);

        return $text;
    }

    /**
     * Parse Setext style headers from the processHeaders regex
     *
     * @param array $matches The match we want to replace
     *
     * @return string The text with the parsed Setext headers
     */
    protected function processSetextHeadersCallback($matches) {
        // Terrible hack to check we haven't found an empty list item.
        if ($matches[2] == '-' && preg_match('{^-(?: |$)}', $matches[1])) {
            return $matches[0];
        }

        if ($matches[2]{0} == '=') {
            $level = 1;
        } else {
            $level = 2;
        }

        $block = '<h' . $level . '>' . $this->runSpanGamut($matches[1]) . '</h' . $level . '>';

        return "\n" . $this->hashBlockElement($block) . "\n\n";
    }

    /**
     * Parse Atx style headers from the processHeaders regex
     *
     * @param array $matches The match we want to replace
     *
     * @return string The text with the parsed Atx headers
     */
    protected function processAxtHeadersCallback($matches)
    {
        $level = strlen($matches[1]);
        $block = '<h' . $level . '>' . $this->runSpanGamut($matches[2]) . '</h' . $level . '>';

        return "\n" . $this->hashBlockElement($block) . "\n\n";
    }

    /**
     * Parse horizontal rules
     *
     * @param string $text The text we are going to process
     *
     * @return string The text with the parsed horizontal rules
     */
    protected function processHorizontalRules($text)
    {
        return preg_replace(
            '{
                ^[ ]{0,3}       # Leading space
                ([-*_])         # $1: First marker
                (?>             # Repeated marker group
                    [ ]{0,2}    # Zero, one, or two spaces.
                    \1          # Marker character
                ){2,}           # Group repeated at least twice
                [ ]*            # Tailing spaces
                $               # End of line.
            }mx',
            "\n".$this->hashBlockElement('<hr' . self::EMPTY_ELEMENT_SUFFIX) . "\n",
            $text);
    }


	function runSpanGamut($text) {
	#
	# Run span gamut tranformations.
	#
		foreach ($this->inlineGamut as $method => $priority) {
			$text = $this->$method($text);
		}

		return $text;
	}


	function doHardBreaks($text) {
		# Do hard breaks:
		return preg_replace_callback('/ {2,}\n/',
			array(&$this, '_doHardBreaks_callback'), $text);
	}
	function _doHardBreaks_callback($matches) {
		return $this->hashPart('<br' . self::EMPTY_ELEMENT_SUFFIX . "\n");
	}


	function doAnchors($text) {
	#
	# Turn Markdown link shortcuts into XHTML <a> tags.
	#
		if ($this->inAnchor) return $text;
		$this->inAnchor = true;

		#
		# First, handle reference-style links: [link text] [id]
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				('.$this->nestedBracketsRegex.')	# link text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]
			)
			}xs',
			array(&$this, '_doAnchors_reference_callback'), $text);

		#
		# Next, inline-style links: [link text](url "optional title")
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  \[
				('.$this->nestedBracketsRegex.')	# link text = $2
			  \]
			  \(			# literal paren
				[ \n]*
				(?:
					<(.+?)>	# href = $3
				|
					('.$this->nestedUrlParenthesisRegex.')	# href = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# Title = $7
				  \6		# matching quote
				  [ \n]*	# ignore any spaces/tabs between closing quote and )
				)?			# title is optional
			  \)
			)
			}xs',
			array(&$this, '_doAnchors_inline_callback'), $text);

		#
		# Last, handle reference-style shortcuts: [link text]
		# These must come last in case you've also got [link text][1]
		# or [link text](/foo)
		#
		$text = preg_replace_callback('{
			(					# wrap whole match in $1
			  \[
				([^\[\]]+)		# link text = $2; can\'t contain [ or ]
			  \]
			)
			}xs',
			array(&$this, '_doAnchors_reference_callback'), $text);

		$this->inAnchor = false;
		return $text;
	}
	function _doAnchors_reference_callback($matches) {
		$whole_match =  $matches[1];
		$link_text   =  $matches[2];
		$link_id     =& $matches[3];

		if ($link_id == "") {
			# for shortcut links like [this][] or [this].
			$link_id = $link_text;
		}

		# lower-case and turn embedded newlines into spaces
		$link_id = strtolower($link_id);
		$link_id = preg_replace('{[ ]?\n}', ' ', $link_id);

		if (isset($this->urls[$link_id])) {
			$url = $this->urls[$link_id];
			$url = $this->encodeAttribute($url);

			$result = "<a href=\"$url\"";
			if ( isset( $this->titles[$link_id] ) ) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}

			$link_text = $this->runSpanGamut($link_text);
			$result .= ">$link_text</a>";
			$result = $this->hashPart($result);
		}
		else {
			$result = $whole_match;
		}
		return $result;
	}
	function _doAnchors_inline_callback($matches) {
		$whole_match	=  $matches[1];
		$link_text		=  $this->runSpanGamut($matches[2]);
		$url			=  $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		$url = $this->encodeAttribute($url);

		$result = "<a href=\"$url\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\"";
		}

		$link_text = $this->runSpanGamut($link_text);
		$result .= ">$link_text</a>";

		return $this->hashPart($result);
	}


	function doImages($text) {
	#
	# Turn Markdown image shortcuts into <img> tags.
	#
		#
		# First, handle reference-style labeled images: ![alt text][id]
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nestedBracketsRegex.')		# alt text = $2
			  \]

			  [ ]?				# one optional space
			  (?:\n[ ]*)?		# one optional newline followed by spaces

			  \[
				(.*?)		# id = $3
			  \]

			)
			}xs',
			array(&$this, '_doImages_reference_callback'), $text);

		#
		# Next, handle inline images:  ![alt text](url "optional title")
		# Don't forget: encode * and _
		#
		$text = preg_replace_callback('{
			(				# wrap whole match in $1
			  !\[
				('.$this->nestedBracketsRegex.')		# alt text = $2
			  \]
			  \s?			# One optional whitespace character
			  \(			# literal paren
				[ \n]*
				(?:
					<(\S*)>	# src url = $3
				|
					('.$this->nestedUrlParenthesisRegex.')	# src url = $4
				)
				[ \n]*
				(			# $5
				  ([\'"])	# quote char = $6
				  (.*?)		# title = $7
				  \6		# matching quote
				  [ \n]*
				)?			# title is optional
			  \)
			)
			}xs',
			array(&$this, '_doImages_inline_callback'), $text);

		return $text;
	}
	function _doImages_reference_callback($matches) {
		$whole_match = $matches[1];
		$alt_text    = $matches[2];
		$link_id     = strtolower($matches[3]);

		if ($link_id == "") {
			$link_id = strtolower($alt_text); # for shortcut links like ![this][].
		}

		$alt_text = $this->encodeAttribute($alt_text);
		if (isset($this->urls[$link_id])) {
			$url = $this->encodeAttribute($this->urls[$link_id]);
			$result = "<img src=\"$url\" alt=\"$alt_text\"";
			if (isset($this->titles[$link_id])) {
				$title = $this->titles[$link_id];
				$title = $this->encodeAttribute($title);
				$result .=  " title=\"$title\"";
			}
			$result .= self::EMPTY_ELEMENT_SUFFIX;
			$result = $this->hashPart($result);
		}
		else {
			# If there's no such link ID, leave intact:
			$result = $whole_match;
		}

		return $result;
	}
	function _doImages_inline_callback($matches) {
		$whole_match	= $matches[1];
		$alt_text		= $matches[2];
		$url			= $matches[3] == '' ? $matches[4] : $matches[3];
		$title			=& $matches[7];

		$alt_text = $this->encodeAttribute($alt_text);
		$url = $this->encodeAttribute($url);
		$result = "<img src=\"$url\" alt=\"$alt_text\"";
		if (isset($title)) {
			$title = $this->encodeAttribute($title);
			$result .=  " title=\"$title\""; # $title already quoted
		}
		$result .= self::EMPTY_ELEMENT_SUFFIX;

		return $this->hashPart($result);
	}


	function doLists($text) {
	#
	# Form HTML ordered (numbered) and unordered (bulleted) lists.
	#
		$less_than_tab = self::TAB_WIDTH - 1;

		# Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re  = '[*+-]';
		$marker_ol_re  = '\d+[\.]';
		$marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";

		$markers_relist = array(
			$marker_ul_re => $marker_ol_re,
			$marker_ol_re => $marker_ul_re,
			);

		foreach ($markers_relist as $marker_re => $other_marker_re) {
			# Re-usable pattern to match any entirel ul or ol list:
			$whole_list_re = '
				(								# $1 = whole list
				  (								# $2
					([ ]{0,'.$less_than_tab.'})	# $3 = number of spaces
					('.$marker_re.')			# $4 = first list item marker
					[ ]+
				  )
				  (?s:.+?)
				  (								# $5
					  \z
					|
					  \n{2,}
					  (?=\S)
					  (?!						# Negative lookahead for another list item marker
						[ ]*
						'.$marker_re.'[ ]+
					  )
					|
					  (?=						# Lookahead for another kind of list
					    \n
						\3						# Must have the same indentation
						'.$other_marker_re.'[ ]+
					  )
				  )
				)
			'; // mx

			# We use a different prefix before nested lists than top-level lists.
			# See extended comment in _ProcessListItems().

			if ($this->list_level) {
				$text = preg_replace_callback('{
						^
						'.$whole_list_re.'
					}mx',
					array(&$this, '_doLists_callback'), $text);
			}
			else {
				$text = preg_replace_callback('{
						(?:(?<=\n)\n|\A\n?) # Must eat the newline
						'.$whole_list_re.'
					}mx',
					array(&$this, '_doLists_callback'), $text);
			}
		}

		return $text;
	}
	function _doLists_callback($matches) {
		# Re-usable patterns to match list item bullets and number markers:
		$marker_ul_re  = '[*+-]';
		$marker_ol_re  = '\d+[\.]';
		$marker_any_re = "(?:$marker_ul_re|$marker_ol_re)";

		$list = $matches[1];
		$list_type = preg_match("/$marker_ul_re/", $matches[4]) ? "ul" : "ol";

		$marker_any_re = ( $list_type == "ul" ? $marker_ul_re : $marker_ol_re );

		$list .= "\n";
		$result = $this->processListItems($list, $marker_any_re);

		$result = $this->hashBlockElement("<$list_type>\n" . $result . "</$list_type>");
		return "\n". $result ."\n\n";
	}

	var $list_level = 0;

	function processListItems($list_str, $marker_any_re) {
	#
	#	Process the contents of a single ordered or unordered list, splitting it
	#	into individual list items.
	#
		# The $this->list_level global keeps track of when we're inside a list.
		# Each time we enter a list, we increment it; when we leave a list,
		# we decrement. If it's zero, we're not in a list anymore.
		#
		# We do this because when we're not inside a list, we want to treat
		# something like this:
		#
		#		I recommend upgrading to version
		#		8. Oops, now this line is treated
		#		as a sub-list.
		#
		# As a single paragraph, despite the fact that the second line starts
		# with a digit-period-space sequence.
		#
		# Whereas when we're inside a list (or sub-list), that line will be
		# treated as the start of a sub-list. What a kludge, huh? This is
		# an aspect of Markdown's syntax that's hard to parse perfectly
		# without resorting to mind-reading. Perhaps the solution is to
		# change the syntax rules such that sub-lists must start with a
		# starting cardinal number; e.g. "1." or "a.".

		$this->list_level++;

		# trim trailing blank lines:
		$list_str = preg_replace("/\n{2,}\\z/", "\n", $list_str);

		$list_str = preg_replace_callback('{
			(\n)?							# leading line = $1
			(^[ ]*)							# leading whitespace = $2
			('.$marker_any_re.'				# list marker and space = $3
				(?:[ ]+|(?=\n))	# space only required if item is not empty
			)
			((?s:.*?))						# list item text   = $4
			(?:(\n+(?=\n))|\n)				# tailing blank line = $5
			(?= \n* (\z | \2 ('.$marker_any_re.') (?:[ ]+|(?=\n))))
			}xm',
			array(&$this, '_processListItems_callback'), $list_str);

		$this->list_level--;
		return $list_str;
	}
	function _processListItems_callback($matches) {
		$item = $matches[4];
		$leading_line =& $matches[1];
		$leading_space =& $matches[2];
		$marker_space = $matches[3];
		$tailing_blank_line =& $matches[5];

		if ($leading_line || $tailing_blank_line ||
			preg_match('/\n{2,}/', $item))
		{
			# Replace marker with the appropriate whitespace indentation
			$item = $leading_space . str_repeat(' ', strlen($marker_space)) . $item;
			$item = $this->processBlockGamut($this->outdent($item)."\n");
		}
		else {
			# Recursion for sub-lists:
			$item = $this->doLists($this->outdent($item));
			$item = preg_replace('/\n+$/', '', $item);
			$item = $this->runSpanGamut($item);
		}

		return "<li>" . $item . "</li>\n";
	}


	function doCodeBlocks($text) {
	#
	#	Process Markdown `<pre><code>` blocks.
	#
		$text = preg_replace_callback('{
				(?:\n\n|\A\n?)
				(	            # $1 = the code block -- one or more lines, starting with a space/tab
				  (?>
					[ ]{'.self::TAB_WIDTH.'}  # Lines must start with a tab or a tab-width of spaces
					.*\n+
				  )+
				)
				((?=^[ ]{0,'.self::TAB_WIDTH.'}\S)|\Z)	# Lookahead for non-space at line-start, or end of doc
			}xm',
			array(&$this, '_doCodeBlocks_callback'), $text);

		return $text;
	}
	function _doCodeBlocks_callback($matches) {
		$codeblock = $matches[1];

		$codeblock = $this->outdent($codeblock);
		$codeblock = htmlspecialchars($codeblock, ENT_NOQUOTES);

		# trim leading newlines and trailing newlines
		$codeblock = preg_replace('/\A\n+|\n+\z/', '', $codeblock);

		$codeblock = "<pre><code>$codeblock\n</code></pre>";
		return "\n\n".$this->hashBlockElement($codeblock)."\n\n";
	}


	function makeCodeSpan($code) {
	#
	# Create a code span markup for $code. Called from handleSpanToken.
	#
		$code = htmlspecialchars(trim($code), ENT_NOQUOTES);
		return $this->hashPart("<code>$code</code>");
	}







	function doItalicsAndBold($text) {
		$token_stack = array('');
		$text_stack = array('');
		$em = '';
		$strong = '';
		$tree_char_em = false;

		while (1) {
			#
			# Get prepared regular expression for seraching emphasis tokens
			# in current context.
			#
			$token_re = $this->emAndStrongRegexList["$em$strong"];

			#
			# Each loop iteration search for the next emphasis token.
			# Each token is then passed to handleSpanToken.
			#
			$parts = preg_split($token_re, $text, 2, PREG_SPLIT_DELIM_CAPTURE);
			$text_stack[0] .= $parts[0];
			$token =& $parts[1];
			$text =& $parts[2];

			if (empty($token)) {
				# Reached end of text span: empty stack without emitting.
				# any more emphasis.
				while ($token_stack[0]) {
					$text_stack[1] .= array_shift($token_stack);
					$text_stack[0] .= array_shift($text_stack);
				}
				break;
			}

			$token_len = strlen($token);
			if ($tree_char_em) {
				# Reached closing marker while inside a three-char emphasis.
				if ($token_len == 3) {
					# Three-char closing marker, close em and strong.
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong><em>$span</em></strong>";
					$text_stack[0] .= $this->hashPart($span);
					$em = '';
					$strong = '';
				} else {
					# Other closing marker: close one em or strong and
					# change current token state to match the other
					$token_stack[0] = str_repeat($token{0}, 3-$token_len);
					$tag = $token_len == 2 ? "strong" : "em";
					$span = $text_stack[0];
					$span = $this->runSpanGamut($span);
					$span = "<$tag>$span</$tag>";
					$text_stack[0] = $this->hashPart($span);
					$$tag = ''; # $$tag stands for $em or $strong
				}
				$tree_char_em = false;
			} else if ($token_len == 3) {
				if ($em) {
					# Reached closing marker for both em and strong.
					# Closing strong marker:
					for ($i = 0; $i < 2; ++$i) {
						$shifted_token = array_shift($token_stack);
						$tag = strlen($shifted_token) == 2 ? "strong" : "em";
						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<$tag>$span</$tag>";
						$text_stack[0] .= $this->hashPart($span);
						$$tag = ''; # $$tag stands for $em or $strong
					}
				} else {
					# Reached opening three-char emphasis marker. Push on token
					# stack; will be handled by the special condition above.
					$em = $token{0};
					$strong = "$em$em";
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$tree_char_em = true;
				}
			} else if ($token_len == 2) {
				if ($strong) {
					# Unwind any dangling emphasis marker:
					if (strlen($token_stack[0]) == 1) {
						$text_stack[1] .= array_shift($token_stack);
						$text_stack[0] .= array_shift($text_stack);
					}
					# Closing strong marker:
					array_shift($token_stack);
					$span = array_shift($text_stack);
					$span = $this->runSpanGamut($span);
					$span = "<strong>$span</strong>";
					$text_stack[0] .= $this->hashPart($span);
					$strong = '';
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$strong = $token;
				}
			} else {
				# Here $token_len == 1
				if ($em) {
					if (strlen($token_stack[0]) == 1) {
						# Closing emphasis marker:
						array_shift($token_stack);
						$span = array_shift($text_stack);
						$span = $this->runSpanGamut($span);
						$span = "<em>$span</em>";
						$text_stack[0] .= $this->hashPart($span);
						$em = '';
					} else {
						$text_stack[0] .= $token;
					}
				} else {
					array_unshift($token_stack, $token);
					array_unshift($text_stack, '');
					$em = $token;
				}
			}
		}
		return $text_stack[0];
	}


	function doBlockQuotes($text) {
		$text = preg_replace_callback('/
			  (								# Wrap whole match in $1
				(?>
				  ^[ ]*>[ ]?			# ">" at the start of a line
					.+\n					# rest of the first line
				  (.+\n)*					# subsequent consecutive lines
				  \n*						# blanks
				)+
			  )
			/xm',
			array(&$this, '_doBlockQuotes_callback'), $text);

		return $text;
	}
	function _doBlockQuotes_callback($matches) {
		$bq = $matches[1];
		# trim one level of quoting - trim whitespace-only lines
		$bq = preg_replace('/^[ ]*>[ ]?|^[ ]+$/m', '', $bq);
		$bq = $this->processBlockGamut($bq);		# recurse

		$bq = preg_replace('/^/m', "  ", $bq);
		# These leading spaces cause problem with <pre> content,
		# so we need to fix that:
		$bq = preg_replace_callback('{(\s*<pre>.+?</pre>)}sx',
			array(&$this, '_doBlockQuotes_callback2'), $bq);

		return "\n". $this->hashBlockElement("<blockquote>\n$bq\n</blockquote>")."\n\n";
	}
	function _doBlockQuotes_callback2($matches) {
		$pre = $matches[1];
		$pre = preg_replace('/^  /m', '', $pre);
		return $pre;
	}


	function formParagraphs($text) {
	#
	#	Params:
	#		$text - string to process with html <p> tags
	#
		# Strip leading and trailing lines:
		$text = preg_replace('/\A\n+|\n+\z/', '', $text);

		$grafs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

		#
		# Wrap <p> tags and unhashify HTML blocks
		#
		foreach ($grafs as $key => $value) {
			if (!preg_match('/^B\x1A[0-9]+B$/', $value)) {
				# Is a paragraph.
				$value = $this->runSpanGamut($value);
				$value = preg_replace('/^([ ]*)/', "<p>", $value);
				$value .= "</p>";
				$grafs[$key] = $this->unhash($value);
			}
			else {
				# Is a block.
				# Modify elements of @grafs in-place...
				$graf = $value;
				$block = $this->htmlHashes[$graf];
				$graf = $block;
//				if (preg_match('{
//					\A
//					(							# $1 = <div> tag
//					  <div  \s+
//					  [^>]*
//					  \b
//					  markdown\s*=\s*  ([\'"])	#	$2 = attr quote char
//					  1
//					  \2
//					  [^>]*
//					  >
//					)
//					(							# $3 = contents
//					.*
//					)
//					(</div>)					# $4 = closing tag
//					\z
//					}xs', $block, $matches))
//				{
//					list(, $div_open, , $div_content, $div_close) = $matches;
//
//					# We can't call Markdown(), because that resets the hash;
//					# that initialization code should be pulled into its own sub, though.
//					$div_content = $this->hashHTMLBlocks($div_content);
//
//					# Run document gamut methods on the content.
//					foreach ($this->documentGamut as $method => $priority) {
//						$div_content = $this->$method($div_content);
//					}
//
//					$div_open = preg_replace(
//						'{\smarkdown\s*=\s*([\'"]).+?\1}', '', $div_open);
//
//					$graf = $div_open . "\n" . $div_content . "\n" . $div_close;
//				}
				$grafs[$key] = $graf;
			}
		}

		return implode("\n\n", $grafs);
	}


	function encodeAttribute($text) {
	#
	# Encode text for a double-quoted HTML attribute. This function
	# is *not* suitable for attributes enclosed in single quotes.
	#
		$text = $this->encodeAmpsAndAngles($text);
		$text = str_replace('"', '&quot;', $text);
		return $text;
	}


	function encodeAmpsAndAngles($text) {
	#
	# Smart processing for ampersands and angle brackets that need to
	# be encoded. Valid character entities are left alone unless the
	# no-entities mode is set.
	#
		if ($this->disabledEntities) {
			$text = str_replace('&', '&amp;', $text);
		} else {
			# Ampersand-encoding based entirely on Nat Irons's Amputator
			# MT plugin: <http://bumppo.net/projects/amputator/>
			$text = preg_replace('/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/',
								'&amp;', $text);;
		}
		# Encode remaining <'s
		$text = str_replace('<', '&lt;', $text);

		return $text;
	}


	function doAutoLinks($text) {
		$text = preg_replace_callback('{<((https?|ftp|dict):[^\'">\s]+)>}i',
			array(&$this, '_doAutoLinks_url_callback'), $text);

		# Email addresses: <address@domain.foo>
		$text = preg_replace_callback('{
			<
			(?:mailto:)?
			(
				(?:
					[-!#$%&\'*+/=?^_`.{|}~\w\x80-\xFF]+
				|
					".*?"
				)
				\@
				(?:
					[-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
				|
					\[[\d.a-fA-F:]+\]	# IPv4 & IPv6
				)
			)
			>
			}xi',
			array(&$this, '_doAutoLinks_email_callback'), $text);

		return $text;
	}
	function _doAutoLinks_url_callback($matches) {
		$url = $this->encodeAttribute($matches[1]);
		$link = "<a href=\"$url\">$url</a>";
		return $this->hashPart($link);
	}
	function _doAutoLinks_email_callback($matches) {
		$address = $matches[1];
		$link = $this->encodeEmailAddress($address);
		return $this->hashPart($link);
	}


	function encodeEmailAddress($addr) {
	#
	#	Input: an email address, e.g. "foo@example.com"
	#
	#	Output: the email address as a mailto link, with each character
	#		of the address encoded as either a decimal or hex entity, in
	#		the hopes of foiling most address harvesting spam bots. E.g.:
	#
	#	  <p><a href="&#109;&#x61;&#105;&#x6c;&#116;&#x6f;&#58;&#x66;o&#111;
	#        &#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;&#101;&#46;&#x63;&#111;
	#        &#x6d;">&#x66;o&#111;&#x40;&#101;&#x78;&#97;&#x6d;&#112;&#x6c;
	#        &#101;&#46;&#x63;&#111;&#x6d;</a></p>
	#
	#	Based by a filter by Matthew Wickline, posted to BBEdit-Talk.
	#   With some optimizations by Milian Wolff.
	#
		$addr = "mailto:" . $addr;
		$chars = preg_split('/(?<!^)(?!$)/', $addr);
		$seed = (int)abs(crc32($addr) / strlen($addr)); # Deterministic seed.

		foreach ($chars as $key => $char) {
			$ord = ord($char);
			# Ignore non-ascii chars.
			if ($ord < 128) {
				$r = ($seed * (1 + $key)) % 100; # Pseudo-random function.
				# roughly 10% raw, 45% hex, 45% dec
				# '@' *must* be encoded. I insist.
				if ($r > 90 && $char != '@') /* do nothing */;
				else if ($r < 45) $chars[$key] = '&#x'.dechex($ord).';';
				else              $chars[$key] = '&#'.$ord.';';
			}
		}

		$addr = implode('', $chars);
		$text = implode('', array_slice($chars, 7)); # text without `mailto:`
		$addr = "<a href=\"$addr\">$text</a>";

		return $addr;
	}


	function parseSpan($str) {
	#
	# Take the string $str and parse it into tokens, hashing embeded HTML,
	# escaped characters and handling code spans.
	#
		$output = '';

		$span_re = '{
				(
					\\\\'.$this->escapeCharsRegex.'
				|
					(?<![`\\\\])
					`+						# code span marker
			'.( $this->disabledHtml ? '' : '
				|
					<!--    .*?     -->		# comment
				|
					<\?.*?\?> | <%.*?%>		# processing instruction
				|
					<[/!$]?[-a-zA-Z0-9:_]+	# regular tags
					(?>
						\s
						(?>[^"\'>]+|"[^"]*"|\'[^\']*\')*
					)?
					>
			').'
				)
				}xs';

		while (1) {
			#
			# Each loop iteration seach for either the next tag, the next
			# openning code span marker, or the next escaped character.
			# Each token is then passed to handleSpanToken.
			#
			$parts = preg_split($span_re, $str, 2, PREG_SPLIT_DELIM_CAPTURE);

			# Create token from text preceding tag.
			if ($parts[0] != "") {
				$output .= $parts[0];
			}

			# Check if we reach the end.
			if (isset($parts[1])) {
				$output .= $this->handleSpanToken($parts[1], $parts[2]);
				$str = $parts[2];
			}
			else {
				break;
			}
		}

		return $output;
	}


	function handleSpanToken($token, &$str) {
	#
	# Handle $token provided by parseSpan by determining its nature and
	# returning the corresponding value that should replace it.
	#
		switch ($token{0}) {
			case "\\":
				return $this->hashPart("&#". ord($token{1}). ";");
			case "`":
				# Search for end marker in remaining text.
				if (preg_match('/^(.*?[^`])'.preg_quote($token).'(?!`)(.*)$/sm',
					$str, $matches))
				{
					$str = $matches[2];
					$codespan = $this->makeCodeSpan($matches[1]);
					return $this->hashPart($codespan);
				}
				return $token; // return as text since no ending marker found.
			default:
				return $this->hashPart($token);
		}
	}


	function outdent($text) {
	#
	# Remove one level of line-leading tabs or spaces
	#
		return preg_replace('/^(\t|[ ]{1,'.self::TAB_WIDTH.'})/m', '', $text);
	}
}