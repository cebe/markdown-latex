<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\latex;

use MikeVanRiel\TextToLatex;

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
	public $labelPrefix = '';


	/**
	 * Render a paragraph block
	 *
	 * @param $block
	 * @return string
	 */
	protected function renderParagraph($block)
	{
		return $this->renderAbsy($block['content']) . "\n\n";
	}

	/**
	 * Renders a blockquote
	 */
	protected function renderQuote($block)
	{
		return '\begin{quote}' . $this->renderAbsy($block['content']) . "\\end{quote}\n";
	}

	/**
	 * Renders a code block
	 */
	protected function renderCode($block)
	{
		$language = isset($block['language']) ? "\\lstset{language={$block['language']}}" : '\lstset{language={}}';
		return "$language\\begin{lstlisting}\n{$block['content']}\n\\end{lstlisting}\n";
	}

	/**
	 * Renders a list
	 */
	protected function renderList($block)
	{
		$type = ($block['list'] === 'ol') ? 'enumerate' : 'itemize';
		$output = "\\begin{{$type}}\n";

		foreach ($block['items'] as $item => $itemLines) {
			$output .= '\item ' . $this->renderAbsy($itemLines). "\n";
		}

		return "$output\\end{{$type}}\n";
	}

	/**
	 * Renders a headline
	 */
	protected function renderHeadline($block)
	{
		$content = $this->renderAbsy($block['content']);
		switch($block['level']) {
			case 1: return "\\section{{$content}}\n";
			case 2: return "\\subsection{{$content}}\n";
			case 3: return "\\subsubsection{{$content}}\n";
			default: return "\\paragraph{{$content}}\n";
		}
	}

	/**
	 * Renders an HTML block
	 */
	protected function renderHtml($block)
	{
		// TODO obviously does not work with latex
		return "\\fbox{NOT PARSEABLE HTML BLOCK}\n"; // implode("\n", $block['content']);
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
	 * @inheritdoc
	 */
	protected function renderLink($markdown)
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				return $block['orig'];
			}
		}

		$url = $block['url'];
		$text = $this->renderAbsy($block['text']);
		if (strpos($url, '://') === false) {
			// consider all non absolute links as relative in the document
			// $title is ignored in this case.
			if ($url[0] === '#') {
				$url = $this->labelPrefix . $url;
			}
			return '\hyperref['.str_replace('#', '::', $url).']{' . $text . '}';
		} else {
			return $text . '\\footnote{' . (empty($block['title']) ? '' : $this->escapeLatex($block['title']) . ': ') . '\url{' . $this->escapeUrl($url) . '}}';
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function renderImage($block)
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				return $block['orig'];
			}
		}

		// TODO create figure with caption with title
		$url = $block['url'];
		return '\includegraphics[width=\textwidth]{' . $this->escapeUrl($url) . '}';
	}

	/**
	 * @inheritdoc
	 */
	protected function renderInlineCode($block)
	{
		return '\\lstinline|' . str_replace("\n", ' ', $block[1]) . '|';
	}

	/**
	 * @inheritdoc
	 */
	protected function renderStrong($block)
	{
		return '\textbf{' . $this->renderAbsy($block[1]) . '}';
	}

	/**
	 * @inheritdoc
	 */
	protected function renderEmph($block)
	{
		return '\textit{' . $this->renderAbsy($block[1]) . '}';
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
	protected function renderText($text)
	{
		return str_replace("  \n", "\\\\\n", $this->escapeLatex($text[1]));
	}
}
