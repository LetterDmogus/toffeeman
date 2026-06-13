<?php

namespace App\Exports;

use App\Models\Payroll;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PayrollReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected int $year
    ) {}

    /**
     * @return Collection
     */
    public function collection()
    {
        $monthlyTrend = Payroll::selectRaw('
                period_month as month,
                SUM(base_salary) as total_base,
                SUM(gross_salary) as total_gross,
                SUM(net_salary) as total_net,
                SUM(deduction_absence + deduction_other) as total_deductions
            ')
            ->where('period_year', $this->year)
            ->where('status', 'paid')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get()
            ->keyBy('month');

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $collection = collect();
        foreach ($months as $num => $name) {
            $data = $monthlyTrend->get($num);
            $collection->push((object) [
                'month_name' => $name,
                'total_base' => $data ? (float) $data->total_base : 0,
                'total_gross' => $data ? (float) $data->total_gross : 0,
                'total_deductions' => $data ? (float) $data->total_deductions : 0,
                'total_net' => $data ? (float) $data->total_net : 0,
            ]);
        }

        return $collection;
    }

    public function headings(): array
    {
        return [
            'Bulan',
            'Total Gaji Pokok (IDR)',
            'Total Gaji Kotor (Gross - IDR)',
            'Total Potongan (IDR)',
            'Total Gaji Bersih (Net - IDR)',
        ];
    }

    /**
     * @param  object  $row
     */
    public function map($row): array
    {
        return [
            $row->month_name,
            $row->total_base,
            $row->total_gross,
            $row->total_deductions,
            $row->total_net,
        ];
    }
}
