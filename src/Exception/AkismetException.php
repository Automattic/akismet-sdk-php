<?php
/**
 * Base exception interface for Akismet SDK.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use Throwable;

/**
 * Marker interface for all Akismet exceptions.
 *
 * All exceptions thrown by the Akismet SDK implement this interface,
 * allowing consumers to catch all SDK exceptions with a single catch block.
 */
interface AkismetException extends Throwable
{
}