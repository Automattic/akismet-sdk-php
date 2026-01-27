<?php
/**
 * Spam verdict enum for Akismet API responses.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Enum;

/**
 * Represents the spam verdict returned by Akismet.
 */
enum SpamVerdict: string
{
    case Ham = 'ham';
    case Spam = 'spam';
    case Discard = 'discard';

    /**
     * Check if this verdict indicates spam.
     */
    public function isSpam(): bool
    {
        return $this === self::Spam || $this === self::Discard;
    }

    /**
     * Check if this content should be silently discarded.
     *
     * Discard indicates blatant spam that doesn't need to be reviewed.
     */
    public function shouldDiscard(): bool
    {
        return $this === self::Discard;
    }
}