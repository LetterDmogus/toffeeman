<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get aggregated reporting data for dashboard charts and metrics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->string('end_date'))->endOfDay()
            : now()->endOfDay();

        // 1. KPI Aggregations
        $kpi = DB::table('transactions')
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->selectRaw("
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
                COUNT(id) as total_transactions,
                AVG(CASE WHEN type = 'income' THEN amount ELSE NULL END) as avg_order_value
            ")
            ->first();

        // 2. Sales Trend (Hourly for single day, Daily for multi-day)
        $isSingleDay = $startDate->toDateString() === $endDate->toDateString();
        $trend = [];

        if ($isSingleDay) {
            $rawTrend = DB::table('transactions')
                ->whereNull('deleted_at')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->selectRaw("HOUR(transaction_date) as hour,
                             SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                             SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense")
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            $rawTrendMap = [];
            foreach ($rawTrend as $row) {
                $rawTrendMap[$row->hour] = [
                    'income' => (float) $row->income,
                    'expense' => (float) $row->expense,
                ];
            }
            for ($h = 0; $h < 24; $h++) {
                $trend[] = [
                    'label' => sprintf('%02d:00', $h),
                    'income' => (float) ($rawTrendMap[$h]['income'] ?? 0),
                    'expense' => (float) ($rawTrendMap[$h]['expense'] ?? 0),
                ];
            }
        } else {
            $rawTrend = DB::table('transactions')
                ->whereNull('deleted_at')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->selectRaw("DATE(transaction_date) as date,
                             SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                             SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense")
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $current = $startDate->copy();
            $rawTrendMap = [];
            foreach ($rawTrend as $row) {
                $rawTrendMap[$row->date] = [
                    'income' => (float) $row->income,
                    'expense' => (float) $row->expense,
                ];
            }

            while ($current->lte($endDate)) {
                $dateStr = $current->format('Y-m-d');
                $trend[] = [
                    'label' => $current->translatedFormat('d M'),
                    'date' => $dateStr,
                    'income' => (float) ($rawTrendMap[$dateStr]['income'] ?? 0),
                    'expense' => (float) ($rawTrendMap[$dateStr]['expense'] ?? 0),
                ];
                $current->addDay();
            }
        }

        // 3. Payment Methods Distribution
        $paymentMethods = DB::table('transactions')
            ->whereNull('deleted_at')
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(id) as count')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($item) => [
                'payment_method' => $item->payment_method ?: 'cash',
                'total' => (float) $item->total,
                'count' => (int) $item->count,
            ]);

        // 4. Top Selling Menu Items
        $topItems = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNull('orders.deleted_at')
            ->where('orders.payment_status', 'paid')
            ->where('order_items.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->selectRaw('order_items.name, SUM(order_items.qty) as total_qty, SUM(order_items.subtotal) as total_revenue')
            ->groupBy('order_items.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return response()->json([
            'kpi' => [
                'total_revenue' => (float) ($kpi->total_revenue ?? 0),
                'total_expense' => (float) ($kpi->total_expense ?? 0),
                'total_transactions' => (int) ($kpi->total_transactions ?? 0),
                'avg_order_value' => (float) ($kpi->avg_order_value ?? 0),
                'net_profit' => (float) (($kpi->total_revenue ?? 0) - ($kpi->total_expense ?? 0)),
            ],
            'trend' => $trend,
            'payment_methods' => $paymentMethods,
            'top_items' => $topItems,
        ]);
    }

    /**
     * Get detailed transaction list for the ledger table.
     */
    public function transactions(Request $request): JsonResponse
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->string('end_date'))->endOfDay()
            : now()->endOfDay();

        $query = Transaction::with(['user'])
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(fn ($q) => $q->where('transaction_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }

        $transactions = $query->latest('transaction_date')->paginate($request->integer('per_page', 20));

        return response()->json($transactions);
    }
}
