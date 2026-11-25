<?php

namespace App\Providers;

use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @money($value) => Rp5,400,000
        Blade::directive('money', fn (string $expression) => "<?php echo \\App\\Support\\Money::format({$expression}); ?>");

        // @compact($value) => 5.4M
        Blade::directive('compact', fn (string $expression) => "<?php echo \\App\\Support\\Money::compact({$expression}); ?>");
    }
}
