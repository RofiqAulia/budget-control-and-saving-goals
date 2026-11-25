<x-guest-layout title="Sign in">
    <div class="panel p-4">
        <h1 class="text-[13px] font-medium tracking-tight">Sign in</h1>
        <p class="mt-0.5 text-[11px] text-muted">Use your account to open the ledger.</p>

        <form method="POST" action="{{ route('login') }}" class="mt-4 space-y-2.5">
            @csrf

            <div>
                <label for="email" class="lbl mb-1 block">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="field" placeholder="you@example.com">
            </div>

            <div>
                <label for="password" class="lbl mb-1 block">Password</label>
                <input id="password" name="password" type="password" required
                       class="field" placeholder="••••••••">
            </div>

            <label class="flex items-center gap-1.5 text-[11px] text-muted">
                <input type="checkbox" name="remember" class="h-3 w-3 border-line-strong accent-[#185fa5]">
                Keep me signed in
            </label>

            <button class="btn btn-primary w-full justify-center py-[6px]">Sign in</button>
        </form>
    </div>

    <p class="mt-3 text-center text-[11px] text-muted">
        No account yet?
        <a href="{{ route('register') }}" class="font-medium text-accent hover:underline">Create one</a>
    </p>
</x-guest-layout>
