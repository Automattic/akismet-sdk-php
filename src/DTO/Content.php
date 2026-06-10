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
		'blog_lang'                 => true,
		'blog_charset'              => true,
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
		'callback'                  => true,
		'classify'                  => true,
	];

	/**
	 * Wire keys valid only on /comment-check. Stripped from feedback submissions
	 * by toFeedbackArray().
	 *
	 * @var array<string, true>
	 */
	private const COMMENT_CHECK_ONLY_KEYS = [
		'callback' => true,
		'classify' => true,
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
	 * Webhook URL for verdict update callbacks (normalized from empty string to null).
	 */
	public readonly ?string $callback;

	/**
	 * The original comment-check result, coerced to an enum.
	 */
	public readonly ?CheckResponse $commentCheckResponse;

	/**
	 * Honeypot field name, trimmed and validated against reserved keys.
	 */
	public readonly ?string $honeypotFieldName;

	/**
	 * Context values to send as repeated comment_context[] fields.
	 *
	 * @var array<int, string>
	 */
	public readonly array $contextValues;

	/**
	 * Additional server variables with reserved keys and honeypot collisions filtered out.
	 *
	 * @var array<string, string>
	 */
	public readonly array $serverVariables;

	/**
	 * Note on union type design: `$type` accepts ContentType|string|null and stores
	 * the value as-is because the Akismet API accepts arbitrary custom type strings
	 * (open value set). In contrast, `$commentCheckResponse` accepts CheckResponse|string|null
	 * but coerces strings to the CheckResponse enum at construction time because the API
	 * only ever returns 'true' or 'false' (closed value set).
	 *
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
	 * @param string|null             $reporter                Who reported the content (e.g., current user name).
	 * @param CheckResponse|string|null $commentCheckResponse  The original comment-check result ('true' or 'false').
	 * @param string|null             $callback                Webhook URL for verdict update callbacks.
	 * @param array<string, string>   $serverVariables         Additional server variables to include. Keys matching
	 *                                                          RESERVED_KEYS and the honeypot field name are filtered
	 *                                                          out at construction time.
	 * @param string|null             $blogLang                Languages in use on the site (for example, "en, fr_ca").
	 * @param array<int, mixed>       $contextValues           Context values to send as repeated comment_context[] fields. Must contain non-empty strings; anything else throws ValidationException.
	 * @param bool                    $classify                Request extended classification metadata from the API.
	 * @param string|null             $blogCharset             Character encoding for comment_* form values.
	 * @throws ValidationException If userIp, authorEmail, authorUrl, permalink, callback, contextValues, honeypotFieldName, or serverVariables are invalid.
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
		?string $honeypotFieldName = null,
		public readonly ?string $honeypotFieldValue = null,
		public readonly ?string $context = null,
		public readonly ?string $reporter = null,
		CheckResponse|string|null $commentCheckResponse = null,
		?string $callback = null,
		array $serverVariables = [],
		public readonly ?string $blogLang = null,
		array $contextValues = [],
		public readonly bool $classify = false,
		public readonly ?string $blogCharset = null,
	) {
		// Normalize empty strings to null for fields with URL/email validation.
		// Other string fields intentionally skip this: empty strings are valid
		// API values and don't undergo format validation that would reject them.
		$this->authorEmail = self::nullIfEmpty( $authorEmail );
		$this->authorUrl   = self::nullIfEmpty( $authorUrl );
		$this->permalink   = self::nullIfEmpty( $permalink );
		$this->callback    = self::nullIfEmpty( $callback );

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
		if ( $this->callback !== null ) {
			InputValidator::validateUrl( $this->callback, 'callback' );
		}
		if ( $this->honeypotFieldValue !== null && $honeypotFieldName === null ) {
			throw ValidationException::invalidValue( 'honeypotFieldValue', 'requires honeypotFieldName to be set' );
		}
		if ( $honeypotFieldName !== null ) {
			$honeypotFieldName = trim( $honeypotFieldName );
			if ( $honeypotFieldName === '' ) {
				throw ValidationException::invalidValue( 'honeypotFieldName', 'cannot be empty' );
			}
			if ( self::isReservedKey( $honeypotFieldName ) ) {
				throw ValidationException::invalidValue( 'honeypotFieldName', 'cannot use reserved Akismet field name' );
			}
		}
		$this->honeypotFieldName = $honeypotFieldName;

		$this->contextValues = self::normalizeContextValues( $contextValues );
		if ( $this->context !== null && $this->contextValues !== [] ) {
			throw ValidationException::invalidValue( 'context', 'cannot set both context and contextValues' );
		}

		// Filter reserved keys and honeypot field name collisions at construction time.
		$this->serverVariables = self::filterServerVariables( $serverVariables, $this->honeypotFieldName );
	}

	/**
	 * Create a copy with feedback fields set for submit-spam/submit-ham requests.
	 *
	 * Note: callback is intentionally omitted — webhook callbacks are only
	 * relevant for comment-check requests, not feedback submissions.
	 *
	 * @param string                  $reporter              Who reported the content (e.g., current user name).
	 * @param CheckResponse|string    $commentCheckResponse  The original comment-check result.
	 * @return self New Content with feedback fields set and callback cleared.
	 * @throws ValidationException If commentCheckResponse is an invalid string.
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
			blogLang: $this->blogLang,
			contextValues: $this->contextValues,
			blogCharset: $this->blogCharset,
		);
	}

	/**
	 * Convert to array for API request.
	 *
	 * Server variables have already been filtered at construction time
	 * to exclude RESERVED_KEYS and honeypot field name collisions.
	 *
	 * @return array<string, string|array<int, string>>
	 */
	public function toArray(): array {
		$data = [
			'user_ip' => $this->userIp,
		];

		if ( $this->userAgent !== null ) {
			$data['user_agent'] = $this->userAgent;
		}

		if ( $this->blogLang !== null ) {
			$data['blog_lang'] = $this->blogLang;
		}

		if ( $this->blogCharset !== null ) {
			$data['blog_charset'] = $this->blogCharset;
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
		} elseif ( $this->contextValues !== [] ) {
			$data['comment_context'] = $this->contextValues;
		}

		if ( $this->reporter !== null ) {
			$data['reporter'] = $this->reporter;
		}

		if ( $this->commentCheckResponse !== null ) {
			$data['comment_check_response'] = $this->commentCheckResponse->value;
		}

		if ( $this->callback !== null ) {
			$data['callback'] = $this->callback;
		}

		if ( $this->classify ) {
			$data['classify'] = '1';
		}

		// Server variables are pre-filtered at construction time.
		foreach ( $this->serverVariables as $key => $value ) {
			$data[ $key ] = $value;
		}

		return $data;
	}

	/**
	 * Wire payload for submit-spam / submit-ham, with comment-check-only
	 * fields stripped. Keeps the DTO authoritative over which keys belong on which
	 * endpoint, so the facade never has to guess.
	 *
	 * @return array<string, string|array<int, string>>
	 */
	public function toFeedbackArray(): array {
		return array_diff_key( $this->toArray(), self::COMMENT_CHECK_ONLY_KEYS );
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

	/**
	 * Normalize context values by reindexing as a list. Empty strings and non-string
	 * entries throw ValidationException so caller mistakes surface loudly.
	 *
	 * Input is declared `mixed` because this helper validates at the public-API
	 * boundary: PHP does not enforce generic array types at runtime.
	 *
	 * @param array<int, mixed> $values Context values.
	 * @return array<int, string>
	 * @throws ValidationException
	 */
	private static function normalizeContextValues( array $values ): array {
		$normalized = [];
		foreach ( $values as $index => $value ) {
			if ( ! is_string( $value ) ) {
				throw ValidationException::invalidValue(
					sprintf( 'contextValues[%d]', $index ),
					sprintf( 'expected string, got %s', get_debug_type( $value ) )
				);
			}
			if ( $value === '' ) {
				throw ValidationException::invalidValue(
					sprintf( 'contextValues[%d]', $index ),
					'cannot be empty string'
				);
			}
			$normalized[] = $value;
		}
		return $normalized;
	}

	/**
	 * Check whether a form key is reserved by Akismet request fields. Also matches
	 * PHP-style array-suffix collisions (e.g. `comment_context[0]` resolves to
	 * the reserved canonical `comment_context`).
	 */
	private static function isReservedKey( string $key ): bool {
		if ( isset( self::RESERVED_KEYS[ $key ] ) ) {
			return true;
		}

		$baseKey = strstr( $key, '[', true );
		return is_string( $baseKey ) && $baseKey !== '' && isset( self::RESERVED_KEYS[ $baseKey ] );
	}

	/**
	 * Filter server variables that would collide with SDK-managed request fields.
	 * Non-string values throw ValidationException — the wire form encoding only
	 * accepts strings.
	 *
	 * Input is declared `mixed` because this helper validates at the public-API
	 * boundary: PHP does not enforce generic array types at runtime.
	 *
	 * @param array<string, mixed> $serverVariables   Server variables to filter.
	 * @param string|null          $honeypotFieldName Honeypot field name to reserve.
	 * @return array<string, string>
	 * @throws ValidationException
	 */
	private static function filterServerVariables( array $serverVariables, ?string $honeypotFieldName ): array {
		$filtered = [];
		foreach ( $serverVariables as $key => $value ) {
			if ( self::isReservedKey( $key ) ) {
				continue;
			}
			if ( $honeypotFieldName !== null && $key === $honeypotFieldName ) {
				continue;
			}
			if ( ! is_string( $value ) ) {
				throw ValidationException::invalidValue(
					sprintf( 'serverVariables[%s]', $key ),
					sprintf( 'expected string, got %s', get_debug_type( $value ) )
				);
			}
			$filtered[ $key ] = $value;
		}
		return $filtered;
	}
}
