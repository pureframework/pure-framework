<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Application-facing facade for HTML output and common HTTP page outcomes.
 * Production apps define PURE_LAYOUT_PATH (required) and PURE_HTDOCS_PATH (optional).
 * Tests may call configure() to override paths without constants.
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Display
{
	private static ?Layout $layout = null;
	private static ?string $layoutPathOverride = null;
	private static ?string $htdocsPathOverride = null;

	/**
	 * Override layout/htdocs paths (tests). Pass null to reset to constants.
	 */
	public static function configure(?string $layoutPath, ?string $htdocsPath = null): void
	{
		self::$layoutPathOverride = $layoutPath !== null
			? rtrim($layoutPath, DIRECTORY_SEPARATOR)
			: null;
		self::$htdocsPathOverride = $htdocsPath !== null
			? rtrim($htdocsPath, DIRECTORY_SEPARATOR)
			: null;
		self::$layout = null;
	}

	public static function reset(): void
	{
		self::configure(null, null);
	}

	/**
	 * Render a co-located template inside the site layout.
	 *
	 * @param string|null $layoutVariant When set, uses site.layout-{variant}.php
	 */
	public static function page(string $template, ?array $vars = null, ?string $layoutVariant = null): void
	{
		$layout = null;
		if ($layoutVariant !== null) {
			$layout = 'site.layout-' . $layoutVariant . '.php';
		}

		self::layout()->template($template, $vars, $layout);
	}

	public static function template(string $file, ?array $vars = null): void
	{
		$template = new Template($file, $vars ?? []);
		$template->display();
	}

	public static function fetchTemplate(string $file, ?array $vars = null): string
	{
		$template = new Template($file, $vars ?? []);
		return $template->fetch();
	}

	public static function partial(string $name, ?array $vars = null, mixed $parent = null): void
	{
		self::layout()->partial(self::normalizePartialName($name), $vars, $parent)->display();
	}

	public static function fetchPartial(string $name, ?array $vars = null, mixed $parent = null): string
	{
		return self::layout()->partial(self::normalizePartialName($name), $vars, $parent)->fetch();
	}

	public static function redirect(string $url, int $status = 302): never
	{
		header('Location: ' . $url, true, $status);
		exit;
	}

	public static function notFound(?string $handler404 = null): never
	{
		$file = self::resolve404HandlerFile($handler404);
		if ($file !== null) {
			HttpResponse::status(404);
			require $file;
			exit;
		}

		HttpResponse::status(404);
		echo '<h1>404 — Not found</h1>';
		exit;
	}

	/**
	 * Closest 404.php wins: explicit path, then walk up from cwd, then htdocs root.
	 */
	private static function resolve404HandlerFile(?string $explicit = null): ?string
	{
		if ($explicit !== null && is_file($explicit)) {
			return $explicit;
		}

		$cwd = Util::path(getcwd());
		$parts = explode(DIRECTORY_SEPARATOR, $cwd);
		while (count($parts) > 0) {
			$file = Util::path($parts, '404.php');
			if (is_file($file)) {
				return $file;
			}
			array_pop($parts);
		}

		$htdocsPath = self::resolveHtdocsPath();
		if ($htdocsPath !== null) {
			$htdocs404 = Util::path($htdocsPath, '404.php');
			if (is_file($htdocs404)) {
				return $htdocs404;
			}
		}

		return null;
	}

	public static function layout(): Layout
	{
		if (self::$layout === null) {
			self::$layout = new Layout(self::resolveLayoutPath());
		}
		return self::$layout;
	}

	private static function resolveLayoutPath(): string
	{
		if (self::$layoutPathOverride !== null) {
			return self::$layoutPathOverride;
		}
		if (!defined('PURE_LAYOUT_PATH') || PURE_LAYOUT_PATH === '') {
			throw new \RuntimeException('PURE_LAYOUT_PATH is not defined');
		}
		return rtrim(PURE_LAYOUT_PATH, DIRECTORY_SEPARATOR);
	}

	private static function resolveHtdocsPath(): ?string
	{
		if (self::$htdocsPathOverride !== null) {
			return self::$htdocsPathOverride;
		}
		if (defined('PURE_HTDOCS_PATH') && PURE_HTDOCS_PATH !== '') {
			return rtrim(PURE_HTDOCS_PATH, DIRECTORY_SEPARATOR);
		}
		return null;
	}

	private static function normalizePartialName(string $name): string
	{
		if (!str_ends_with($name, '.php')) {
			return $name . '.php';
		}
		return $name;
	}
}
