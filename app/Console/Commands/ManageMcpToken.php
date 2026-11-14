<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ManageMcpToken extends Command
{
    protected $signature = 'app:mcp-token {email : Email address of the user} {--revoke : Revoke the existing token instead of creating a new one}';

    protected $description = 'Create (or rotate) or revoke the personal MCP token of a user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->revokeMcpToken();
            $this->info("MCP token for {$email} revoked.");

            return self::SUCCESS;
        }

        $replaced = $user->mcp_token_hash !== null;
        $token = $user->issueMcpToken();

        $this->info($replaced
            ? "New MCP token for {$email} created. The previous token is no longer valid."
            : "MCP token for {$email} created.");
        $this->warn('Copy it now, it will not be shown again:');
        $this->line($token);

        return self::SUCCESS;
    }
}
