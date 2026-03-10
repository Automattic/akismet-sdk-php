<?php
/**
 * Content DTO for Akismet API requests.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Enum\CheckResponse;
use Automattic\Akismet\Enum\ContentType;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;
use DateTimeInterface;

/**
 * Immutable data transfer object representing content to check for spam.
 */
final class Content {

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
	 * The original comment-check result, coerced to an enum.
	 */
	public readonly ?CheckResponse $commentCheckResponse;

	/**
	 * Additional server variables with reserved keys and honeypot collisions filtered out.
	 *
	 * @var array<string, string>
	 */
	public readonly array $serverVariables;

	/**
	 * @param string                  $userIp                  IP address of the content submitter (required).
	 * @param string|null             $userAgent               User agent of the content submitter.
	 * @param string|null             $body                    The content to check.
	 * @param string|null             $authorName              Name of the content author.
	 * @param string|null             $authorEmail             Email of the content author.
	 * @param string|null             $authorUrl               URL/website of the content author.
	 * @param ContentType|string|null $type                    Type of content being checked.
	 * @param string|null             $permalink               Permanent URL of the entry being commented on.
	 * @param string|null             $referrer                HTTP referrer header.
	 * @param DateTimeInterface|null  $dateGmt                 Date/time the content was created.
	 * @param DateTimeInterface|null  $postModifiedGmt         Date/time the post was last modified.
	 * @param string|null             $parentId                ID of the parent content if this is a reply.
	 * @param string|null             $userRole                Role of the content submitter (e.g., 'administrator').
	 * @param string|null             $recheckReason           Reason for rechecking previously checked content (free-form string, e.g., 'edit').
	 * @param string|null             $honeypotFieldName       Name of a honeypot field if one was used.
	 * @param string|null             $honeypotFieldValue      Value of the honeypot field (should be empty for humans).
	 * @param string|null             $context                 The context or location of the content within the website.
	 * @param string|null               $reporter                Who reported the content (e.g., current user name).
	 * @param CheckResponse|string|null $commentCheckResponse    The original comment-check result ('true' or 'false').
	 * @param array<string, string>     $serverVariables         Additional server variables to include. Keys matching
	 *                                                            RESERVED_KEYS and the honeypot field name are filtered
	 *                                                            out at construction time.
	 * @throws ValidationException If userIp, authorEmail, authorUrl, or permalink is invalid.
	 */
	public function __construct(
		public readonly string $userIp,
		public readonly ?string $userAgent = null,
		public readonly ?string $body = null,
		public readonly ?string $authorName = null,
		?string $authorEmail = null,
		?string $authorUrl = null,
		public readonly ContentType|string|null $type = null,
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
		CheckResponse|string|null $commentCheckResponse = null,
		array $serverVariables = [],
	) {
		// Normalize empty strings to null for optional validated fields.
		$this->authorEmail = self::nullIfEmpty( $authorEmail );
		$this->authorUrl   = self::nullIfEmpty( $authorUrl );
		$this->permalink   = self::nullIfEmpty( $permalink );

		// Coerce string to enum when possible, validate otherwise.
		$this->commentCheckResponse = self::resolveCheckResponse( $commentCheckResponse );

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
		if ( $this->honeypotFieldValue !== null && $this->honeypotFieldName === null ) {
			throw ValidationException::invalidValue( 'honeypotFieldValue', 'requires honeypotFieldName to be set' );
		}

		// Filter reserved keys and honeypot field name collisions at construction time.
		$excludeKeys = self::RESERVED_KEYS;
		if ( $this->honeypotFieldName !== null ) {
			$excludeKeys[ $this->honeypotFieldName ] = true;
		}
		$this->serverVariables = array_diff_key( $serverVariables, $excludeKeys );
	}

	/**
	 * Create a copy with feedback fields set for submit-spam/submit-ham requests.
	 *
	 * @param string                  $reporter              Who reported the content (e.g., current user name).
	 * @param CheckResponse|string    $commentCheckResponse  The original comment-check result.
	 */
	public function withFeedback( string $reporter, CheckResponse|string $commentCheckResponse ): self {
		// Note: serverVariables are already filtered, but the constructor will
		// harmlessly re-filter them since readonly properties prevent bypass.
		return new self(
			userIp: $this->userIp,
			userAgent: $this->userAgent,
			body: $this->body,
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

		if ( $this->body !== null ) {
			$data['comment_content'] = $this->body;
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
			$data['comment_type'] = $this->type instanceof ContentType
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
			$data['comment_check_response'] = $this->commentCheckResponse->value;
		}

		// Server variables are pre-filtered at construction time.
		foreach ( $this->serverVariables as $key => $value ) {
			$data[ $key ] = $value;
		}

		return $data;
	}

	/**
	 * Normalize an empty string to null.
	 */
	private static function nullIfEmpty( ?string $value ): ?string {
		return ( $value === null || $value === '' ) ? null : $value;
	}

	/**
	 * Coerce a CheckResponse|string|null to a CheckResponse enum or throw.
	 *
	 * @throws ValidationException If the string value is not 'true' or 'false'.
	 */
	private static function resolveCheckResponse( CheckResponse|string|null $value ): ?CheckResponse {
		if ( $value === null || $value instanceof CheckResponse ) {
			return $value;
		}

		$resolved = CheckResponse::tryFrom( $value );
		if ( $resolved === null ) {
			throw ValidationException::invalidValue( 'commentCheckResponse', "expected 'true' or 'false'" );
		}

		return $resolved;
	}
}
