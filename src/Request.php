<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Request
{
	public $routingData = null;

	private string $method = '';
	private bool $https = false;
	private int $port = 0;
	private ?string $domain = null;
	private ?string $referer = null;
	private ?string $url = null;

	/** @var array|null Route captures from the matched pattern */
	private ?array $params = null;

	/** @var array|null Query string parameters ($_GET) */
	private ?array $query = null;

	/** @var array|null Cookie values ($_COOKIE) */
	private ?array $cookie = null;

	/** @var array|null POST body ($_POST) */
	private ?array $post = null;

	private bool $jsonBodyLoaded = false;
	private array $jsonBodyCache = [];

	public function __construct($autoInit = true)
	{
		if ($autoInit) {
			$this->init();
		}
	}

	public function init()
	{
		$this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if (isset($_SERVER['SERVER_PORT'])) {
			$this->port = (int) $_SERVER['SERVER_PORT'];
		} else {
			$this->port = -1;
		}

		$this->https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
			|| (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
			|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
		;

		if (isset($_SERVER['SERVER_NAME'])) {
			$this->domain = $_SERVER['SERVER_NAME'];
		}

		if (isset($_SERVER['HTTP_REFERER'])) {
			$this->referer = $_SERVER['HTTP_REFERER'];
		}

		if (isset($_SERVER['REQUEST_URI'])) {
			if (!empty($_SERVER['QUERY_STRING'])) {
				$this->url = str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
			} else {
				$this->url = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
			}
		}

		if (isset($_SERVER['QUERY_STRING'])) {
			$this->query = $_GET;
		}

		if (isset($_COOKIE)) {
			$this->cookie = $_COOKIE;
		}

		if (isset($_POST)) {
			$this->post = $_POST;
		}

		$this->routingData = [];
	}

	public function method(): string
	{
		return $this->method;
	}

	public function setMethod(string $method): void
	{
		$this->method = $method;
	}

	public function url(): ?string
	{
		return $this->url;
	}

	public function setUrl(?string $url): void
	{
		$this->url = $url;
	}

	public function isHttps(): bool
	{
		return $this->https;
	}

	public function setHttps(bool $https): void
	{
		$this->https = $https;
	}

	public function domain(): ?string
	{
		return $this->domain;
	}

	public function setDomain(?string $domain): void
	{
		$this->domain = $domain;
	}

	public function port(): int
	{
		return $this->port;
	}

	public function setPort(int $port): void
	{
		$this->port = $port;
	}

	public function referer(): ?string
	{
		return $this->referer;
	}

	public function setReferer(?string $referer): void
	{
		$this->referer = $referer;
	}

	public function setParams(?array $params): void
	{
		$this->params = $params;
	}

	public function setQuery(?array $query): void
	{
		$this->query = $query;
	}

	public function setPost(?array $post): void
	{
		$this->post = $post;
	}

	public function setCookie(?array $cookie): void
	{
		$this->cookie = $cookie;
	}

	public function param(string $name, mixed $default = null): mixed
	{
		if (!is_array($this->params) || !array_key_exists($name, $this->params)) {
			return $default;
		}

		return $this->params[$name];
	}

	/**
	 * Whether the matched route registered a capture with this name (e.g. edit vs new on shared handlers).
	 */
	public function hasParam(string $name): bool
	{
		return is_array($this->params) && array_key_exists($name, $this->params);
	}

	/**
	 * Route param normalized to hyphenated lowercase UUID via Util::sanitizeUuid().
	 * Returns false when the param is missing, empty, or not a valid UUID string.
	 */
	public function uuidParam(string $name): string|false
	{
		if (!is_array($this->params) || !array_key_exists($name, $this->params)) {
			return false;
		}

		$value = $this->params[$name];
		if ($value === null || $value === '') {
			return false;
		}

		if (!is_string($value)) {
			$value = (string) $value;
		}

		return Util::sanitizeUuid($value);
	}

	public function query(string $name, mixed $default = null): mixed
	{
		if (!is_array($this->query) || !array_key_exists($name, $this->query)) {
			return $default;
		}

		return $this->query[$name];
	}

	public function post(string $name, mixed $default = null): mixed
	{
		if (!is_array($this->post) || !array_key_exists($name, $this->post)) {
			return $default;
		}

		return $this->post[$name];
	}

	public function cookie(string $name, mixed $default = null): mixed
	{
		if (!is_array($this->cookie) || !array_key_exists($name, $this->cookie)) {
			return $default;
		}

		return $this->cookie[$name];
	}

	/**
	 * Route params merged with $defaults (request values override defaults for the same key).
	 */
	public function params(array $defaults = []): array
	{
		$params = is_array($this->params) ? $this->params : [];
		return array_merge($defaults, $params);
	}

	/**
	 * Query string params merged with $defaults (request values override defaults for the same key).
	 */
	public function queryAll(array $defaults = []): array
	{
		$query = is_array($this->query) ? $this->query : [];
		return array_merge($defaults, $query);
	}

	/**
	 * POST params merged with $defaults (request values override defaults for the same key).
	 */
	public function postAll(array $defaults = []): array
	{
		$post = is_array($this->post) ? $this->post : [];
		return array_merge($defaults, $post);
	}

	/**
	 * Cookie values merged with $defaults (request values override defaults for the same key).
	 */
	public function cookies(array $defaults = []): array
	{
		$cookie = is_array($this->cookie) ? $this->cookie : [];
		return array_merge($defaults, $cookie);
	}

	public function isMethod(string $method): bool
	{
		return strtoupper($this->method) === strtoupper($method);
	}

	public function isGet(): bool
	{
		return $this->isMethod('GET');
	}

	public function isPost(): bool
	{
		return $this->isMethod('POST');
	}

	public function isPut(): bool
	{
		return $this->isMethod('PUT');
	}

	public function isPatch(): bool
	{
		return $this->isMethod('PATCH');
	}

	public function isDelete(): bool
	{
		return $this->isMethod('DELETE');
	}

	public function isHead(): bool
	{
		return $this->isMethod('HEAD');
	}

	public function isOptions(): bool
	{
		return $this->isMethod('OPTIONS');
	}

	/**
	 * QUERY is a non-standard HTTP verb used for safe read operations with a body.
	 */
	public function isQueryMethod(): bool
	{
		return $this->isMethod('QUERY');
	}

	/**
	 * Parsed JSON request body (php://input). Returns [] when empty, invalid JSON, or non-object/array JSON.
	 * Result is read once per Request instance.
	 */
	public function jsonBody(): array
	{
		if ($this->jsonBodyLoaded) {
			return $this->jsonBodyCache;
		}

		$this->jsonBodyLoaded = true;
		$raw = file_get_contents('php://input');
		if ($raw === false || $raw === '') {
			return $this->jsonBodyCache;
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return $this->jsonBodyCache;
		}

		$this->jsonBodyCache = $decoded;
		return $this->jsonBodyCache;
	}
}
