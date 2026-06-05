<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * URL router: map regex routes to handler files.
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Router
{
	protected $_debug = false;
	private $routes = [];
	/** @var string|callable|null */
	private $notFoundHandler = null;
	private bool $notFoundHandlerDisabled = false;

	/**
	 * Toggle debug mode for run(). Core Router does not emit output; subclass debugRequest().
	 */
	public function debug($enabled = false)
	{
		$this->_debug = $enabled;
	}

	/**
	 * Hook called at the start of run() when debug is enabled. Override in a subclass for dev tooling.
	 */
	protected function debugRequest($request, $routes)
	{
	}

	public function route(): void
	{
		$route = new Route(...func_get_args());
		$this->routes[] = $route;
	}

	/**
	 * Handler when no route matches: path to a PHP file (same include/chdir as routes) or callable(Request).
	 * Pass null to restore the framework default (Display::notFound()).
	 * Pass false to disable not-found handling (run() returns false when nothing matches).
	 */
	public function setNotFoundHandler(string|callable|null|false $handler): void
	{
		if ($handler === false) {
			$this->notFoundHandlerDisabled = true;
			$this->notFoundHandler = null;
			return;
		}

		$this->notFoundHandlerDisabled = false;
		$this->notFoundHandler = $handler;
	}

	/**
	 * @return mixed|false Matched or custom not-found handler return value; false when not-found handling is disabled
	 */
	public function run(?Request $request = null): mixed
	{
		if ($request === null) {
			$request = new Request();
		}

		if ($this->_debug) {
			$this->debugRequest($request, $this->routes);
		}

		// Execute the routes in the order that they were added to the router
		// First one that matches is what gets served
		foreach ($this->routes as $index => $route) {
			if ($route->matches($request)) {
				return $route->executeHandler($request);
			}
		}

		if ($this->notFoundHandlerDisabled) {
			return false;
		}

		if ($this->notFoundHandler !== null) {
			return $this->executeNotFoundHandler($request);
		}

		$this->executeDefaultNotFoundHandler($request);
	}

	/**
	 * Include a handler file with the same chdir/globals semantics as Route::executeHandler().
	 */
	public static function includeHandlerFile(string $handler, Request $request, ?Route $route = null): mixed
	{
		$HANDLER = $handler;
		$REQUEST = $request;
		unset($request);
		$ROUTE = $route;

		$__pureRouterFullHandlerPath = Util::path(getcwd(), $handler);
		$__pureRouterCwd = getcwd();
		chdir(dirname($handler));
		$ret = include($__pureRouterFullHandlerPath);
		chdir($__pureRouterCwd);

		return $ret;
	}

	private function executeNotFoundHandler(Request $request): mixed
	{
		$handler = $this->notFoundHandler;

		if (is_string($handler)) {
			return static::includeHandlerFile($handler, $request, null);
		}

		$REQUEST = $request;
		$ROUTE = null;
		$HANDLER = null;
		unset($request);

		return $handler($REQUEST);
	}

	/**
	 * Default when no route matches and no custom setNotFoundHandler() is registered.
	 */
	protected function executeDefaultNotFoundHandler(Request $request): never
	{
		$REQUEST = $request;
		$ROUTE = null;
		$HANDLER = null;
		unset($request);

		Display::notFound();
	}
}

class Route
{
	public ?array $methods = null;
	public ?string $url = null;
	public ?array $params = null;
	public ?string $handler = null;

	private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'QUERY'];

	/**
	 * route('^/path', 'handler.php')
	 * route('POST', '^/path', 'handler.php')
	 * route('POST', '^/path/([^/]+)$', ['id'], 'handler.php')
	 */
	public function __construct(...$args)
	{
		$args = func_get_args();

		if (isset($args[0]) && is_array($args[0])) {
			trigger_error(
				'Associative route(...) definitions and URL array patterns are no longer supported. '
				. 'Use route($pattern, $handler) or route($method, $pattern, $handler).',
				E_USER_DEPRECATED
			);
			throw new \InvalidArgumentException(
				'Invalid route definition. Supported forms: '
				. 'route(string $pattern, string $handler), '
				. 'route(string $method, string $pattern, string $handler), '
				. 'route(string $method, string $pattern, array $paramNames, string $handler).'
			);
		}

		$index = 0;

		if (isset($args[$index]) && is_string($args[$index]) && in_array(strtoupper($args[$index]), self::HTTP_METHODS, true)) {
			$this->methods = [strtoupper($args[$index])];
			$index++;
		}

		if (!isset($args[$index]) || !is_string($args[$index])) {
			throw new \InvalidArgumentException('Route requires a URL pattern string.');
		}
		$this->url = $args[$index];
		$index++;

		if (isset($args[$index]) && is_array($args[$index])) {
			$this->params = $args[$index];
			$index++;
		}

		if (!isset($args[$index]) || !is_string($args[$index])) {
			throw new \InvalidArgumentException('Route requires a handler file path string.');
		}
		$this->handler = $args[$index];
	}

	public function matches(Request $request): bool
	{
		if (is_array($this->methods) && !in_array($request->method(), $this->methods, true)) {
			return false;
		}

		return $this->matchesUrl($request->url());
	}

	private function matchesUrl(string $url): bool
	{
		if ($this->url === null) {
			return true;
		}

		if (str_starts_with($this->url, '^')) {
			$pattern = str_replace('/', '\/', $this->url);

			return preg_match('/' . $pattern . '/', $url) === 1;
		}

		return $this->url === $url;
	}

	public function extractParams(Request $request): array
	{
		if ($this->url === null || !is_array($this->params) || $this->params === [] || !str_starts_with($this->url, '^')) {
			return [];
		}

		$pattern = '/' . str_replace('/', '\/', $this->url) . '/';
		if (!preg_match($pattern, $request->url(), $matched)) {
			return [];
		}

		$params = [];
		foreach ($this->params as $index => $key) {
			if (!is_int($index)) {
				continue;
			}
			if (isset($matched[$index + 1])) {
				$params[$key] = $matched[$index + 1];
			}
		}

		return $params;
	}

	public function executeHandler(Request $request)
	{
		if (is_array($this->params) && $this->params !== []) {
			$request->setParams($this->extractParams($request));
		}

		if (is_string($this->handler)) {
			return Router::includeHandlerFile($this->handler, $request, $this);
		}

		return false;
	}
}
