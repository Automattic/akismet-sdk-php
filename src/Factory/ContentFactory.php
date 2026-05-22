<?php
/**
 * Factory for creating Content objects from various sources.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Factory;

use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\ContentType;
use Automattic\Akismet\Exception\ValidationException;
use DateTimeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for creating Content DTOs from HTTP requests and other sources.
 */
final class ContentFactory {

	/**
	 * Server variables to exclude from spam checks.
	 *
	 * The Akismet API benefits from receiving as much server environment data
	 * as possible. Rather than an allowlist, we exclude only sensitive values
	 * that should never be sent — matching the approach used by the WP plugin.
	 *
	 * @var array<string, true>
	 */
	private const SERVER_VARS_TO_EXCLUDE = [
		'HTTP_COOKIE'  => true,
		'HTTP_COOKIE2' => true,
		'PHP_AUTH_PW'  => true,
	];

	/**
	 * Proxy headers to check for the real client IP (after X-Forwarded-For).
	 *
	 * @var array<string>
	 */
	private const PROXY_HEADERS = [
		'X-Real-IP',
		'CF-Connecting-IP',
		'True-Client-IP',
	];

	/**
	 * Create a Content from a PSR-7 server request.
	 *
	 * Automatically extracts IP, user agent, referrer, and server variables.
	 *
	 * @param ServerRequestInterface   $request       The incoming HTTP request.
	 * @param string|null              $body          The content to check (e.g., comment body).
	 * @param string|null              $authorName    The content author's name.
	 * @param string|null              $authorEmail   The content author's email.
	 * @param string|null              $authorUrl     The content author's website.
	 * @param ContentType|string|null  $type          The type of content.
	 * @param string|null              $permalink     The permanent URL of the entry.
	 * @param DateTimeInterface|null   $dateGmt       When the content was created.
	 * @param string|null              $userRole      The user's role (e.g., 'administrator').
	 * @param string|null              $recheckReason Reason for rechecking content.
	 * @param string|null              $honeypotFieldName  Name of honeypot field.
	 * @param string|null              $honeypotFieldValue Value of honeypot field.
	 * @param string|null              $context            The context or location of the content.
	 * @param string|null              $callback            Webhook URL for verdict update callbacks.
	 * @param array<string>           $trustedProxies     List of trusted proxy IPs. Use ['*'] to trust all proxies.
	 * @param string|null              $blogLang           Languages in use on the site.
	 * @param array<int, string>       $contextValues      Context values to send as repeated comment_context[] fields.
	 * @param bool                     $classify           Request extended classification metadata from the API.
	 * @param string|null              $blogCharset        Character encoding for comment_* form values.
	 */
	public static function fromRequest(
		ServerRequestInterface $request,
		?string $body = null,
		?string $authorName = null,
		?string $authorEmail = null,
		?string $authorUrl = null,
		ContentType|string|null $type = null,
		?string $permalink = null,
		?DateTimeInterface $dateGmt = null,
		?string $userRole = null,
		?string $recheckReason = null,
		?string $honeypotFieldName = null,
		?string $honeypotFieldValue = null,
		?string $context = null,
		?string $callback = null,
		array $trustedProxies = [],
		?string $blogLang = null,
		array $contextValues = [],
		bool $classify = false,
		?string $blogCharset = null,
	): Content {
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

		return new Content(
			userIp: $userIp,
			userAgent: $userAgent,
			body: $body,
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
			callback: $callback,
			serverVariables: $serverVariables,
			blogLang: $blogLang,
			contextValues: $contextValues,
			classify: $classify,
			blogCharset: $blogCharset,
		);
	}

	/**
	 * Create a Content from an array of data.
	 *
	 * Useful for creating content from form submissions or stored data.
	 *
	 * @param array<string, mixed> $data Content data with keys matching Content properties.
	 */
	public static function fromArray( array $data ): Content {
		$type = self::getContentType( $data );

		// Extract server variables with proper type checking
		$serverVars = $data['serverVariables'] ?? [];
		/** @var array<string, string> $serverVariables */
		$serverVariables = is_array( $serverVars ) ? $serverVars : [];

		$honeypotFieldName = self::getString( $data, 'honeypotFieldName', 'honeypot_field_name' );
		$context           = self::getString( $data, 'context', 'comment_context' );
		$contextValues     = self::getStringArray( $data, 'contextValues', 'comment_context' );
		if ( $contextValues === [] ) {
			$contextValues = self::getStringArray( $data, 'comment_context[]' );
		}

		return new Content(
			userIp: self::getString( $data, 'userIp', 'user_ip' ) ?? '',
			userAgent: self::getString( $data, 'userAgent', 'user_agent' ),
			body: self::getString( $data, 'body' ) ?? self::getString( $data, 'content', 'comment_content' ),
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
			honeypotFieldName: $honeypotFieldName,
			honeypotFieldValue: self::getHoneypotValue( $data, $honeypotFieldName ),
			context: $context,
			reporter: self::getString( $data, 'reporter' ),
			commentCheckResponse: self::getString( $data, 'commentCheckResponse', 'comment_check_response' ),
			callback: self::getString( $data, 'callback' ),
			serverVariables: $serverVariables,
			blogLang: self::getString( $data, 'blogLang', 'blog_lang' ),
			contextValues: $contextValues,
			classify: self::getBool( $data, 'classify' ),
			blogCharset: self::getString( $data, 'blogCharset', 'blog_charset' ),
		);
	}

	/**
	 * Resolve a value from the data array, trying the primary key then an optional fallback.
	 *
	 * @param array<string, mixed> $data        Source data.
	 * @param string               $key         Primary key.
	 * @param string|null          $fallbackKey Fallback key if primary not found.
	 */
	private static function resolve( array $data, string $key, ?string $fallbackKey = null ): mixed {
		return $data[ $key ] ?? ( $fallbackKey !== null ? ( $data[ $fallbackKey ] ?? null ) : null );
	}

	/**
	 * Get a string value from data array with fallback key.
	 *
	 * @param array<string, mixed> $data        Source data.
	 * @param string               $key         Primary key.
	 * @param string|null          $fallbackKey Fallback key if primary not found.
	 */
	private static function getString( array $data, string $key, ?string $fallbackKey = null ): ?string {
		$value = self::resolve( $data, $key, $fallbackKey );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Get a list of string values from data array with fallback key.
	 *
	 * @param array<string, mixed> $data        Source data.
	 * @param string               $key         Primary key.
	 * @param string|null          $fallbackKey Fallback key if primary not found.
	 * @return array<int, string>
	 */
	private static function getStringArray( array $data, string $key, ?string $fallbackKey = null ): array {
		$value = self::resolve( $data, $key, $fallbackKey );
		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_filter( $value, 'is_string' ) );
	}

	/**
	 * Get a boolean value from data array.
	 *
	 * @param array<string, mixed> $data Source data.
	 * @param string               $key  Key to read.
	 */
	private static function getBool( array $data, string $key ): bool {
		$value = $data[ $key ] ?? null;
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return $value === 1;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), [ '1', 'true' ], true );
		}
		return false;
	}

	/**
	 * Get a DateTimeInterface value from data array with fallback key.
	 *
	 * @param array<string, mixed> $data        Source data.
	 * @param string               $key         Primary key.
	 * @param string|null          $fallbackKey Fallback key if primary not found.
	 * @throws ValidationException If the value is a string that cannot be parsed as a date.
	 */
	private static function getDateTime( array $data, string $key, ?string $fallbackKey = null ): ?DateTimeInterface {
		$value = self::resolve( $data, $key, $fallbackKey );
		if ( $value instanceof DateTimeInterface ) {
			return $value;
		}
		if ( is_string( $value ) && $value !== '' ) {
			try {
				return new \DateTimeImmutable( $value );
			} catch ( \Exception $e ) {
				throw ValidationException::invalidValue(
					$key,
					sprintf( 'invalid date string "%s": %s', $value, $e->getMessage() )
				);
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
	 * Get ContentType from data array.
	 *
	 * @param array<string, mixed> $data Source data.
	 * @return ContentType|string|null
	 */
	private static function getContentType( array $data ): ContentType|string|null {
		$type = $data['type'] ?? $data['comment_type'] ?? null;

		if ( $type instanceof ContentType ) {
			return $type;
		}

		if ( is_string( $type ) ) {
			return ContentType::tryFrom( $type ) ?? $type;
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
			foreach ( self::PROXY_HEADERS as $header ) {
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
			if ( is_string( $value ) && ! isset( self::SERVER_VARS_TO_EXCLUDE[ $key ] ) ) {
				$variables[ $key ] = $value;
			}
		}

		return $variables;
	}
}
