<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:check-expired-subscriptions')]
#[Description('Check for expired subscriptions and reset it to free plan')]
class checkExpiredSubscriptions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $freePlan=Plan::where('is_free',true)->first();

        $expiredSubscriptions=Subscription::where('end_at','<',now())  
                                            // ->where('status','active')
                                            ->get();
        
        foreach($expiredSubscriptions as $subscription){
            $subscription->update([
                'plan_id'=>$freePlan->id,
                'end_at'=>now()->addDays(30),
                'status'=>'active',
                'tokens_used'=>0,
            ]);
        }

        $this->info('Expired subscriptions reset successfully');
    }
}
