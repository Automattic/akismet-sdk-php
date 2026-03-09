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
	 * Akismet canonical field names that must not be overwritten by serverVariables.
	 *
	 * @var array<string, true>
	 */
	private const RESERVED_KEYS = [
		'user_ip'                   => true,
		'user_agent'                => true,
		'comment_content'           => true,
		'comment_author'            => true,
		'comment_author_email'      => true,
		'comment_author_url'        => true,
		'comment_type'              => true,
		'permalink'                 => true,
		'referrer'                  => true,
		'comment_date_gmt'          => true,
		'comment_post_modified_gmt' => true,
		'comment_parent'            => true,
		'user_role'                 => true,
		'recheck_reason'            => true,
		'honeypot_field_name'       => true,
		'comment_context'           => true,
		'api_key'                   => true,
		'blog'                      => true,
		'is_test'                   => true,
		'reporter'                  => true,
		'comment_check_response'    => true,
	];

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
	 * @param string|null             $context                 The context or location of the comment within the website.
	 * @param string|null             $reporter                Who reported the content (e.g., current user name).
	 * @param string|null             $commentCheckResponse    The original comment-check result ('true' or 'false').
	 * @param array<string, string>   $serverVariables         Additional server variables to include. Keys matching
	 *                                                          RESERVED_KEYS are silently skipped in toArray() to
	 *                                                          prevent overwriting canonical Akismet fields.
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
		public readonly ?string $context = null,
		public readonly ?string $reporter = null,
		public readonly ?string $commentCheckResponse = null,
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
		if ( $this->commentCheckResponse !== null && ! in_array( $this->commentCheckResponse, [ 'true', 'false' ], true ) ) {
			throw ValidationException::invalidValue( 'commentCheckResponse', "expected 'true' or 'false'" );
		}
	}

	/**
	 * Create a copy with feedback fields set for submit-spam/submit-ham requests.
	 *
	 * @param string $reporter              Who reported the content (e.g., current user name).
	 * @param string $commentCheckResponse  The original comment-check result ('true' or 'false').
	 */
	public function withFeedback( string $reporter, string $commentCheckResponse ): self {
		return new self(
			userIp: $this->userIp,
			userAgent: $this->userAgent,
			content: $this->content,
			authorName: $this->authorName,
			authorEmail: $this->authorEmail,
			authorUrl: $this->authorUrl,
			type: $this->type,
			permalink: $this->permalink,
			referrer: $this->referrer,
			dateGmt: $this->dateGmt,
			postModifiedGmt: $this->postModifiedGmt,
			parentId: $this->parentId,
			userRole: $this->userRole,
			recheckReason: $this->recheckReason,
			honeypotFieldName: $this->honeypotFieldName,
			honeypotFieldValue: $this->honeypotFieldValue,
			context: $this->context,
			reporter: $reporter,
			commentCheckResponse: $commentCheckResponse,
			serverVariables: $this->serverVariables,
		);
	}

	/**
	 * Convert to array for API request.
	 *
	 * Server variables matching RESERVED_KEYS (e.g., 'user_ip', 'blog', 'api_key')
	 * are silently skipped to prevent overwriting canonical Akismet fields.
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

		if ( $this->context !== null ) {
			$data['comment_context'] = $this->context;
		}

		if ( $this->reporter !== null ) {
			$data['reporter'] = $this->reporter;
		}

		if ( $this->commentCheckResponse !== null ) {
			$data['comment_check_response'] = $this->commentCheckResponse;
		}

		// Include additional server variables, skipping reserved Akismet fields
		// and the dynamic honeypot key to prevent overwriting honeypot data.
		foreach ( $this->serverVariables as $key => $value ) {
			if ( ! isset( self::RESERVED_KEYS[ $key ] ) && $key !== $this->honeypotFieldName ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}
}
