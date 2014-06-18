<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\latex;

// work around https://github.com/facebook/hhvm/issues/1120
use MikeVanRiel\TextToLatex;

defined('ENT_HTML401') || define('ENT_HTML401', 0);

/**
 * Markdown parser for the [initial markdown spec](http://daringfireball.net/projects/markdown/syntax).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Markdown extends \cebe\markdown\Markdown
{
	/**
	 * @var boolean This option has no effect in LaTeX Markdown.
	 */
	public $html5 = false;
	/**
	 * @var boolean This option has no effect in LaTeX Markdown.
	 */
	public $keepListStartNumber = false;
	/**
	 * @var string this string will be prefixed to all auto generated labels.
	 * This can be used to disambiguate labels when combining multiple markdown files into one document.
	 */
	public $labelPrefix;


	/**
	 * @inheritDoc
	 */
	protected function inlineMarkers()
	{
		return [
			'&'     => 'parseEntity',
			'!['    => 'parseImage',
			'*'     => 'parseEmphStrong',
			'_'     => 'parseEmphStrong',
			'<'     => 'parseLt',
			'['     => 'parseLink',
			'\\'    => 'parseEscape',
			'`'     => 'parseCode',
		];
	}


	// rendering


	/**
	 * Render a paragraph block
	 *
	 * @param $block
	 * @return string
	 */
	protected function renderParagraph($block)
	{
		return $this->parseInline(implode("\n", $block['content'])) . "\n";
	}

	/**
	 * Renders a blockquote
	 */
	protected function renderQuote($block)
	{
		return '\begin{quote}' . $this->parseBlocks($block['content']) . '\end{quote}';
	}

	/**
	 * Renders a code block
	 */
	protected function renderCode($block)
	{
		$language = isset($block['language']) ? ' \lstset{language=' . $block['language'] . '} ' : '\lstset{language={}}';
		return $language . '\begin{lstlisting}' . "\n" . implode("\n", $block['content']) . "\n" . '\end{lstlisting}';
	}

	/**
	 * Renders a list
	 */
	protected function renderList($block)
	{
		$type = $block['list'];
		if ($type === 'ol') {
			$type = 'enumerate';
		} else {
			$type = 'itemize';
		}

		$output = '\begin{' . $type . '}' . "\n";
		foreach ($block['items'] as $item => $itemLines) {
			$output .= '\item ';
			// TODO treat lazy lists correctly
			if (!isset($block['lazyItems'][$item])) {
				$firstPar = [];
				while (!empty($itemLines) && rtrim($itemLines[0]) !== '' && $this->identifyLine($itemLines, 0) === 'paragraph') {
					$firstPar[] = array_shift($itemLines);
				}
				$output .= $this->parseInline(implode("\n", $firstPar));
			}
			if (!empty($itemLines)) {
				$output .= $this->parseBlocks($itemLines);
			}
			$output .= "\n";
		}
		return $output . '\end{' . $type . '}';
	}

	/**
	 * Renders a headline
	 */
	protected function renderHeadline($block)
	{
		$content = $this->parseInline($block['content']);
		switch($block['level']) {
			case 1: return '\section{' . $content . '}';
			case 2: return '\subsection{' . $content . '}';
			case 3: return '\subsubsection{' . $content . '}';
			default: return '\paragraph{' . $content . '}';
		}
	}

	/**
	 * Renders an HTML block
	 */
	protected function renderHtml($block)
	{
		// TODO obviously does not work with latex
		return '\fbox{NOT PARSEABLE HTML BLOCK}'; // implode("\n", $block['content']);
	}

	/**
	 * Renders a horizontal rule
	 */
	protected function renderHr($block)
	{
		return "\n\\noindent\\rule{\\textwidth}{0.4pt}\n";
	}


	// inline parsing


	/**
	 * Parses escaped special characters.
	 */
	protected function parseEscape($text)
	{
		if (isset($text[1]) && in_array($text[1], $this->escapeCharacters)) {
			return [$text[1], 2];
		}
		return ['\\textbackslash{}', 1];
	}

	/**
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function parseNewline($text)
	{
		return [
			'\\\\',
			3
		];
	}

	/**
	 * Parses an & or a html entity definition.
	 */
	protected function parseEntity($text)
	{
		// TODO obviously does not work with latex

		// html entities e.g. &copy; &#169; &#x00A9;
		if (preg_match('/^&#?[\w\d]+;/', $text, $matches)) {
			return [str_replace('#', '\\#', '\\' . $matches[0]), strlen($matches[0])];
		} else {
			return ['\&', 1];
		}
	}

	/**
	 * Parses inline HTML.
	 */
	protected function parseLt($text)
	{
		if (strpos($text, '>') !== false) {
			// convert a name markers to \labels
			if (preg_match('~^<a name="(.*?)">.*?</a>~i', $text, $matches)) {
				return ['\label{' . str_replace('#', '::', $this->labelPrefix . $matches[1]) . "}", strlen($matches[0])];
			}
			// email address
			if (preg_match('/^<([^\s]*?@[^\s]*?\.\w+?)>/', $text, $matches)) {
				$email = htmlspecialchars($matches[1], ENT_NOQUOTES, 'UTF-8');
				return [
					'\href{mailto:' . $email . '}{' . $this->escapeLatex($email) . '}',
					strlen($matches[0])
				];
			} elseif (preg_match('/^<([a-z]{3,}:\/\/[^\s]+?)>/', $text, $matches)) {
				// URL
				return ['\url{' . $this->escapeUrl($matches[1]) . '}', strlen($matches[0])];
			} elseif (preg_match('~^</?(\w+\d?)( .*?)?>~', $text, $matches)) {
				// HTML tags
				return [$this->escapeLatex($matches[0]), strlen($matches[0])];
			} elseif (preg_match('~^<!--(.*?)-->~', $text, $matches)) {
				// HTML comments to LaTeX comments
				return ['% ' . $matches[1] . "\n", strlen($matches[0])];
			}
		}
		return ['<', 1];
	}

	/**
	 * Parses a link indicated by `[`.
	 */
	protected function parseLink($markdown)
	{
		if (($parts = $this->parseLinkOrImage($markdown)) !== false) {
			list($text, $url, $title, $offset) = $parts;

			if (strpos($url, '://') === false) {
				// consider all non absolute links as relative in the document
				// $title is ignored in this case.
				if ($url[0] === '#') {
					$url = $this->labelPrefix . $url;
				}
				$link = '\hyperref['.str_replace('#', '::', $url).']{' . $this->parseInline($text) . '}';
			} else {
				$link = $this->parseInline($text) . '\\footnote{' . (empty($title) ? '' : $this->escapeLatex($title) . ': ') . '\url{' . $this->escapeUrl($url) . '}}';
			}

			return [$link, $offset];
		} else {
			// remove all starting [ markers to avoid next one to be parsed as link
			$result = '[';
			$i = 1;
			while (isset($markdown[$i]) && $markdown[$i] == '[') {
				$result .= '[';
				$i++;
			}
			return [$result, $i];
		}
	}

	/**
	 * Parses an image indicated by `![`.
	 */
	protected function parseImage($markdown)
	{
		if (($parts = $this->parseLinkOrImage(substr($markdown, 1))) !== false) {
			list($text, $url, $title, $offset) = $parts;

			// TODO create figure with caption with title
			$image = '\includegraphics[width=\textwidth]{' . $url . '}';
//			$image = '<img src="' . htmlspecialchars($url, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
//				. ' alt="' . htmlspecialchars($text, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
//				. (empty($title) ? '' : ' title="' . htmlspecialchars($title, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"')
//				. ($this->html5 ? '>' : ' />');

			return [$image, $offset + 1];
		} else {
			// remove all starting [ markers to avoid next one to be parsed as link
			$result = '!';
			$i = 1;
			while (isset($markdown[$i]) && $markdown[$i] == '[') {
				$result .= '[';
				$i++;
			}
			return [$result, $i];
		}
	}

	/**
	 * Parses an inline code span `` ` ``.
	 */
	protected function parseCode($text)
	{
		if (preg_match('/^(``+)\s(.+?)\s\1/s', $text, $matches)) { // code with enclosed backtick
			return [
				'\\lstinline|' . str_replace("\n", ' ', $matches[2]) . '|',
				strlen($matches[0])
			];
		} elseif (preg_match('/^`(.+?)`/s', $text, $matches)) {
			return [
				'\\lstinline|' . str_replace("\n", ' ', $matches[1]) . '|',
				strlen($matches[0])
			];
		}
		return [$text[0], 1];
	}

	/**
	 * Parses empathized and strong elements.
	 */
	protected function parseEmphStrong($text)
	{
		// TODO check http://tex.stackexchange.com/questions/41681/correct-way-to-bold-italicize-text
		$marker = $text[0];

		if (!isset($text[1])) {
			return [$text[0], 1];
		}

		if ($marker == $text[1]) { // strong
			if ($marker == '*' && preg_match('/^[*]{2}((?:[^*]|[*][^*]*[*])+?)[*]{2}(?![*])/s', $text, $matches) ||
				$marker == '_' && preg_match('/^__((?:[^_]|_[^_]*_)+?)__(?!_)/us', $text, $matches)) {

				return ['\textbf{' . $this->parseInline($matches[1]) . '}', strlen($matches[0])];
			}
		} else { // emph
			if ($marker == '*' && preg_match('/^[*]((?:[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s', $text, $matches) ||
				$marker == '_' && preg_match('/^_((?:[^_]|__[^_]*__)+?)_(?!_)\b/us', $text, $matches)) {
				return ['\textit{' . $this->parseInline($matches[1]) . '}', strlen($matches[0])];
			}
		}
		return [$text[0] == '_' ? '\\_' : $text[0], 1];
	}

	private $_escaper;


	protected function escapeUrl($string)
	{
		return str_replace('%', '\\%', $this->escapeLatex($string));
	}

	protected function escapeLatex($string)
	{
		if ($this->_escaper === null) {
			$this->_escaper = new TextToLatex();
		}
		return $this->_escaper->convert($string);
	}

	/**
	 * @inheritdocs
	 *
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function parsePlainText($text)
	{
		return str_replace("  \n", '\\\\', $this->escapeLatex($text));
	}
}
