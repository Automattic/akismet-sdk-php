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
final class CommentFactory
{
    /**
     * Server variables to include in spam checks.
     *
     * These provide additional context for Akismet's spam detection.
     */
    private const SERVER_VARS_TO_INCLUDE = [
        'CONTENT_LENGTH',
        'HTTP_ACCEPT',
        'HTTP_ACCEPT_CHARSET',
        'HTTP_ACCEPT_ENCODING',
        'HTTP_ACCEPT_LANGUAGE',
        'HTTP_CONNECTION',
        'HTTP_HOST',
        'REMOTE_ADDR',
        'REMOTE_HOST',
        'REMOTE_PORT',
        'REQUEST_URI',
        'SERVER_ADDR',
        'SERVER_NAME',
        'SERVER_PORT',
        'SERVER_SOFTWARE',
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
    ): Comment {
        $serverParams = $request->getServerParams();

        // Extract IP address (check common proxy headers first)
        $userIp = self::extractIpAddress($request, $serverParams);

        // Extract user agent
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        // Extract referrer
        $referrer = $request->getHeaderLine('Referer') ?: null;

        // Collect relevant server variables
        $serverVariables = self::extractServerVariables($serverParams);

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
    public static function fromArray(array $data): Comment
    {
        $type = $data['type'] ?? null;
        if (is_string($type)) {
            $type = CommentType::tryFrom($type) ?? $type;
        }

        return new Comment(
            userIp: (string) ($data['userIp'] ?? $data['user_ip'] ?? ''),
            userAgent: $data['userAgent'] ?? $data['user_agent'] ?? null,
            content: $data['content'] ?? $data['comment_content'] ?? null,
            authorName: $data['authorName'] ?? $data['comment_author'] ?? null,
            authorEmail: $data['authorEmail'] ?? $data['comment_author_email'] ?? null,
            authorUrl: $data['authorUrl'] ?? $data['comment_author_url'] ?? null,
            type: $type,
            permalink: $data['permalink'] ?? null,
            referrer: $data['referrer'] ?? null,
            dateGmt: $data['dateGmt'] ?? $data['comment_date_gmt'] ?? null,
            postModifiedGmt: $data['postModifiedGmt'] ?? $data['comment_post_modified_gmt'] ?? null,
            parentId: $data['parentId'] ?? $data['comment_parent'] ?? null,
            userRole: $data['userRole'] ?? $data['user_role'] ?? null,
            recheckReason: $data['recheckReason'] ?? $data['recheck_reason'] ?? null,
            honeypotFieldName: $data['honeypotFieldName'] ?? $data['honeypot_field_name'] ?? null,
            honeypotFieldValue: $data['honeypotFieldValue'] ?? null,
            serverVariables: $data['serverVariables'] ?? [],
        );
    }

    /**
     * Extract the client IP address from the request.
     *
     * Checks common proxy headers before falling back to REMOTE_ADDR.
     *
     * @param ServerRequestInterface $request      The HTTP request.
     * @param array<string, mixed>   $serverParams Server parameters.
     * @return string The client IP address.
     */
    private static function extractIpAddress(
        ServerRequestInterface $request,
        array $serverParams,
    ): string {
        // Check X-Forwarded-For header (may contain multiple IPs)
        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if ($forwardedFor !== '') {
            $ips = array_map('trim', explode(',', $forwardedFor));
            return $ips[0];
        }

        // Check other common proxy headers
        $proxyHeaders = ['X-Real-IP', 'CF-Connecting-IP', 'True-Client-IP'];
        foreach ($proxyHeaders as $header) {
            $ip = $request->getHeaderLine($header);
            if ($ip !== '') {
                return $ip;
            }
        }

        // Fall back to REMOTE_ADDR
        return (string) ($serverParams['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    /**
     * Extract relevant server variables for Akismet.
     *
     * @param array<string, mixed> $serverParams Server parameters.
     * @return array<string, string> Filtered server variables.
     */
    private static function extractServerVariables(array $serverParams): array
    {
        $variables = [];

        foreach (self::SERVER_VARS_TO_INCLUDE as $key) {
            if (isset($serverParams[$key]) && is_string($serverParams[$key])) {
                $variables[$key] = $serverParams[$key];
            }
        }

        return $variables;
    }
}