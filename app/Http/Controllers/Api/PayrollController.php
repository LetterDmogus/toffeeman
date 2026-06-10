<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    /**
     * Return paginated payroll list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('payroll-access')) {
            abort(403);
        }

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

        $payrolls = $query->latest()->paginate($request->integer('per_page', 20));

        // Append summary for the period
        $summary = [
            'total_gross' => (float) Payroll::where('period_month', $month)->where('period_year', $year)->sum('gross_salary'),
            'total_net' => (float) Payroll::where('period_month', $month)->where('period_year', $year)->sum('net_salary'),
            'total_deductions' => (float) Payroll::where('period_month', $month)->where('period_year', $year)->sum('deduction_absence') +
                (float) Payroll::where('period_month', $month)->where('period_year', $year)->sum('deduction_other'),
            'count_draft' => Payroll::where('period_month', $month)->where('period_year', $year)->where('status', 'draft')->count(),
            'count_approved' => Payroll::where('period_month', $month)->where('period_year', $year)->where('status', 'approved')->count(),
            'count_paid' => Payroll::where('period_month', $month)->where('period_year', $year)->where('status', 'paid')->count(),
        ];

        return response()->json([
            'payrolls' => $payrolls,
            'summary' => $summary,
        ]);
    }

    /**
     * Return payroll reports and analytics.
     */
    public function report(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'superadmin' && ! $user->hasPermissionTo('payroll-access')) {
            abort(403);
        }

        $year = $request->integer('year', now()->year);

        // 1. Monthly trend of payroll costs (Gross & Net) for the selected year
        $monthlyTrend = Payroll::selectRaw('
                period_month as month,
                SUM(base_salary) as total_base,
                SUM(gross_salary) as total_gross,
                SUM(net_salary) as total_net,
                SUM(deduction_absence + deduction_other) as total_deductions
            ')
            ->where('period_year', $year)
            ->where('status', 'paid') // only count actual paid out salaries
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get()
            ->keyBy('month');

        $trend = [];
        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        foreach ($months as $num => $name) {
            $data = $monthlyTrend->get($num);
            $trend[] = [
                'month' => $num,
                'label' => $name,
                'total_base' => $data ? (float) $data->total_base : 0,
                'total_gross' => $data ? (float) $data->total_gross : 0,
                'total_net' => $data ? (float) $data->total_net : 0,
                'total_deductions' => $data ? (float) $data->total_deductions : 0,
            ];
        }

        // 2. Department / Position cost breakdown for the current year
        $positionCosts = Payroll::join('employees', 'payrolls.employee_id', '=', 'employees.id')
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->join('positions', 'users.position_id', '=', 'positions.id')
            ->selectRaw('positions.name as position_name, SUM(payrolls.net_salary) as total_net, COUNT(payrolls.id) as count')
            ->where('payrolls.period_year', $year)
            ->where('payrolls.status', 'paid')
            ->groupBy('positions.name')
            ->orderByDesc('total_net')
            ->get();

        return response()->json([
            'trend' => $trend,
            'position_costs' => $positionCosts,
            'year' => $year,
        ]);
    }

    /**
     * Return list of active employees not yet in a payroll for the given period.
     */
    public function missingEmployees(Request $request): JsonResponse
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $existingIds = Payroll::where('period_month', $month)
            ->where('period_year', $year)
            ->pluck('employee_id');

        $missing = Employee::with('user.position')
            ->where('status', 'active')
            ->whereNotIn('id', $existingIds)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position?->name,
                'salary' => (float) $e->salary,
            ]);

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $workingDays = 0;
        $cursor = $periodStart->copy();
        while ($cursor->lte($periodEnd)) {
            if ($cursor->dayOfWeek !== Carbon::SUNDAY) {
                $workingDays++;
            }
            $cursor->addDay();
        }

        return response()->json([
            'employees' => $missing,
            'working_days' => $workingDays,
            'period_label' => $periodStart->translatedFormat('F Y'),
        ]);
    }
}
