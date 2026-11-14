<?php

namespace App\Filament\Pages\User;

use App\Mcp\McpPermission;
use App\Models\McpGrant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * AI clients (MCP) the user connected: their permissions (changeable), last use, revoking,
 * and how to connect Claude Code, Codex, OpenClaw and others.
 */
class ConnectedAiClients extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'connected-ai-clients';

    public static function getNavigationLabel(): string
    {
        return __('Connected AI clients');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Connected AI clients');
    }

    public function content(Schema $schema): Schema
    {
        $url = route('mcp.aurum');
        $name = str(config('app.name'))->slug()->toString();
        $pre = '<pre style="overflow-x: auto; border-radius: 0.5rem; background: rgba(127, 127, 127, 0.12); padding: 0.75rem; font-size: 0.75rem;"><code>';

        // Inline styles: the panel has no Tailwind utilities of its own.
        return $schema->components([
            Section::make(__('Connect an AI client'))
                ->description(__('AI clients like Claude Code, Codex or OpenClaw connect over MCP and can import orders and products, e.g. from an invoice. Add the address below, then sign in and choose the permissions when the client asks. No plugin is needed.'))
                ->collapsible()
                ->schema([
                    Text::make(new HtmlString(implode('', [
                        '<p><strong>'.e(__('Address (MCP server)')).':</strong> <code>'.e($url).'</code></p>',
                        '<p style="margin-top: 0.75rem;"><strong>Claude Code:</strong></p>'.$pre.'claude mcp add --transport http '.e($name).' '.e($url).'</code></pre><p style="font-size: 0.75rem;">'.e(__('Then run /mcp in Claude Code and choose "Authenticate".')).'</p>',
                        '<p style="margin-top: 0.75rem;"><strong>Codex:</strong></p>'.$pre.'codex mcp add '.e($name).' --url '.e($url)."\ncodex mcp login ".e($name).'</code></pre>',
                        '<p style="margin-top: 0.75rem;"><strong>OpenClaw:</strong> '.e(__('ask your agent: "Connect the MCP server :url as :name" or add it in the settings under MCP servers; the sign-in opens in the browser.', ['url' => $url, 'name' => $name])).'</p>',
                        '<p style="margin-top: 0.75rem;"><strong>'.e(__('Other clients (Claude Desktop, ChatGPT, Cursor …)')).':</strong> '.e(__('add a remote MCP server with this address and OAuth.')).'</p>',
                    ]))),
                ]),
            Section::make(__('Connected clients'))
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => McpGrant::query()->where('user_id', Auth::id())->with('client'))
            ->columns([
                TextColumn::make('client.name')->label(__('Client')),
                TextColumn::make('permissions')
                    ->label(__('Permissions'))
                    ->formatStateUsing(fn (string $state): string => McpPermission::tryFrom($state)?->label() ?? $state)
                    ->badge(),
                TextColumn::make('last_used_at')->label(__('Last used'))->since()->placeholder(__('never')),
                TextColumn::make('created_at')->label(__('Connected'))->date(),
            ])
            ->recordActions([
                Action::make('permissions')
                    ->label(__('Permissions'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->fillForm(fn (McpGrant $record): array => ['permissions' => $record->permissions])
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label(__('Permissions'))
                            ->options(McpPermission::options())
                            ->required(),
                    ])
                    ->action(function (McpGrant $record, array $data): void {
                        $record->update(['permissions' => array_values($data['permissions'])]);

                        Notification::make()->success()->title(__('Saved'))->send();
                    }),
                Action::make('revoke')
                    ->label(__('Revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The client loses access right away. To use it again, connect it again.'))
                    ->action(function (McpGrant $record): void {
                        self::revoke($record);

                        Notification::make()->success()->title(__('Access revoked'))->send();
                    }),
            ])
            ->emptyStateHeading(__('No AI client connected yet'))
            ->paginated(false);
    }

    /**
     * Revokes the client's tokens of this user and forgets the permissions.
     */
    public static function revoke(McpGrant $grant): void
    {
        DB::transaction(function () use ($grant): void {
            $tokens = DB::table('oauth_access_tokens')
                ->where('user_id', $grant->user_id)
                ->where('client_id', $grant->oauth_client_id);

            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', (clone $tokens)->pluck('id'))->update(['revoked' => true]);
            $tokens->update(['revoked' => true]);

            $grant->delete();
        });
    }
}
