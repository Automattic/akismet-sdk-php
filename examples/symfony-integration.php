<?php

declare(strict_types=1);

/**
 * Symfony Integration Example
 *
 * This example shows how to integrate the Akismet SDK into a Symfony application
 * using service configuration and dependency injection.
 *
 * Installation steps:
 * 1. Add these values to your .env file:
 *    AKISMET_API_KEY=your-api-key
 *    AKISMET_SITE_URL=https://your-site.com
 *    AKISMET_TEST_MODE=true
 *
 * 2. Add the configuration to config/packages/akismet.yaml (see below)
 *
 * 3. Add the service definitions to config/services.yaml (see below)
 *
 * 4. Use dependency injection in your controllers/services
 */

// config/packages/akismet.yaml
/*
akismet:
    api_key: '%env(AKISMET_API_KEY)%'
    site_url: '%env(AKISMET_SITE_URL)%'
    test_mode: '%env(bool:AKISMET_TEST_MODE)%'
*/

// config/services.yaml - Add these service definitions
/*
services:
    # ... existing services

    Automattic\Akismet\Config\Configuration:
        arguments:
            $apiKey: '%env(AKISMET_API_KEY)%'
            $blog: '%env(AKISMET_SITE_URL)%'
            $isTest: '%env(bool:AKISMET_TEST_MODE)%'

    Automattic\Akismet\Akismet:
        arguments:
            $configuration: '@Automattic\Akismet\Config\Configuration'

    Automattic\Akismet\AkismetInterface:
        alias: Automattic\Akismet\Akismet

    # Auto-wire the SDK classes
    Automattic\Akismet\:
        resource: '../vendor/automattic/akismet-sdk-php/src/*'
        exclude:
            - '../vendor/automattic/akismet-sdk-php/src/{DTO,Enum,Exception}'
*/

// Example Controller Usage:

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
        $akismetComment = new Comment(
            user_ip: $request->getClientIp() ?? '',
            user_agent: $request->headers->get('User-Agent') ?? '',
            comment_content: $data['content'],
            comment_author: $data['author'],
            comment_author_email: $data['author_email'],
            comment_type: CommentType::COMMENT,
            referrer: $request->headers->get('referer'),
            permalink: $request->getUri()
        );

        // Check for spam
        $result = $this->akismet->checkComment($akismetComment);

        if ($result->isDiscard()) {
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

// Example Service for handling spam operations:

namespace App\Service;

use Automattic\Akismet\AkismetInterface;
use Automattic\Akismet\DTO\Comment as AkismetComment;
use Automattic\Akismet\Enum\CommentType;

class SpamService
{
    public function __construct(
        private readonly AkismetInterface $akismet
    ) {}

    public function checkComment(array $commentData, string $userIp, string $userAgent): array
    {
        $akismetComment = new AkismetComment(
            user_ip: $userIp,
            user_agent: $userAgent,
            comment_content: $commentData['content'],
            comment_author: $commentData['author'] ?? null,
            comment_author_email: $commentData['author_email'] ?? null,
            comment_type: CommentType::from($commentData['type'] ?? 'comment')
        );

        $result = $this->akismet->checkComment($akismetComment);

        return [
            'is_spam' => $result->isSpam(),
            'is_discard' => $result->isDiscard(),
            'verdict' => $result->verdict->value,
        ];
    }

    public function reportSpam(array $commentData, string $userIp, string $userAgent): void
    {
        $akismetComment = new AkismetComment(
            user_ip: $userIp,
            user_agent: $userAgent,
            comment_content: $commentData['content'],
            comment_author: $commentData['author'] ?? null,
            comment_author_email: $commentData['author_email'] ?? null,
            comment_type: CommentType::from($commentData['type'] ?? 'comment')
        );

        $this->akismet->submitSpam($akismetComment);
    }

    public function reportHam(array $commentData, string $userIp, string $userAgent): void
    {
        $akismetComment = new AkismetComment(
            user_ip: $userIp,
            user_agent: $userAgent,
            comment_content: $commentData['content'],
            comment_author: $commentData['author'] ?? null,
            comment_author_email: $commentData['author_email'] ?? null,
            comment_type: CommentType::from($commentData['type'] ?? 'comment')
        );

        $this->akismet->submitHam($akismetComment);
    }

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
