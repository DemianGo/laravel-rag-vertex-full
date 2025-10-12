<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class GenerateApiKeysForUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-keys:generate
                            {--user-id= : Generate API key for specific user ID}
                            {--all : Generate API keys for all users without one}
                            {--force : Regenerate API keys even if they already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API keys for users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $all = $this->option('all');
        $force = $this->option('force');

        if ($userId) {
            $this->generateForUser($userId, $force);
        } elseif ($all) {
            $this->generateForAllUsers($force);
        } else {
            $this->error('Please specify --user-id=<id> or --all');
            return 1;
        }

        return 0;
    }

    private function generateForUser($userId, $force)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }

        if ($user->hasApiKey() && !$force) {
            $this->warn("User {$user->name} (ID: {$user->id}) already has an API key. Use --force to regenerate.");
            $this->info("Current masked key: {$user->masked_api_key}");
            return;
        }

        $apiKey = $user->generateApiKey();
        
        $this->info("API key generated for user: {$user->name} (ID: {$user->id})");
        $this->line("API Key: {$apiKey}");
        $this->warn("⚠️  Save this key securely! It won't be shown again.");
    }

    private function generateForAllUsers($force)
    {
        $query = $force ? User::query() : User::whereNull('api_key');
        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users found requiring API key generation.');
            return;
        }

        $this->info("Generating API keys for {$users->count()} user(s)...");
        
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $user->generateApiKey();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✓ API keys generated successfully!');
    }
}
