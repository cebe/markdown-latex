<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\latex;
use cebe\markdown\block\TableTrait;

/**
 * Markdown parser for github flavored markdown.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class GithubMarkdown extends Markdown
{
	use TableTrait;

	/**
	 * @var boolean whether to interpret newlines as `<br />`-tags.
	 * This feature is useful for comments where newlines are often meant to be real new lines.
	 */
	public $enableNewlines = false;

	/**
	 * @inheritDoc
	 */
	protected $escapeCharacters = [
		// from Markdown
		'\\', // backslash
		'`', // backtick
		'*', // asterisk
		'_', // underscore
		'{', '}', // curly braces
		'[', ']', // square brackets
		'(', ')', // parentheses
		'#', // hash mark
		'+', // plus sign
		'-', // minus sign (hyphen)
		'.', // dot
		'!', // exclamation mark
		'<', '>',
		// added by GithubMarkdown
		':', // colon
		'|', // pipe
	];

	/**
	 * @inheritDoc
	 */
	protected function inlineMarkers()
	{
		return parent::inlineMarkers() + [
			'http'  => 'parseUrl',
			'ftp'   => 'parseUrl',
			'~~'    => 'parseStrike',
			'|'     => 'parseTd',
		];
	}


	// block parsing


	/**
	 * @inheritDoc
	 */
	protected function identifyLine($lines, $current)
	{
		if (isset($lines[$current]) && (strncmp($lines[$current], '```', 3) === 0 || strncmp($lines[$current], '~~~', 3) === 0)) {
			return 'fencedCode';
		}
		if ($this->identifyTable($lines, $current)) {
			return 'table';
		}
		return parent::identifyLine($lines, $current);
	}

	/**
	 * Consume lines for a fenced code block
	 */
	protected function consumeFencedCode($lines, $current)
	{
		// consume until ```
		$block = [
			'type' => 'code',
			'content' => [],
		];
		$line = rtrim($lines[$current]);
		$fence = substr($line, 0, $pos = strrpos($line, $line[0]) + 1);
		$language = substr($line, $pos);
		if (!empty($language)) {
			$block['language'] = $language;
		}
		for ($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			if (rtrim($line = $lines[$i]) !== $fence) {
				$block['content'][] = $line;
			} else {
				break;
			}
		}
		return [$block, $i];
	}


	// inline parsing


	/**
	 * Parses the strikethrough feature.
	 */
	protected function parseStrike($markdown)
	{
		if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
			return [
				'\sout{' . $this->parseInline($matches[1]) . '}',
				strlen($matches[0])
			];
		}
		return [$markdown[0] . $markdown[1], 2];
	}

	/**
	 * Parses urls and adds auto linking feature.
	 */
	protected function parseUrl($markdown)
	{
		$pattern = <<<REGEXP
			/(?(R) # in case of recursion match parentheses
				 \(((?>[^\s()]+)|(?R))*\)
			|      # else match a link with title
				^(https?|ftp):\/\/(([^\s()]+)|(?R))+(?<![\.,:;\'"!\?\s])
			)/x
REGEXP;

		if (!in_array('parseLink', $this->context) && preg_match($pattern, $markdown, $matches)) {
			return [
				'\url{' . $matches[0] . '}',
				strlen($matches[0])
			];
		}
		return [substr($markdown, 0, 4), 4];
	}

	/**
	 * @inheritdocs
	 *
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function parsePlainText($text)
	{
		if ($this->enableNewlines) {
			return preg_replace("/(  \n|\n)/", '\\\\\\\\', $this->escapeLatex($text));
		} else {
			return parent::parsePlainText($text);
		}
	}

	private $_tableCellHead = false;

	protected function renderTable($block)
	{
		$align = [];
		foreach($block['cols'] as $col) {
			if (empty($col)) {
				$align[] = 'l';
			} else {
				$align[] = $col[0];
			}
		}
		$align = implode('|', $align);

		$content = '';
		$first = true;
		foreach($block['rows'] as $row) {
			$this->_tableCellHead = $first;
			$content .= $this->parseInline($row) . "\\\\ \\hline\n";
			$first = false;
		}
		return "\n\n\\noindent\\begin{tabular}{|$align|}\\hline\n$content\\end{tabular}\n";
	}

	protected function parseTd($markdown)
	{
		if ($this->context[1] === 'table') {
			return ["&", 1];
		}
		return [$markdown[0], 1];
	}
}
