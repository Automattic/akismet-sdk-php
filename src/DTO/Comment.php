<?php
/**
 * Comment DTO for Akismet API requests.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Enum\CommentType;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;
use DateTimeInterface;

/**
 * Immutable data transfer object representing content to check for spam.
 */
final class Comment {

	/**
	 * Email of the content author (normalized from empty string to null).
	 */
	public readonly ?string $authorEmail;

	/**
	 * URL/website of the content author (normalized from empty string to null).
	 */
	public readonly ?string $authorUrl;

	/**
	 * Permanent URL of the entry being commented on (normalized from empty string to null).
	 */
	public readonly ?string $permalink;

	/**
	 * @param string                  $userIp                  IP address of the content submitter (required).
	 * @param string|null             $userAgent               User agent of the content submitter.
	 * @param string|null             $content                 The content to check.
	 * @param string|null             $authorName              Name of the content author.
	 * @param string|null             $authorEmail             Email of the content author.
	 * @param string|null             $authorUrl               URL/website of the content author.
	 * @param CommentType|string|null $type                    Type of content being checked.
	 * @param string|null             $permalink               Permanent URL of the entry being commented on.
	 * @param string|null             $referrer                HTTP referrer header.
	 * @param DateTimeInterface|null  $dateGmt                 Date/time the content was created.
	 * @param DateTimeInterface|null  $postModifiedGmt         Date/time the post was last modified.
	 * @param string|null             $parentId                ID of the parent comment if this is a reply.
	 * @param string|null             $userRole                Role of the content submitter (e.g., 'administrator').
	 * @param string|null             $recheckReason           Reason for rechecking previously checked content (free-form string, e.g., 'edit').
	 * @param string|null             $honeypotFieldName       Name of a honeypot field if one was used.
	 * @param string|null             $honeypotFieldValue      Value of the honeypot field (should be empty for humans).
	 * @param array<string, string>   $serverVariables         Additional server variables to include.
	 * @throws ValidationException If userIp, authorEmail, authorUrl, or permalink is invalid.
	 */
	public function __construct(
		public readonly string $userIp,
		public readonly ?string $userAgent = null,
		public readonly ?string $content = null,
		public readonly ?string $authorName = null,
		?string $authorEmail = null,
		?string $authorUrl = null,
		public readonly CommentType|string|null $type = null,
		?string $permalink = null,
		public readonly ?string $referrer = null,
		public readonly ?DateTimeInterface $dateGmt = null,
		public readonly ?DateTimeInterface $postModifiedGmt = null,
		public readonly ?string $parentId = null,
		public readonly ?string $userRole = null,
		public readonly ?string $recheckReason = null,
		public readonly ?string $honeypotFieldName = null,
		public readonly ?string $honeypotFieldValue = null,
		public readonly array $serverVariables = [],
	) {
		// Normalize empty strings to null for optional validated fields.
		$this->authorEmail = $authorEmail === '' ? null : $authorEmail;
		$this->authorUrl   = $authorUrl === '' ? null : $authorUrl;
		$this->permalink   = $permalink === '' ? null : $permalink;

		// Validate required fields.
		InputValidator::validateIp( $userIp, 'userIp' );

		// Validate optional fields.
		if ( $this->authorEmail !== null ) {
			InputValidator::validateEmail( $this->authorEmail, 'authorEmail' );
		}
		if ( $this->authorUrl !== null ) {
			InputValidator::validateUrl( $this->authorUrl, 'authorUrl' );
		}
		if ( $this->permalink !== null ) {
			InputValidator::validateUrl( $this->permalink, 'permalink' );
		}
	}

	/**
	 * Convert to array for API request.
	 *
	 * @return array<string, string>
	 */
	public function toArray(): array {
		$data = [
			'user_ip' => $this->userIp,
		];

		if ( $this->userAgent !== null ) {
			$data['user_agent'] = $this->userAgent;
		}

		if ( $this->content !== null ) {
			$data['comment_content'] = $this->content;
		}

		if ( $this->authorName !== null ) {
			$data['comment_author'] = $this->authorName;
		}

		if ( $this->authorEmail !== null ) {
			$data['comment_author_email'] = $this->authorEmail;
		}

		if ( $this->authorUrl !== null ) {
			$data['comment_author_url'] = $this->authorUrl;
		}

		if ( $this->type !== null ) {
			$data['comment_type'] = $this->type instanceof CommentType
				? $this->type->value
				: $this->type;
		}

		if ( $this->permalink !== null ) {
			$data['permalink'] = $this->permalink;
		}

		if ( $this->referrer !== null ) {
			$data['referrer'] = $this->referrer;
		}

		if ( $this->dateGmt !== null ) {
			$data['comment_date_gmt'] = $this->dateGmt->format( 'c' );
		}

		if ( $this->postModifiedGmt !== null ) {
			$data['comment_post_modified_gmt'] = $this->postModifiedGmt->format( 'c' );
		}

		if ( $this->parentId !== null ) {
			$data['comment_parent'] = $this->parentId;
		}

		if ( $this->userRole !== null ) {
			$data['user_role'] = $this->userRole;
		}

		if ( $this->recheckReason !== null ) {
			$data['recheck_reason'] = $this->recheckReason;
		}

		if ( $this->honeypotFieldName !== null ) {
			$data['honeypot_field_name'] = $this->honeypotFieldName;
			if ( $this->honeypotFieldValue !== null ) {
				$data[ $this->honeypotFieldName ] = $this->honeypotFieldValue;
			}
		}

		// Include additional server variables
		foreach ( $this->serverVariables as $key => $value ) {
			$data[ $key ] = $value;
		}

		return $data;
	}
}
