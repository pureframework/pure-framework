<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class ConstraintViolationMessage
{
    private string $type = '';
    private array $tokens = [];
    private string $message;

    public function __get($name)
    {
        if ($name === 'type') {
            return $this->type;
        }
    }

    public function __construct(string $type, string $message = '', array|null $tokens = null)
    {
        $this->type = $type;

        if (is_array($tokens)) {
            $this->tokens = $tokens;
        }
        $this->message = $message;
    }

    public function getMessage($tokens = [])
    {
        $formattedTokens = [];
        foreach (array_merge($this->tokens, $tokens) as $token => $value) {
            $formattedTokens['{' . $token . '}'] = $value;
        }

        if (!empty($this->message)) {
            $message = strtr($this->message, $formattedTokens);
            return $message;
        }
    }
}