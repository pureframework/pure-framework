<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class SuccessResponse extends Response
{
	public function __construct($data = null, $related = null)
	{
		parent::__construct(Response::SUCCESS, $data, $related);
	}
}
