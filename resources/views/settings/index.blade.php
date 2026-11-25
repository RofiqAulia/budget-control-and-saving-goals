<x-app-layout title="Integrations" subtitle="Telegram bot and Google Sheets">
    <div class="grid items-start gap-3 lg:grid-cols-2">

        {{-- Telegram --}}
        <div class="panel">
            <div class="panel-head">
                <div class="flex items-center gap-1.5">
                    <x-icon name="telegram" class="h-3.5 w-3.5 text-accent" />
                    <h2 class="text-[12px] font-medium">Telegram bot</h2>
                </div>
                <span class="chip {{ $telegram->is_active && $telegram->configValue('bot_token') ? 'border-pos/40 text-pos' : '' }}">
                    {{ $telegram->is_active && $telegram->configValue('bot_token') ? 'active' : 'off' }}
                </span>
            </div>

            <div class="space-y-3 p-3">
                <ol class="space-y-0.5 border-l-2 border-line bg-canvas py-2 pl-2.5 pr-2 text-[10px] leading-relaxed text-muted">
                    <li>1. Open <span class="font-medium text-ink">@BotFather</span> and send <span class="num">/newbot</span>.</li>
                    <li>2. Copy the HTTP API token.</li>
                    <li>3. Paste below, then message your bot once.</li>
                </ol>

                <form method="POST" action="{{ route('settings.telegram') }}" class="space-y-2">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="lbl mb-1 block">Bot token</label>
                        <input name="bot_token" value="{{ $telegram->configValue('bot_token') }}" required
                               placeholder="123456789:AAH..." class="field num">
                    </div>

                    <div>
                        <label class="lbl mb-1 block">Chat ID <span class="normal-case tracking-normal">(optional)</span></label>
                        <input name="chat_id" value="{{ $telegram->configValue('chat_id') }}"
                               placeholder="123456789" class="field num">
                    </div>

                    <button class="btn btn-primary w-full justify-center">Save &amp; register webhook</button>
                </form>

                @if ($webhookUrl)
                    <div>
                        <p class="lbl mb-1">Webhook URL</p>
                        <div class="flex items-stretch">
                            <input readonly value="{{ $webhookUrl }}" class="field num truncate text-[10px]">
                            <button type="button" onclick="navigator.clipboard.writeText('{{ $webhookUrl }}')"
                                    class="btn shrink-0 border-l-0 px-2">Copy</button>
                        </div>
                    </div>
                @endif

                @if ($appUrlIsLocal)
                    <div class="border border-warn/40 bg-warn-soft px-2 py-1.5 text-[10px] leading-relaxed text-warn">
                        <p>APP_URL is localhost, so Telegram cannot reach a webhook.</p>
                        <p class="mt-1">For local use run <span class="num">php artisan telegram:poll</span> in a terminal — it receives the same messages and saves your chat ID automatically.</p>
                        <p class="mt-1">For production, expose the app over HTTPS (tunnel or deploy), update APP_URL, then save this form again.</p>
                    </div>
                @endif

                @if ($telegram->last_error)
                    <p class="border border-neg/40 bg-neg-soft px-2 py-1.5 text-[10px] leading-relaxed text-neg">{{ $telegram->last_error }}</p>
                @endif

                <form method="POST" action="{{ route('settings.telegram.test') }}">
                    @csrf
                    <button class="btn w-full justify-center">Send test message</button>
                </form>

                <div class="border border-line bg-canvas p-2">
                    <p class="lbl">Log from Telegram</p>
                    <div class="num mt-1 space-y-0.5 text-[10px] text-muted">
                        <p>makan 25000</p>
                        <p>kopi 15rb</p>
                        <p>income shopee 180000</p>
                        <p>/saldo &nbsp; /budget &nbsp; /laporan</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Google Sheets --}}
        <div class="panel">
            <div class="panel-head">
                <div class="flex items-center gap-1.5">
                    <x-icon name="sheets" class="h-3.5 w-3.5 text-pos" />
                    <h2 class="text-[12px] font-medium">Google Sheets</h2>
                </div>
                <span class="chip {{ $sheets->is_active && $sheets->configValue('spreadsheet_id') ? 'border-pos/40 text-pos' : '' }}">
                    {{ $sheets->is_active && $sheets->configValue('spreadsheet_id') ? 'connected' : 'off' }}
                </span>
            </div>

            <div class="space-y-3 p-3">
                <ol class="space-y-0.5 border-l-2 border-line bg-canvas py-2 pl-2.5 pr-2 text-[10px] leading-relaxed text-muted">
                    <li>1. Create a service account and enable the Sheets API.</li>
                    <li>2. Download its JSON key.</li>
                    <li>3. Share the spreadsheet with the service account email as <span class="font-medium text-ink">Editor</span>.</li>
                </ol>

                <form method="POST" action="{{ route('settings.sheets') }}" class="space-y-2">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="lbl mb-1 block">Service account JSON</label>
                        <textarea name="credentials" rows="6" required placeholder='{"type":"service_account","client_email":"...","private_key":"-----BEGIN PRIVATE KEY-----\n..."}'
                                  class="field num leading-relaxed">{{ $sheets->configValue('credentials') }}</textarea>
                    </div>

                    <div>
                        <label class="lbl mb-1 block">Spreadsheet ID</label>
                        <input name="spreadsheet_id" value="{{ $sheets->configValue('spreadsheet_id') }}" required
                               placeholder="1AbC… from the sheet URL" class="field num">
                    </div>

                    <div>
                        <label class="lbl mb-1 block">Sheet tab name <span class="normal-case tracking-normal">(optional)</span></label>
                        <input name="sheet_name" value="{{ $sheets->configValue('sheet_name') }}"
                               placeholder="Leave blank for the first tab" class="field">
                    </div>

                    <button class="btn btn-primary w-full justify-center">Save &amp; test connection</button>
                </form>

                @if ($sheets->configValue('service_account_email'))
                    <div>
                        <p class="lbl mb-1">Share your sheet with</p>
                        <input readonly value="{{ $sheets->configValue('service_account_email') }}" class="field num truncate text-[10px]">
                    </div>
                @endif

                @if ($sheets->last_error)
                    <p class="border border-neg/40 bg-neg-soft px-2 py-1.5 text-[10px] leading-relaxed text-neg">{{ $sheets->last_error }}</p>
                @endif

                @if ($sheets->last_synced_at)
                    <p class="num text-[10px] text-faint">Last sync {{ $sheets->last_synced_at->format('d/m/Y H:i') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
