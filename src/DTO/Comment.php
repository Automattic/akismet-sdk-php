<?php
/**
 * Comment DTO for Akismet API requests.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Enum\CommentType;
use Automattic\Akismet\Validator\InputValidator;
use DateTimeInterface;

/**
 * Immutable data transfer object representing content to check for spam.
 */
final readonly class Comment {

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
	 * @param string|null             $recheckReason           Reason for rechecking previously checked content.
	 * @param string|null             $honeypotFieldName       Name of a honeypot field if one was used.
	 * @param string|null             $honeypotFieldValue      Value of the honeypot field (should be empty for humans).
	 * @param array<string, string>   $serverVariables         Additional server variables to include.
	 */
	public function __construct(
		public string $userIp,
		public ?string $userAgent = null,
		public ?string $content = null,
		public ?string $authorName = null,
		public ?string $authorEmail = null,
		public ?string $authorUrl = null,
		public CommentType|string|null $type = null,
		public ?string $permalink = null,
		public ?string $referrer = null,
		public ?DateTimeInterface $dateGmt = null,
		public ?DateTimeInterface $postModifiedGmt = null,
		public ?string $parentId = null,
		public ?string $userRole = null,
		public ?string $recheckReason = null,
		public ?string $honeypotFieldName = null,
		public ?string $honeypotFieldValue = null,
		public array $serverVariables = [],
	) {
		InputValidator::validateIp( $userIp, 'userIp' );

		if ( $authorEmail !== null ) {
			InputValidator::validateEmail( $authorEmail, 'authorEmail' );
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
