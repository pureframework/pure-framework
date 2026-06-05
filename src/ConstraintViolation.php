<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class ConstraintViolation
{
    private array $violationMessages = [];
    private bool $stopProcessing = false;

    public static function isInstance($violation)
    {
        return $violation instanceof ConstraintViolation;
    }

    public static function merge(array $violations)
    {
        if (count($violations) === 0 || !is_array($violations)) {
            return null;
        }

        $combinedViolations = new self();
        foreach ($violations as $violation) {
            if (!ConstraintViolation::isInstance($violation)) {
                continue;
            }

            $combinedViolations->addViolation($violation);

            if ($violation->stopProcessing) {
                $combinedViolations->stopProcessing();
            }
        }

        if (!$combinedViolations->hasViolations()) {
            return null;
        }

        return $combinedViolations;
    }

    public function __construct(string $type = '', $message = '', array|null $tokens = null, $stopProcessing = false)
    {
        if (!empty($message) && !empty($type)) {
            $this->addMessage($type, $message, $tokens);
        }

        if ($stopProcessing) {
            $this->stopProcessing();
        }
    }

    public function __get($name)
    {
        if ($name === 'stopProcessing') {
            return $this->stopProcessing;
        }
    }

    public function addViolation(ConstraintViolation $violation)
    {
        if (ConstraintViolation::isInstance($violation)) {
            if ($violation->hasViolations()) {
                $this->violationMessages = array_merge($this->violationMessages, $violation->violationMessages);
            }
        }
    }

    public function hasViolations()
    {
        return count($this->violationMessages) > 0;
    }

    public function stopProcessing()
    {
        $this->stopProcessing = true;
    }

    public function addMessage(string $type, $message = '', array|null $tokens = null)
    {
        if (!empty($message)) {
            $this->violationMessages[] = new ConstraintViolationMessage($type, $message, $tokens);
        }
    }

    public function getMessages($tokens = [])
    {
        $messages = [];
        foreach ($this->violationMessages as $violationMessage) {
            $message = $violationMessage->getMessage($tokens);
            if (!empty($message)) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
