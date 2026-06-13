<?php

namespace App\Exports;

use App\Models\Payroll;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PayrollListExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected int $month,
        protected int $year,
        protected ?string $status = null,
        protected ?string $search = null
    ) {}

    /**
     * @return Collection
     */
    public function collection()
    {
        $query = Payroll::with(['employee.user.position'])
            ->where('period_month', $this->month)
            ->where('period_year', $this->year);

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->search) {
            $query->whereHas('employee.user', function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            });
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return [
            'Nama Karyawan',
            'Jabatan',
            'Periode Bulan',
            'Periode Tahun',
            'Hari Kerja',
            'Hadir',
            'Absen',
            'Gaji Pokok (IDR)',
            'Tunjangan (IDR)',
            'Bonus (IDR)',
            'Gaji Kotor (IDR)',
            'Potongan Absensi (IDR)',
            'Potongan Lainnya (IDR)',
            'Gaji Bersih (IDR)',
            'Status',
        ];
    }

    /**
     * @param  Payroll  $row
     */
    public function map($row): array
    {
        return [
            $row->employee?->name,
            $row->employee?->position?->name ?? '—',
            $row->period_month,
            $row->period_year,
            $row->working_days,
            $row->present_days,
            $row->absent_days,
            (float) $row->base_salary,
            (float) $row->allowance,
            (float) $row->bonus,
            (float) $row->gross_salary,
            (float) $row->deduction_absence,
            (float) $row->deduction_other,
            (float) $row->net_salary,
            strtoupper($row->status),
        ];
    }
}
