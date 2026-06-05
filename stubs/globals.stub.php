<?php

/**
 * Pure Framework
 *
 * Global variable stubs for IDE type hinting.
 * Set at runtime by Router when executing route or not-found handlers.
 * Include via composer.json autoload-dev files or your IDE include path.
 *
 * @see docs/configuration.md#globals-at-runtime
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

namespace {
	if (false) {
		/** @var \PureFramework\Request $REQUEST */
		$REQUEST = new \PureFramework\Request();

		/** @var \PureFramework\Route|null $ROUTE Matched route; null in not-found handlers */
		$ROUTE = null;

		/** @var string|null $HANDLER Handler file path; null for callable not-found */
		$HANDLER = null;
	}
}
