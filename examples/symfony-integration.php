<?php

declare(strict_types=1);

/**
 * Symfony Integration Example
 *
 * This file contains multiple snippets representing separate files in a Symfony
 * application. It is not directly runnable — copy each section into the
 * corresponding file path shown in the section marker.
 *
 * Installation steps:
 * 1. Add these values to your .env file:
 *    AKISMET_API_KEY=your-api-key
 *    AKISMET_SITE_URL=https://your-site.com
 *    AKISMET_TEST_MODE=true
 *
 * 2. Add the service definitions to config/services.yaml (see below)
 *
 * 3. Use dependency injection in your controllers/services
 */

// FILE: config/services.yaml
/*
services:
    # ... existing services

    Automattic\Akismet\Akismet:
        arguments:
            $apiKey: '%env(AKISMET_API_KEY)%'
            $blog: '%env(AKISMET_SITE_URL)%'
            $isTest: '%env(bool:AKISMET_TEST_MODE)%'

    Automattic\Akismet\AkismetInterface:
        alias: Automattic\Akismet\Akismet
*/

// FILE: src/Controller/CommentController.php

namespace App\Controller;

use Automattic\Akismet\AkismetInterface;
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends AbstractController
{
    public function __construct(
        private readonly AkismetInterface $akismet
    ) {}

    #[Route('/api/comments', name: 'api_comment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate input (use Symfony Validator in production)
        if (empty($data['content']) || empty($data['author']) || empty($data['author_email'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        // Create Akismet comment from request
        // If symfony/psr-http-message-bridge is installed, you can use
        // CommentFactory::fromRequest() with a PSR-7 ServerRequest instead.
        $akismetComment = new Comment(
            userIp: $request->getClientIp() ?? '',
            userAgent: $request->headers->get('User-Agent'),
            content: $data['content'],
            authorName: $data['author'],
            authorEmail: $data['author_email'],
            type: CommentType::Comment,
            referrer: $request->headers->get('referer'),
            permalink: $request->getUri()
        );

        // Check for spam
        $result = $this->akismet->check($akismetComment);

        if ($result->shouldDiscard()) {
            // Blatant spam - just reject it
            return $this->json(
                ['message' => 'Comment rejected as spam'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Save comment (pseudo-code - use your entity manager)
        // $comment = new CommentEntity();
        // $comment->setContent($data['content']);
        // $comment->setAuthor($data['author']);
        // $comment->setAuthorEmail($data['author_email']);
        // $comment->setIsSpam($result->isSpam());
        // $comment->setStatus($result->isSpam() ? 'pending' : 'approved');
        // $entityManager->persist($comment);
        // $entityManager->flush();

        return $this->json([
            'message' => 'Comment created',
            'is_spam' => $result->isSpam(),
            'status' => $result->isSpam() ? 'pending' : 'approved',
        ], Response::HTTP_CREATED);
    }
}

// FILE: src/Service/SpamService.php

namespace App\Service;

use Automattic\Akismet\AkismetInterface;
use Automattic\Akismet\DTO\Comment as AkismetComment;
use Automattic\Akismet\Enum\CommentType;

class SpamService
{
    public function __construct(
        private readonly AkismetInterface $akismet
    ) {}

    /**
     * Check a comment for spam.
     *
     * @param array<string, mixed> $commentData Comment data.
     * @param string               $userIp      User IP address.
     * @param string               $userAgent   User agent string.
     * @return array{is_spam: bool, is_discard: bool, verdict: string}
     */
    public function checkComment(array $commentData, string $userIp, string $userAgent): array
    {
        $akismetComment = new AkismetComment(
            userIp: $userIp,
            userAgent: $userAgent,
            content: $commentData['content'] ?? null,
            authorName: $commentData['author'] ?? null,
            authorEmail: $commentData['author_email'] ?? null,
            type: CommentType::tryFrom($commentData['type'] ?? 'comment') ?? $commentData['type'] ?? 'comment'
        );

        $result = $this->akismet->check($akismetComment);

        return [
            'is_spam' => $result->isSpam(),
            'is_discard' => $result->shouldDiscard(),
            'verdict' => $result->verdict->value,
        ];
    }

    /**
     * Report a comment as spam.
     *
     * @param array<string, mixed> $commentData Comment data.
     * @param string               $userIp      User IP address.
     * @param string               $userAgent   User agent string.
     */
    public function reportSpam(array $commentData, string $userIp, string $userAgent): void
    {
        $akismetComment = new AkismetComment(
            userIp: $userIp,
            userAgent: $userAgent,
            content: $commentData['content'] ?? null,
            authorName: $commentData['author'] ?? null,
            authorEmail: $commentData['author_email'] ?? null,
            type: CommentType::tryFrom($commentData['type'] ?? 'comment') ?? $commentData['type'] ?? 'comment'
        );

        $this->akismet->submitSpam($akismetComment);
    }

    /**
     * Report a comment as ham (not spam).
     *
     * @param array<string, mixed> $commentData Comment data.
     * @param string               $userIp      User IP address.
     * @param string               $userAgent   User agent string.
     */
    public function reportHam(array $commentData, string $userIp, string $userAgent): void
    {
        $akismetComment = new AkismetComment(
            userIp: $userIp,
            userAgent: $userAgent,
            content: $commentData['content'] ?? null,
            authorName: $commentData['author'] ?? null,
            authorEmail: $commentData['author_email'] ?? null,
            type: CommentType::tryFrom($commentData['type'] ?? 'comment') ?? $commentData['type'] ?? 'comment'
        );

        $this->akismet->submitHam($akismetComment);
    }

    /**
     * Get API usage statistics.
     *
     * @return array{usage: int, limit: int|null, percentage: string, throttled: bool}
     */
    public function getUsageStats(): array
    {
        $usage = $this->akismet->getUsageLimit();

        return [
            'usage' => $usage->usage,
            'limit' => $usage->limit,
            'percentage' => $usage->percentage,
            'throttled' => $usage->throttled,
        ];
    }
}
