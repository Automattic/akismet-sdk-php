<?php

declare(strict_types=1);

/**
 * Laravel Integration Example
 *
 * This file contains multiple snippets representing separate files in a Laravel
 * application. It is not directly runnable — copy each section into the
 * corresponding file path shown in the section marker.
 *
 * Installation steps:
 * 1. Add these values to your .env file:
 *    AKISMET_API_KEY=your-api-key
 *    AKISMET_SITE_URL=https://your-site.com
 *    AKISMET_TEST_MODE=true
 *
 * 2. Create app/Providers/AkismetServiceProvider.php with the code below
 *
 * 3. Register the provider in config/app.php:
 *    'providers' => [
 *        // ...
 *        App\Providers\AkismetServiceProvider::class,
 *    ]
 *
 * 4. Use dependency injection in your controllers/services
 */

// FILE: app/Providers/AkismetServiceProvider.php

namespace App\Providers;

use Automattic\Akismet\Akismet;
use Automattic\Akismet\AkismetInterface;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Illuminate\Support\ServiceProvider;

class AkismetServiceProvider extends ServiceProvider
{
    /**
     * Register the Akismet service.
     */
    public function register(): void
    {
        $this->app->singleton(AkismetInterface::class, function ($app) {
            return new Akismet(
                apiKey: config('services.akismet.api_key'),
                blog: config('services.akismet.site_url'),
                isTest: config('services.akismet.test_mode', false)
            );
        });

        // Also bind the concrete class for type-hinting
        $this->app->singleton(Akismet::class, fn($app) => $app->make(AkismetInterface::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Optionally verify the API key on boot (only in non-production environments)
        if ($this->app->environment('local') && config('services.akismet.verify_on_boot', false)) {
            $akismet = $this->app->make(AkismetInterface::class);
            try {
                $akismet->verifyKey();
            } catch (InvalidApiKeyException $e) {
                throw new \RuntimeException('Invalid Akismet API key: ' . $e->getMessage());
            }
        }
    }
}

// FILE: config/services.php (add to existing array)
/*
return [
    // ... other services

    'akismet' => [
        'api_key' => env('AKISMET_API_KEY'),
        'site_url' => env('AKISMET_SITE_URL'),
        'test_mode' => env('AKISMET_TEST_MODE', false),
        'verify_on_boot' => env('AKISMET_VERIFY_ON_BOOT', false),
    ],
];
*/

// FILE: app/Http/Controllers/CommentController.php

namespace App\Http\Controllers;

use Automattic\Akismet\AkismetInterface;
use Automattic\Akismet\DTO\Comment as AkismetComment;
use Automattic\Akismet\Enum\CommentType;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(
        private readonly AkismetInterface $akismet
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'author' => 'required|string',
            'author_email' => 'required|email',
        ]);

        // Create Akismet comment from request
        $akismetComment = new AkismetComment(
            userIp: $request->ip(),
            userAgent: $request->userAgent(),
            content: $validated['content'],
            authorName: $validated['author'],
            authorEmail: $validated['author_email'],
            type: CommentType::Comment,
            referrer: $request->header('referer'),
            permalink: $request->url()
        );

        // Check for spam
        $result = $this->akismet->check($akismetComment);

        if ($result->shouldDiscard()) {
            // Blatant spam - just discard it
            return response()->json(['message' => 'Comment rejected'], 400);
        }

        // Save comment with spam flag
        $comment = Comment::create([
            'content' => $validated['content'],
            'author' => $validated['author'],
            'author_email' => $validated['author_email'],
            'is_spam' => $result->isSpam(),
            'status' => $result->isSpam() ? 'pending' : 'approved',
        ]);

        return response()->json($comment, 201);
    }
}

// FILE: app/Jobs/CheckCommentForSpam.php

namespace App\Jobs;

use Automattic\Akismet\AkismetInterface;
use Automattic\Akismet\Factory\CommentFactory;
use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckCommentForSpam implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param int                  $commentId   The comment ID to check.
     * @param array<string, mixed> $akismetData Comment data for Akismet (camelCase or snake_case keys).
     */
    public function __construct(
        private readonly int $commentId,
        private readonly array $akismetData
    ) {}

    public function handle(AkismetInterface $akismet): void
    {
        $comment = Comment::findOrFail($this->commentId);

        // CommentFactory::fromArray() supports both camelCase and snake_case keys
        $akismetComment = CommentFactory::fromArray($this->akismetData);
        $result = $akismet->check($akismetComment);

        $comment->update([
            'is_spam' => $result->isSpam(),
            'status' => $result->isSpam() ? 'spam' : 'approved',
        ]);
    }
}
