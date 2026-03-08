<?php
/**
 * Factory for creating Comment objects from various sources.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Factory;

use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
use DateTimeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for creating Comment DTOs from HTTP requests and other sources.
 */
final class CommentFactory {

	/**
	 * Server variables to exclude from spam checks.
	 *
	 * The Akismet API benefits from receiving as much server environment data
	 * as possible. Rather than an allowlist, we exclude only sensitive values
	 * that should never be sent — matching the approach used by the WP plugin.
	 */
	private const SERVER_VARS_TO_EXCLUDE = [
		'HTTP_COOKIE',
		'HTTP_COOKIE2',
		'PHP_AUTH_PW',
	];

	/**
	 * Create a Comment from a PSR-7 server request.
	 *
	 * Automatically extracts IP, user agent, referrer, and server variables.
	 *
	 * @param ServerRequestInterface   $request       The incoming HTTP request.
	 * @param string|null              $content       The content to check (e.g., comment body).
	 * @param string|null              $authorName    The content author's name.
	 * @param string|null              $authorEmail   The content author's email.
	 * @param string|null              $authorUrl     The content author's website.
	 * @param CommentType|string|null  $type          The type of content.
	 * @param string|null              $permalink     The permanent URL of the entry.
	 * @param DateTimeInterface|null   $dateGmt       When the content was created.
	 * @param string|null              $userRole      The user's role (e.g., 'administrator').
	 * @param string|null              $recheckReason Reason for rechecking content.
	 * @param string|null              $honeypotFieldName  Name of honeypot field.
	 * @param string|null              $honeypotFieldValue Value of honeypot field.
	 * @param string|null              $context            The context or location of the comment.
	 * @param array<string>           $trustedProxies     List of trusted proxy IPs. Use ['*'] to trust all proxies.
	 */
	public static function fromRequest(
		ServerRequestInterface $request,
		?string $content = null,
		?string $authorName = null,
		?string $authorEmail = null,
		?string $authorUrl = null,
		CommentType|string|null $type = null,
		?string $permalink = null,
		?DateTimeInterface $dateGmt = null,
		?string $userRole = null,
		?string $recheckReason = null,
		?string $honeypotFieldName = null,
		?string $honeypotFieldValue = null,
		?string $context = null,
		array $trustedProxies = [],
	): Comment {
		/** @var array<string, mixed> $serverParams */
		$serverParams = $request->getServerParams();

		// Extract IP address (only consult forwarded headers when behind trusted proxies)
		$userIp = self::extractIpAddress( $request, $serverParams, $trustedProxies );

		// Extract user agent
		$userAgentHeader = $request->getHeaderLine( 'User-Agent' );
		$userAgent       = $userAgentHeader !== '' ? $userAgentHeader : null;

		// Extract referrer
		$referrerHeader = $request->getHeaderLine( 'Referer' );
		$referrer       = $referrerHeader !== '' ? $referrerHeader : null;

		// Collect relevant server variables
		$serverVariables = self::extractServerVariables( $serverParams );

		return new Comment(
			userIp: $userIp,
			userAgent: $userAgent,
			content: $content,
			authorName: $authorName,
			authorEmail: $authorEmail,
			authorUrl: $authorUrl,
			type: $type,
			permalink: $permalink,
			referrer: $referrer,
			dateGmt: $dateGmt,
			userRole: $userRole,
			recheckReason: $recheckReason,
			honeypotFieldName: $honeypotFieldName,
			honeypotFieldValue: $honeypotFieldValue,
			context: $context,
			serverVariables: $serverVariables,
		);
	}

	/**
	 * Create a Comment from an array of data.
	 *
	 * Useful for creating comments from form submissions or stored data.
	 *
	 * @param array<string, mixed> $data Comment data with keys matching Comment properties.
	 */
	public static function fromArray( array $data ): Comment {
		$type = self::getCommentType( $data );

		// Extract server variables with proper type checking
		$serverVars = $data['serverVariables'] ?? [];
		/** @var array<string, string> $serverVariables */
		$serverVariables = is_array( $serverVars ) ? $serverVars : [];

		return new Comment(
			userIp: self::getString( $data, 'userIp', 'user_ip' ) ?? '',
			userAgent: self::getString( $data, 'userAgent', 'user_agent' ),
			content: self::getString( $data, 'content', 'comment_content' ),
			authorName: self::getString( $data, 'authorName', 'comment_author' ),
			authorEmail: self::getString( $data, 'authorEmail', 'comment_author_email' ),
			authorUrl: self::getString( $data, 'authorUrl', 'comment_author_url' ),
			type: $type,
			permalink: self::getString( $data, 'permalink' ),
			referrer: self::getString( $data, 'referrer' ),
			dateGmt: self::getDateTime( $data, 'dateGmt', 'comment_date_gmt' ),
			postModifiedGmt: self::getDateTime( $data, 'postModifiedGmt', 'comment_post_modified_gmt' ),
			parentId: self::getString( $data, 'parentId', 'comment_parent' ),
			userRole: self::getString( $data, 'userRole', 'user_role' ),
			recheckReason: self::getString( $data, 'recheckReason', 'recheck_reason' ),
			honeypotFieldName: self::getString( $data, 'honeypotFieldName', 'honeypot_field_name' ),
			honeypotFieldValue: self::getHoneypotValue( $data, self::getString( $data, 'honeypotFieldName', 'honeypot_field_name' ) ),
			context: self::getString( $data, 'context', 'comment_context' ),
			reporter: self::getString( $data, 'reporter' ),
			commentCheckResponse: self::getString( $data, 'commentCheckResponse', 'comment_check_response' ),
			serverVariables: $serverVariables,
		);
	}

	/**
	 * Get a string value from data array with fallback key.
	 *
	 * @param array<string, mixed> $data        Source data.
	 * @param string               $key         Primary key.
	 * @param string|null          $fallbackKey Fallback key if primary not found.
	 */
	private static function getString( array $data, string $key, ?string $fallbackKey = null ): ?string {
		$value = $data[ $key ] ?? ( $fallbackKey !== null ? ( $data[ $fallbackKey ] ?? null ) : null );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Get a DateTimeInterface value from data array with fallback key.
	 *
	 * @param array<string, mixed> $data        Source data.
	 * @param string               $key         Primary key.
	 * @param string|null          $fallbackKey Fallback key if primary not found.
	 */
	private static function getDateTime( array $data, string $key, ?string $fallbackKey = null ): ?DateTimeInterface {
		$value = $data[ $key ] ?? ( $fallbackKey !== null ? ( $data[ $fallbackKey ] ?? null ) : null );
		if ( $value instanceof DateTimeInterface ) {
			return $value;
		}
		if ( is_string( $value ) && $value !== '' ) {
			try {
				return new \DateTimeImmutable( $value );
			} catch ( \Throwable ) {
				return null;
			}
		}
		return null;
	}

	/**
	 * Get the honeypot field value, checking multiple possible keys.
	 *
	 * @param array<string, mixed> $data              Source data.
	 * @param string|null          $honeypotFieldName The honeypot field name, if set.
	 */
	private static function getHoneypotValue( array $data, ?string $honeypotFieldName ): ?string {
		// Check canonical keys first.
		$value = self::getString( $data, 'honeypotFieldValue', 'honeypot_field_value' );
		if ( $value !== null ) {
			return $value;
		}

		// Fall back to the dynamic key used by toArray().
		if ( $honeypotFieldName !== null ) {
			return self::getString( $data, $honeypotFieldName );
		}

		return null;
	}

	/**
	 * Get CommentType from data array.
	 *
	 * @param array<string, mixed> $data Source data.
	 * @return CommentType|string|null
	 */
	private static function getCommentType( array $data ): CommentType|string|null {
		$type = $data['type'] ?? null;

		if ( $type instanceof CommentType ) {
			return $type;
		}

		if ( is_string( $type ) ) {
			return CommentType::tryFrom( $type ) ?? $type;
		}

		return null;
	}

	/**
	 * Extract the client IP address from the request.
	 *
	 * Uses REMOTE_ADDR by default. Only consults forwarded headers when the
	 * direct client (REMOTE_ADDR) is in the trusted proxies list.
	 *
	 * @param ServerRequestInterface $request        The HTTP request.
	 * @param array<string, mixed>   $serverParams   Server parameters.
	 * @param array<string>          $trustedProxies List of trusted proxy IPs, or ['*'] for wildcard.
	 * @return string The client IP address.
	 */
	private static function extractIpAddress(
		ServerRequestInterface $request,
		array $serverParams,
		array $trustedProxies,
	): string {
		$remoteAddr = $serverParams['REMOTE_ADDR'] ?? '';
		$remoteAddr = is_string( $remoteAddr ) ? $remoteAddr : '';

		// Only consult forwarded headers when REMOTE_ADDR is a trusted proxy.
		$isTrusted = in_array( '*', $trustedProxies, true )
			|| in_array( $remoteAddr, $trustedProxies, true );

		if ( $isTrusted ) {
			// Check X-Forwarded-For header (may contain multiple IPs)
			$forwardedFor = $request->getHeaderLine( 'X-Forwarded-For' );
			if ( $forwardedFor !== '' ) {
				$ips = array_map( 'trim', explode( ',', $forwardedFor ) );
				return $ips[0];
			}

			// Check other common proxy headers
			$proxyHeaders = [ 'X-Real-IP', 'CF-Connecting-IP', 'True-Client-IP' ];
			foreach ( $proxyHeaders as $header ) {
				$ip = $request->getHeaderLine( $header );
				if ( $ip !== '' ) {
					return $ip;
				}
			}
		}

		return $remoteAddr;
	}

	/**
	 * Extract relevant server variables for Akismet.
	 *
	 * @param array<string, mixed> $serverParams Server parameters.
	 * @return array<string, string> Filtered server variables.
	 */
	private static function extractServerVariables( array $serverParams ): array {
		$variables = [];

		foreach ( $serverParams as $key => $value ) {
			if ( is_string( $value ) && ! in_array( $key, self::SERVER_VARS_TO_EXCLUDE, true ) ) {
				$variables[ $key ] = $value;
			}
		}

		return $variables;
	}
}
