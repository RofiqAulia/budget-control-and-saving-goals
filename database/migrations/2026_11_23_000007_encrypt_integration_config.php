<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Integration configs hold bot tokens and service-account private keys,
     * so they must not sit in the database as readable JSON.
     */
    public function up(): void
    {
        // Must run after create_integrations_table; the filename orders it last.
        if (! Schema::hasTable('integrations')) {
            return;
        }

        // Ciphertext is not valid JSON, so the column has to become TEXT first.
        Schema::table('integrations', function (Blueprint $table) {
            $table->text('config')->nullable()->change();
        });

        DB::table('integrations')->orderBy('id')->each(function ($row) {
            if (blank($row->config)) {
                return;
            }

            $decoded = json_decode((string) $row->config, true);

            if (! is_array($decoded)) {
                return;
            }

            DB::table('integrations')
                ->where('id', $row->id)
                ->update(['config' => Crypt::encryptString(json_encode($decoded))]);
        });
    }

    public function down(): void
    {
        DB::table('integrations')->orderBy('id')->each(function ($row) {
            if (blank($row->config)) {
                return;
            }

            try {
                $plain = Crypt::decryptString((string) $row->config);
            } catch (\Throwable) {
                return;
            }

            DB::table('integrations')
                ->where('id', $row->id)
                ->update(['config' => $plain]);
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->json('config')->nullable()->change();
        });
    }
};
