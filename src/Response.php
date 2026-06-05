<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * API success/error response envelope (JsonSerializable).
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class Response implements \JsonSerializable
{
	const SUCCESS = 'success';
	const ERROR = 'error';

	private $status = 'undefined';

	// The payload
	public $data = null;
	// Secondary context: validation violations on error; side-loaded rows, aggregates, or linked objects on success
	public $related = null;

	public function __construct($status, $data = null, $related = null)
	{
		$this->status = $status;

		if ($data !== null) {
			$this->data = $data;
		}

		if ($related !== null) {
			$this->related = $related;
		}
	}

	public function setData($data)
	{
		$this->data = $data;
		return $this;
	}

	public function setRelated($related)
	{
		$this->related = $related;
		return $this;
	}

	public function isSuccess(): bool
	{
		return $this->status == self::SUCCESS;
	}

	public function isError(): bool
	{
		return $this->status == self::ERROR;
	}

	public function jsonSerialize(): \stdClass
	{
		$obj = new \stdClass;
		$obj->status = $this->status;
		$obj->data = $this->data;
		$obj->related = $this->related;
		return $obj;
	}

	public function json(): string
	{
		return json_encode($this);
	}
}