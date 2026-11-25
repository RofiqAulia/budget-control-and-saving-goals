<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Services\GoogleSheetsService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $report = ReportService::for($request->user()->id);

        $income = $report->hierarchicalReport($period, 'income');
        $expense = $report->hierarchicalReport($period, 'expense');
        $summary = $report->monthlySummary($period);

        return view('reports.index', [
            'period' => $period,
            'income' => $income,
            'expense' => $expense,
            'summary' => $summary,
            'incomeTotal' => $income->sum('total'),
            'expenseTotal' => $expense->sum('total'),
            'sheetRows' => $report->spreadsheetRows($period),
            'sheets' => Integration::forProvider($request->user()->id, Integration::GOOGLE_SHEETS),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $period = $this->resolvePeriod($request);
        $rows = ReportService::for($request->user()->id)->spreadsheetRows($period);

        $filename = "finance-report-{$period}.csv";

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Type', 'Group', 'Category', 'Amount', 'Group Total']);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function syncToSheets(Request $request): RedirectResponse
    {
        $period = $this->resolvePeriod($request);
        $integration = Integration::forProvider($request->user()->id, Integration::GOOGLE_SHEETS);

        $credentials = (string) $integration->configValue('credentials', '');
        $spreadsheetId = (string) $integration->configValue('spreadsheet_id', '');
        $sheetName = $integration->configValue('sheet_name');

        if ($credentials === '' || $spreadsheetId === '') {
            return back()->with('error', 'Add your service account JSON and spreadsheet ID in Integrations first.');
        }

        try {
            $service = new GoogleSheetsService($credentials, $spreadsheetId, $sheetName ?: null);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        // Full export: every transaction, then a monthly summary block.
        $report = ReportService::for($request->user()->id);

        $rows = array_merge(
            $report->ledgerRows(),
            [['']],
            [['MONTHLY SUMMARY']],
            $report->monthlyTotalsRows(),
        );

        // Google expects strings or numbers; blank cells stay empty strings.
        $payload = array_map(
            fn (array $row) => array_map(fn ($cell) => is_float($cell) ? round($cell, 2) : (string) $cell, $row),
            $rows
        );

        $result = $service->push($payload);

        $integration->update([
            'is_active' => $result['ok'] ? true : $integration->is_active,
            'last_synced_at' => $result['ok'] ? now() : $integration->last_synced_at,
            'last_error' => $result['ok'] ? null : $result['message'],
        ]);

        return back()->with(
            $result['ok'] ? 'status' : 'error',
            $result['message']
        );
    }

    private function resolvePeriod(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)
            ? $period
            : Carbon::now()->format('Y-m');
    }
}
