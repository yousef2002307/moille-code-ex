<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WebhookSuccess;

class CheckWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-webhooks {--limit=10 : Maximum number of records to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check webhook success records from Mollie';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        
        $webhooks = WebhookSuccess::latest()->limit($limit)->get();
        
        if ($webhooks->isEmpty()) {
            $this->info('No webhook records found.');
            return;
        }
        
        $this->info('=== Recent Webhook Calls ===');
        
        $headers = ['ID', 'Payment ID', 'Status', 'IP Address', 'Received At'];
        $rows = [];
        
        foreach ($webhooks as $webhook) {
            $rows[] = [
                $webhook->id,
                $webhook->payment_id,
                $webhook->status,
                $webhook->ip_address,
                $webhook->created_at->format('Y-m-d H:i:s'),
            ];
        }
        
        $this->table($headers, $rows);
        
        $this->info('Total webhook records: ' . WebhookSuccess::count());
        
        // Show latest webhook details
        $latest = WebhookSuccess::latest()->first();
        if ($latest) {
            $this->info('=== Latest Webhook Details ===');
            $this->line('Payment ID: ' . $latest->payment_id);
            $this->line('Status: ' . $latest->status);
            $this->line('IP Address: ' . $latest->ip_address);
            $this->line('Received At: ' . $latest->created_at->format('Y-m-d H:i:s'));
            $this->line('Data: ' . json_encode($latest->webhook_data, JSON_PRETTY_PRINT));
        }
    }
}
