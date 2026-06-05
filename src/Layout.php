<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Composes site layout templates (header, content, footer).
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Layout
{
	private string $layoutPath;
	private string $defaultLayoutFile;
	private string $headerFile;
	private string $footerFile;
	private string $partialPath;

	public function __construct(string $layoutPath)
	{
		$this->layoutPath = rtrim($layoutPath, DIRECTORY_SEPARATOR);
		$this->defaultLayoutFile = $this->layoutPath . DIRECTORY_SEPARATOR . 'site.layout.php';
		$this->headerFile = $this->layoutPath . DIRECTORY_SEPARATOR . 'site.header.php';
		$this->footerFile = $this->layoutPath . DIRECTORY_SEPARATOR . 'site.footer.php';
		$this->partialPath = $this->layoutPath . DIRECTORY_SEPARATOR . 'partials';
	}

	public function setDefaultLayoutFile(string $path): void
	{
		$this->defaultLayoutFile = $path;
	}

	public function setHeaderAndFooterFiles(string $headerFile, string $footerFile): void
	{
		$this->headerFile = $headerFile;
		$this->footerFile = $footerFile;
	}

	public function setPartialPath(string $partialPath): void
	{
		$this->partialPath = $partialPath;
	}

	public function template(string|Template $template, ?array $vars = null, ?string $layout = null): void
	{
		if ($vars === null) {
			$vars = [];
		}

		if ($template instanceof Template) {
			$content = $template;
		} else {
			$content = new Template($template, $vars);
		}

		if ($layout === null) {
			$layout = $this->defaultLayoutFile;
		} else {
			$layout = Util::path($this->layoutPath, $layout);
		}
		$vars['_layoutFile'] = $layout;

		$rendered = $content->fetch();
		$contentVars = $content->getVars() ?? [];
		$vars = array_merge($vars, $contentVars);

		$l = new Template($layout, $vars);
		$l->set('header', new Template($this->headerFile, $vars));
		$l->set('content', $rendered);
		$l->set('footer', new Template($this->footerFile, $vars));
		$l->display();
	}

	public function partial(string $partialFile, ?array $vars = null, mixed $parent = null): Template
	{
		if ($vars === null) {
			$vars = [];
		}
		if ($parent !== null) {
			$vars['site'] = $parent;
		}
		return new Template($this->buildPartialPath($partialFile), $vars);
	}

	private function buildPartialPath(string $partial): string
	{
		return Util::path($this->partialPath, $partial);
	}
}
