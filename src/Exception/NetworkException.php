<?php
/**
 * Exception for network errors.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when a network error occurs during API communication.
 */
final class NetworkException extends RuntimeException implements AkismetException
{
    /**
     * Create exception for connection failure.
     */
    public static function connectionFailed(?Throwable $previous = null): self
    {
        return new self('Failed to connect to Akismet API', 0, $previous);
    }

    /**
     * Create exception for request timeout.
     */
    public static function timeout(?Throwable $previous = null): self
    {
        return new self('Akismet API request timed out', 0, $previous);
    }

    /**
     * Create exception from a PSR-18 client exception.
     */
    public static function fromClientException(Throwable $exception): self
    {
        return new self(
            sprintf('Akismet API request failed: %s', $exception->getMessage()),
            0,
            $exception
        );
    }
}