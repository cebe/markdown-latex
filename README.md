markdown-latex
==============

A markdown parser for converting markdown to LaTeX written in PHP.

Implementation based on [cebe/markdown][].

[cebe/markdown]: https://github.com/cebe/markdown "A markdown parser for PHP"


Installation
------------

PHP 5.4 or higher is required to use it.

Installation is recommended to be done via [composer][] by adding the following to the `require` section in your `composer.json`:

```json
"cebe/markdown-latex": "*"
```

Run `composer update` afterwards.

[composer]: https://getcomposer.org/ "The PHP package manager"

Usage
-----

### In your PHP project

To use the parser as is, you just create an instance of a provided flavor class and call the `parse()`-
or `parseParagraph()`-method:

```php
// default markdown and parse full text
$parser = new \cebe\markdown\latex\Markdown();
$parser->parse($markdown);

// use github
$parser = new \cebe\markdown\latex\GithubMarkdown();
$parser->parse($markdown);

// parse only inline elements (useful for one-line descriptions)
$parser = new \cebe\markdown\latex\GithubMarkdown();
$parser->parseParagraph($markdown);
```

### The command line script

You can use it to render this readme:

    bin/markdown-latex README.md > output.tex

Using github flavored markdown:

    bin/markdown-latex --flavor=gfm README.md > output.tex

or convert the original markdown description to html using the unix pipe:

    curl http://daringfireball.net/projects/markdown/syntax.text | bin/markdown-latex > output.tex

To create a latex document you have to include the generated latex source in a latex document `main.tex`:

```tex
\documentclass[a4paper, 12pt]{article}

% english and utf8
\usepackage[british]{babel}
\usepackage[utf8]{inputenc}

% url support
\usepackage{url}

% make links clickable
\usepackage{hyperref}

% code listings
\usepackage{listings}

% include images
\usepackage{graphicx}

% support github markdown strikethrough
% http://tex.stackexchange.com/questions/23711/strikethrough-text
\usepackage{ulem}

\begin{document}

	\include{output.tex}

\end{document}
```

make a PDF with `pdflatex main.tex`.