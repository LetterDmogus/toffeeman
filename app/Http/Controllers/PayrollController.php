<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    /**
     * Display the payroll list page.
     */
    public function index(Request $request): Response
    {
        $this->authorizePayrollAccess($request);

        return Inertia::render('payroll/Index', [
            'filters' => $request->only(['month', 'year', 'status', 'search']),
        ]);
    }

    /**
     * Show the generate payroll page.
     */
    public function create(Request $request): Response
    {
        $this->authorizePayrollManage($request);

        return Inertia::render('payroll/Generate');
    }

    /**
     * Show a single payroll slip.
     */
    public function show(Request $request, Payroll $payroll): Response
    {
        $this->authorizePayrollAccess($request);

        $payroll->load(['employee.user.position', 'approver', 'transaction']);

        return Inertia::render('payroll/Show', [
            'payroll' => $payroll,
        ]);
    }

    /**
     * Generate payroll slips for all active employees for a given period.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->authorizePayrollManage($request);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $employeeId = $validated['employee_id'] ?? null;

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        // Count working days (Mon–Sat) in the period
        $workingDays = 0;
        $cursor = $periodStart->copy();
        while ($cursor->lte($periodEnd)) {
            if ($cursor->dayOfWeek !== Carbon::SUNDAY) {
                $workingDays++;
            }
            $cursor->addDay();
        }

        $query = Employee::with('user')->where('status', 'active');
        if ($employeeId) {
            $query->where('id', $employeeId);
        }
        $employees = $query->get();
        $generated = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            // Skip if slip already exists for this period
            if (Payroll::where('employee_id', $employee->id)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->exists()) {
                $skipped++;

                continue;
            }

            // Count verified check-in attendances in the period
            $presentDays = Attendance::where('employee_id', $employee->id)
                ->where('type', 'check_in')
                ->where('status', 'verified')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            $absentDays = max(0, $workingDays - $presentDays);
            $baseSalary = (float) $employee->salary;

            $payroll = new Payroll([
                'employee_id' => $employee->id,
                'period_month' => $month,
                'period_year' => $year,
                'base_salary' => $baseSalary,
                'working_days' => $workingDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'apply_absence_deduction' => false,
                'allowance' => 0,
                'deduction_other' => 0,
                'status' => 'draft',
            ]);

            $payroll->recalculate();
            $payroll->save();
            $generated++;
        }

        if ($employeeId && $generated === 0) {
            $existingPayroll = Payroll::where('employee_id', $employeeId)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->first();

            if ($existingPayroll) {
                return redirect()->route('payroll.show', $existingPayroll->id)
                    ->with('info', 'Slip gaji untuk periode ini sudah ada. Anda dialihkan ke halaman pengaturan.');
            }

            return redirect()->route('payroll.index')
                ->with('error', 'Slip gaji karyawan tersebut untuk periode ini sudah ada.');
        }

        if ($employeeId && isset($payroll)) {
            return redirect()->route('payroll.show', $payroll->id)
                ->with('success', 'Slip gaji berhasil dibuat. Silakan atur komponen gaji karyawan di bawah ini.');
        }

        $message = "Berhasil generate {$generated} slip gaji. {$skipped} sudah ada sebelumnya.";

        return redirect()->route('payroll.index')->with('success', $message);
    }

    /**
     * Update allowances, deductions, and absence toggle on a draft payroll.
     */
    public function update(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorizePayrollManage($request);

        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Hanya slip dengan status draft yang bisa diubah.');
        }

        $validated = $request->validate([
            'apply_absence_deduction' => ['required', 'boolean'],
            'allowance' => ['required', 'numeric', 'min:0'],
            'allowance_notes' => ['nullable', 'string', 'max:255'],
            'deduction_other' => ['required', 'numeric', 'min:0'],
            'deduction_notes' => ['nullable', 'string', 'max:255'],
            'bonus' => ['required', 'numeric', 'min:0'],
            'bonus_notes' => ['nullable', 'string', 'max:255'],
            'shift_night_days' => ['required', 'integer', 'min:0'],
            'shift_night_rate' => ['required', 'numeric', 'min:0'],
            'overtime_hours' => ['required', 'integer', 'min:0'],
            'overtime_rate' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payroll->fill($validated);
        $payroll->recalculate();
        $payroll->save();

        return back()->with('success', 'Slip gaji berhasil diperbarui.');
    }

    /**
     * Approve a draft payroll slip.
     */
    public function approve(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorizePayrollManage($request);

        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Hanya slip draft yang bisa di-approve.');
        }

        $payroll->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Slip gaji telah disetujui.');
    }

    /**
     * Mark a payroll as paid and create an expense transaction.
     */
    public function pay(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorizePayrollManage($request);

        if ($payroll->status !== 'approved') {
            return back()->with('error', 'Hanya slip yang sudah di-approve yang bisa dibayar.');
        }

        $payroll->load('employee.user');

        DB::transaction(function () use ($payroll, $request) {
            $periodLabel = Carbon::createFromDate($payroll->period_year, $payroll->period_month, 1)
                ->translatedFormat('F Y');

            $transaction = Transaction::create([
                'transaction_number' => 'TXN-SAL-'.strtoupper(Str::random(6)),
                'type' => 'expense',
                'category' => 'salary',
                'reference_id' => $payroll->id,
                'reference_type' => Payroll::class,
                'user_id' => $request->user()->id,
                'amount' => $payroll->net_salary,
                'payment_method' => 'transfer',
                'payment_status' => 'completed',
                'description' => "Gaji {$payroll->employee->name} — {$periodLabel}",
                'transaction_date' => now(),
            ]);

            $payroll->update([
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_id' => $transaction->id,
            ]);
        });

        return back()->with('success', 'Slip gaji berhasil dibayarkan dan transaksi pengeluaran telah dicatat.');
    }

    /**
     * Delete a draft payroll slip.
     */
    public function destroy(Request $request, Payroll $payroll): RedirectResponse
    {
        $this->authorizePayrollManage($request);

        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Hanya slip draft yang bisa dihapus.');
        }

        $payroll->delete();

        return redirect()->route('payroll.index')->with('success', 'Slip gaji dihapus.');
    }

    /**
     * Export a single payroll slip as a printable HTML page (PDF-ready).
     */
    public function export(Request $request, Payroll $payroll): Response
    {
        $this->authorizePayrollAccess($request);
        $payroll->load(['employee.user.position', 'approver']);

        return Inertia::render('payroll/Export', [
            'payroll' => $payroll,
        ]);
    }

    /**
     * Export the list of payroll slips for a given period as CSV.
     */
    public function exportList(Request $request)
    {
        $this->authorizePayrollAccess($request);

        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $query = Payroll::with(['employee.user.position'])
            ->where('period_month', $month)
            ->where('period_year', $year);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('employee.user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $payrolls = $query->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rekap_slip_gaji_' . $month . '_' . $year . '.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($payrolls) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Karyawan',
                'Jabatan',
                'Bulan',
                'Tahun',
                'Hari Kerja',
                'Hadir',
                'Absen',
                'Gaji Pokok',
                'Tunjangan',
                'Bonus',
                'Gaji Kotor (Gross)',
                'Potongan Absensi',
                'Potongan Lainnya',
                'Gaji Bersih (Net)',
                'Status',
            ]);

            foreach ($payrolls as $p) {
                fputcsv($handle, [
                    $p->employee?->name,
                    $p->employee?->position?->name ?? '—',
                    $p->period_month,
                    $p->period_year,
                    $p->working_days,
                    $p->present_days,
                    $p->absent_days,
                    $p->base_salary,
                    $p->allowance,
                    $p->bonus,
                    $p->gross_salary,
                    $p->deduction_absence,
                    $p->deduction_other,
                    $p->net_salary,
                    strtoupper($p->status),
                ]);
            }

            fclose($handle);
        }, 'rekap_slip_gaji_' . $month . '_' . $year . '.csv', $headers);
    }

    /**
     * Export the yearly trend report as CSV.
     */
    public function exportReport(Request $request)
    {
        $this->authorizePayrollAccess($request);

        $year = $request->integer('year', now()->year);

        $monthlyTrend = Payroll::selectRaw('
                period_month as month,
                SUM(base_salary) as total_base,
                SUM(gross_salary) as total_gross,
                SUM(net_salary) as total_net,
                SUM(deduction_absence + deduction_other) as total_deductions
            ')
            ->where('period_year', $year)
            ->where('status', 'paid')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get()
            ->keyBy('month');

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan_tahunan_gaji_' . $year . '.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($monthlyTrend, $months) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Bulan',
                'Total Gaji Pokok',
                'Total Gaji Kotor (Gross)',
                'Total Potongan',
                'Total Gaji Bersih (Net)',
            ]);

            foreach ($months as $num => $name) {
                $data = $monthlyTrend->get($num);
                fputcsv($handle, [
                    $name,
                    $data ? $data->total_base : 0,
                    $data ? $data->total_gross : 0,
                    $data ? $data->total_deductions : 0,
                    $data ? $data->total_net : 0,
                ]);
            }

            fclose($handle);
        }, 'laporan_tahunan_gaji_' . $year . '.csv', $headers);
    }
    // ── Private helpers ──────────────────────────────────────────────────────

    private function authorizePayrollAccess(Request $request): void
    {
        $user = $request->user();
        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('payroll-access')) {
            abort(403);
        }
    }

    private function authorizePayrollManage(Request $request): void
    {
        $user = $request->user();
        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('payroll-manage')) {
            abort(403);
        }
    }
}
