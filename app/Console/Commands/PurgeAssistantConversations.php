<?php

namespace App\Console\Commands;

use App\Models\AssistantConversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:purge-conversations
    {--limit=500 : Maximum conversations to remove in one run}')]
#[Description('Delete expired internal assistant conversations and their messages')]
final class PurgeAssistantConversations extends Command
{
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $deleted = 0;

        AssistantConversation::query()
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get()
            ->each(function (AssistantConversation $conversation) use (&$deleted): void {
                $deleted += $conversation->delete() ? 1 : 0;
            });

        $this->components->info("Deleted {$deleted} expired assistant conversation(s).");

        return self::SUCCESS;
    }
}
