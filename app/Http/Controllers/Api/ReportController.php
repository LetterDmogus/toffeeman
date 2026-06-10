<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $hourExpr = $isSqlite
                ? "CAST(strftime('%H', transaction_date) AS INTEGER)"
                : 'HOUR(transaction_date)';

            $rawTrend = DB::table('transactions')
                ->whereNull('deleted_at')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->selectRaw("{$hourExpr} as hour,
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
        } elseif ($startDate->diffInDays($endDate) > 90) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            $monthExpr = $isSqlite
                ? "strftime('%Y-%m', transaction_date)"
                : "DATE_FORMAT(transaction_date, '%Y-%m')";

            $rawTrend = DB::table('transactions')
                ->whereNull('deleted_at')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->selectRaw("{$monthExpr} as month,
                             SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                             SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            $current = $startDate->copy();
            $rawTrendMap = [];
            foreach ($rawTrend as $row) {
                $rawTrendMap[$row->month] = [
                    'income' => (float) $row->income,
                    'expense' => (float) $row->expense,
                ];
            }

            while ($current->lte($endDate)) {
                $monthStr = $current->format('Y-m');
                $trend[] = [
                    'label' => $current->translatedFormat('M Y'),
                    'month' => $monthStr,
                    'income' => (float) ($rawTrendMap[$monthStr]['income'] ?? 0),
                    'expense' => (float) ($rawTrendMap[$monthStr]['expense'] ?? 0),
                ];
                $current->addMonth();
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

        // 5. Total Orders & Unique Buyers
        $totalOrders = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $uniqueCustomers = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('COUNT(DISTINCT customer_id) as registered, COUNT(CASE WHEN customer_id IS NULL THEN 1 END) as guests')
            ->first();

        $totalUniqueBuyers = ($uniqueCustomers->registered ?? 0) + ($uniqueCustomers->guests ?? 0);

        return response()->json([
            'kpi' => [
                'total_revenue' => (float) ($kpi->total_revenue ?? 0),
                'total_expense' => (float) ($kpi->total_expense ?? 0),
                'total_transactions' => (int) ($kpi->total_transactions ?? 0),
                'avg_order_value' => (float) ($kpi->avg_order_value ?? 0),
                'net_profit' => (float) (($kpi->total_revenue ?? 0) - ($kpi->total_expense ?? 0)),
                'total_orders' => (int) $totalOrders,
                'unique_buyers' => (int) $totalUniqueBuyers,
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

    /**
     * Get aggregated orders report and order logs.
     */
    public function ordersReport(Request $request)
    {
        $query = Order::with(['items', 'table', 'customer']);

        // Period filter logic
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->string('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->string('end_date'))->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $startDate = Carbon::createFromDate($request->integer('year'), $request->integer('month'), 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($request->filled('month')) {
            $startDate = Carbon::createFromDate(now()->year, $request->integer('month'), 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($request->filled('year')) {
            $startDate = Carbon::createFromDate($request->integer('year'), 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($request->filled('date')) {
            $startDate = Carbon::parse($request->string('date'))->startOfDay();
            $endDate = Carbon::parse($request->string('date'))->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            // Default to today
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Additional filters
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        if ($request->filled('order_type')) {
            $query->where('order_type', $request->string('order_type'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('order_number', 'like', "%{$search}%");
        }

        // CSV Export
        if ($request->boolean('export')) {
            return $this->exportOrdersToCsv($query);
        }

        // Calculate KPI Stats
        $kpiQuery = clone $query;
        $totalOrders = $kpiQuery->count();
        $totalItemsSold = (int) DB::table('order_items')
            ->whereIn('order_id', (clone $query)->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->sum('qty');

        $statusCounts = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $typeCounts = (clone $query)
            ->select('order_type', DB::raw('count(*) as count'))
            ->groupBy('order_type')
            ->pluck('count', 'order_type')
            ->toArray();

        // Top Selling items query for this period
        $topItems = DB::table('order_items')
            ->whereIn('order_id', (clone $query)->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->selectRaw('name, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $orders = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json([
            'kpi' => [
                'total_orders' => $totalOrders,
                'total_items_sold' => $totalItemsSold,
                'status_pending' => $statusCounts['pending'] ?? 0,
                'status_processing' => $statusCounts['processing'] ?? 0,
                'status_ready' => $statusCounts['ready'] ?? 0,
                'status_served' => $statusCounts['served'] ?? 0,
                'status_cancelled' => $statusCounts['cancelled'] ?? 0,
                'type_dine_in' => $typeCounts['dine_in'] ?? 0,
                'type_take_away' => $typeCounts['take_away'] ?? 0,
            ],
            'top_items' => $topItems,
            'orders' => $orders,
        ]);
    }

    /**
     * Export query to CSV file format.
     */
    protected function exportOrdersToCsv($query)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rekap_pesanan_'.now()->format('Y-m-d_H-i-s').'.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'ID Pesanan',
                'Nomor Pesanan',
                'Karyawan / Pelanggan',
                'Meja',
                'Tipe Pesanan',
                'Subtotal (Gross)',
                'Diskon',
                'Pajak',
                'Total Bersih (Net)',
                'Metode Bayar',
                'Status Pembayaran',
                'Status Hidangan',
                'Waktu Pemesanan',
            ]);

            $query->chunk(200, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    $customerName = $order->customer?->name ?? 'Pelanggan Umum / Guest';
                    $tableName = $order->table?->number ? 'Meja '.$order->table->number : '—';
                    $typeLabel = $order->order_type === 'dine_in' ? 'Dine In' : 'Take Away';
                    $paymentStatusLabel = $order->payment_status === 'paid' ? 'Lunas' : 'Belum Lunas';

                    $statusLabel = match ($order->status) {
                        'pending' => 'Menunggu',
                        'processing' => 'Dimasak',
                        'ready' => 'Siap Saji',
                        'served' => 'Selesai',
                        'cancelled' => 'Batal',
                        default => $order->status,
                    };

                    fputcsv($handle, [
                        $order->id,
                        $order->order_number,
                        $customerName,
                        $tableName,
                        $typeLabel,
                        $order->total_amount,
                        $order->discount_amount,
                        $order->tax_amount,
                        $order->final_amount,
                        strtoupper($order->payment_method),
                        $paymentStatusLabel,
                        $statusLabel,
                        $order->created_at?->format('Y-m-d H:i:s') ?? '—',
                    ]);
                }
            });

            fclose($handle);
        }, 'rekap_pesanan_'.now()->format('Y-m-d_H-i-s').'.csv', $headers);
    }
}
