<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sign in' }} · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#185fa5">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex min-h-full">
        <div class="flex flex-1 items-center justify-center px-4 py-10">
            <div class="w-full max-w-[320px]">
                <div class="mb-5 flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center bg-accent text-[11px] font-medium text-white">F</span>
                    <div class="leading-tight">
                        <p class="text-[13px] font-medium tracking-tight">{{ config('app.name') }}</p>
                        <p class="text-[10px] text-faint">Personal finance ledger</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mb-3 border border-neg/40 bg-neg-soft px-2.5 py-1.5 text-[11px] text-neg">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>

        <div class="hidden flex-1 items-center justify-center border-l border-line bg-white px-10 lg:flex">
            <div class="w-full max-w-sm">
                <p class="lbl">November 2026</p>
                <div class="mt-2 border border-line">
                    <div class="flex items-center justify-between border-b border-line bg-canvas px-3 py-[6px]">
                        <span class="lbl">Category</span>
                        <span class="lbl">Amount</span>
                    </div>
                    <div class="num divide-y divide-[#edf0f3] text-[11px]">
                        <div class="flex justify-between px-3 py-[5px]"><span class="text-muted">income</span><span class="text-pos">5,400,000</span></div>
                        <div class="flex justify-between px-3 py-[5px]"><span class="text-muted">expense</span><span class="text-neg">2,668,667</span></div>
                        <div class="flex justify-between border-t border-line-strong px-3 py-[5px] font-medium"><span>net</span><span>2,731,333</span></div>
                        <div class="flex justify-between px-3 py-[5px]"><span class="text-muted">pokok</span><span>1,997,000</span></div>
                        <div class="flex justify-between px-3 py-[5px]"><span class="text-muted">transportation</span><span>166,667</span></div>
                        <div class="flex justify-between px-3 py-[5px]"><span class="text-muted">shopping bulanan</span><span>355,000</span></div>
                    </div>
                </div>
                <p class="mt-3 text-[11px] leading-relaxed text-muted">
                    Log spending from Telegram in seconds. Sync the monthly report straight into Google Sheets.
                </p>
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach (['Telegram bot', 'Sheets sync', 'Portfolio', 'Reports'] as $chip)
                        <span class="chip">{{ $chip }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</body>
</html>
