<x-guest-layout title="Create account">
    <div class="panel p-4">
        <h1 class="text-[13px] font-medium tracking-tight">Create account</h1>
        <p class="mt-0.5 text-[11px] text-muted">Your category tree is seeded automatically.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-4 space-y-2.5">
            @csrf

            <div>
                <label for="name" class="lbl mb-1 block">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                       class="field" placeholder="Your name">
            </div>

            <div>
                <label for="email" class="lbl mb-1 block">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required
                       class="field" placeholder="you@example.com">
            </div>

            <div>
                <label for="password" class="lbl mb-1 block">Password</label>
                <input id="password" name="password" type="password" required
                       class="field" placeholder="At least 8 characters">
            </div>

            <div>
                <label for="password_confirmation" class="lbl mb-1 block">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="field" placeholder="Repeat password">
            </div>

            <button class="btn btn-primary w-full justify-center py-[6px]">Create account</button>
        </form>
    </div>

    <p class="mt-3 text-center text-[11px] text-muted">
        Already registered?
        <a href="{{ route('login') }}" class="font-medium text-accent hover:underline">Sign in</a>
    </p>
</x-guest-layout>
