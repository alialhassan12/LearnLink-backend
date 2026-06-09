<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:reset-ai-usage')]
#[Description('Reset AI usage for all users')]
class ResetAiUsage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Resetting AI usage for all users...');
        Subscription::where('status','active')->update([
            'tokens_used'=>0
        ]);

        $this->info('AI usage reset successfully for all users');
    }
}
