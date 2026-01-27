<?php
/**
 * UsageLimit DTO for Akismet API 1.2 usage-limit response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

/**
 * Represents API usage statistics and limits.
 */
final readonly class UsageLimit
{
    /**
     * @param int|null $limit      Monthly API call limit, or null if unlimited.
     * @param int      $usage      Number of API calls this month.
     * @param string   $percentage Percentage of limit used (e.g., "45.2%").
     * @param bool     $throttled  Whether requests are being throttled.
     */
    public function __construct(
        public ?int $limit,
        public int $usage,
        public string $percentage,
        public bool $throttled,
    ) {
    }

    /**
     * Check if the API key has unlimited usage.
     */
    public function isUnlimited(): bool
    {
        return $this->limit === null;
    }

    /**
     * Get the remaining API calls for this month.
     *
     * Returns null if unlimited.
     */
    public function getRemaining(): ?int
    {
        if ($this->limit === null) {
            return null;
        }

        return max(0, $this->limit - $this->usage);
    }

    /**
     * Create from API JSON response.
     *
     * @param array{limit: int|string, usage: int, percentage: string, throttled: bool} $data
     */
    public static function fromResponse(array $data): self
    {
        // limit can be an integer or "none" for unlimited
        $limit = $data['limit'] === 'none' ? null : (int) $data['limit'];

        return new self(
            $limit,
            (int) $data['usage'],
            $data['percentage'],
            (bool) $data['throttled'],
        );
    }
}