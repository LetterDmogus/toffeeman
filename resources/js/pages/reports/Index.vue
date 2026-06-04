<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import { 
    BarChart3, 
    Calendar, 
    Download, 
    Printer, 
    TrendingUp, 
    DollarSign, 
    Receipt, 
    ShoppingBag, 
    Search,
    RefreshCw,
    ChevronLeft,
    ChevronRight,
    ArrowUpRight,
    ArrowDownRight,
    Loader2
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, reports } from '@/routes';
import Chart from 'chart.js/auto';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard().url },
            { title: 'Laporan Keuangan', href: '#' }
        ]
    }
});

// Date Range Filter
const isMounted = ref(false);
const startDate = ref('');
const endDate = ref('');

const reportType = ref('monthly'); // 'daily', 'weekly', 'monthly'
const selectedDate = ref(new Date().toISOString().split('T')[0]);
const selectedMonth = ref(new Date().toISOString().slice(0, 7)); // YYYY-MM
const selectedWeek = ref('1');
const selectedMonthOnly = ref(new Date().toISOString().slice(0, 7)); // YYYY-MM
const financialView = ref('all'); // 'all' (Gabungan), 'income' (Pemasukan), 'expense' (Pengeluaran)

const updateDateRange = () => {
    if (reportType.value === 'daily') {
        startDate.value = selectedDate.value;
        endDate.value = selectedDate.value;
    } else if (reportType.value === 'weekly') {
        const [year, month] = selectedMonth.value.split('-').map(Number);
        const week = Number(selectedWeek.value);
        
        let startDay = 1 + (week - 1) * 7;
        let endDay = startDay + 6;
        
        const lastDayOfMonth = new Date(year, month, 0).getDate();
        if (startDay > lastDayOfMonth) {
            startDay = lastDayOfMonth;
        }
        if (endDay > lastDayOfMonth) {
            endDay = lastDayOfMonth;
        }
        
        const start = new Date(year, month - 1, startDay);
        const end = new Date(year, month - 1, endDay);
        
        const pad = (n: number) => n.toString().padStart(2, '0');
        startDate.value = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`;
        endDate.value = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}`;
    } else if (reportType.value === 'monthly') {
        const [year, month] = selectedMonthOnly.value.split('-').map(Number);
        const first = new Date(year, month - 1, 1);
        const last = new Date(year, month, 0);
        
        const pad = (n: number) => n.toString().padStart(2, '0');
        startDate.value = `${first.getFullYear()}-${pad(first.getMonth() + 1)}-${pad(first.getDate())}`;
        endDate.value = `${last.getFullYear()}-${pad(last.getMonth() + 1)}-${pad(last.getDate())}`;
    }
};

// Initialize dates
updateDateRange();

// Dashboard Data
const loadingDashboard = ref(false);
const kpi = ref({
    total_revenue: 0,
    total_expense: 0,
    total_transactions: 0,
    avg_order_value: 0,
    net_profit: 0
});
const trend = ref<any[]>([]);
const paymentMethods = ref<any[]>([]);
const topItems = ref<any[]>([]);

// Transaction Table
const loadingTransactions = ref(false);
const transactions = ref<any[]>([]);
const search = ref('');
const filterType = ref('');
const filterPayment = ref('');
const pagination = ref({
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
    total: 0
});

// Extra Report Controls
const chartType = ref<'line' | 'bar'>('line');
const visibleColumns = ref<Record<string, boolean>>({
    transaction_number: true,
    type: true,
    category: true,
    description: true,
    payment_method: true,
    amount: true,
    transaction_date: true,
    user: true
});
const showColumnDropdown = ref(false);

// Chart.js instances
let trendChartInstance: any = null;
let paymentChartInstance: any = null;
let itemsChartInstance: any = null;

// Format Rupiah Helper
const formatIDR = (value: number) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};

// Watchers
watch([reportType, selectedDate, selectedMonth, selectedWeek, selectedMonthOnly], () => {
    updateDateRange();
    fetchReportData();
});

watch(financialView, (newVal) => {
    filterType.value = newVal === 'all' ? '' : newVal;
    renderTrendChart();
    fetchTransactions(1);
});

watch(filterType, (newVal) => {
    const targetView = newVal === '' ? 'all' : newVal;
    if (financialView.value !== targetView) {
        financialView.value = targetView;
    }
});

// Fetch Dashboard & Transactions Data
const fetchReportData = async () => {
    if (!isMounted.value) return;
    loadingDashboard.value = true;
    try {
        const queryParams = new URLSearchParams({
            start_date: startDate.value,
            end_date: endDate.value
        });
        const res = await fetch(`/api/reports/dashboard?${queryParams.toString()}`);
        if (res.ok) {
            const data = await res.json();
            kpi.value = data.kpi;
            trend.value = data.trend;
            paymentMethods.value = data.payment_methods;
            topItems.value = data.top_items;
            
            // Re-render Charts
            renderTrendChart();
            renderPaymentChart();
            renderItemsChart();
        }
    } catch (e) {
        console.error('Error loading dashboard data:', e);
    } finally {
        loadingDashboard.value = false;
    }

    // Refresh transactions table too
    fetchTransactions(1);
};

const fetchTransactions = async (page = 1) => {
    loadingTransactions.value = true;
    try {
        const queryParams = new URLSearchParams({
            start_date: startDate.value,
            end_date: endDate.value,
            page: String(page),
            search: search.value,
            type: filterType.value,
            payment_method: filterPayment.value
        });
        const res = await fetch(`/api/reports/transactions?${queryParams.toString()}`);
        if (res.ok) {
            const data = await res.json();
            transactions.value = data.data;
            pagination.value = {
                current_page: data.current_page,
                last_page: data.last_page,
                from: data.from || 0,
                to: data.to || 0,
                total: data.total || 0
            };
        }
    } catch (e) {
        console.error('Error fetching transactions:', e);
    } finally {
        loadingTransactions.value = false;
    }
};

const changePage = (page: number) => {
    if (page < 1 || page > pagination.value.last_page) return;
    fetchTransactions(page);
};

// Watch chartType to re-render trend
watch(chartType, () => {
    renderTrendChart();
});

// Render Charts Logic
const renderTrendChart = () => {
    const ctx: any = document.getElementById('trendChart');
    if (!ctx) return;

    if (trendChartInstance) trendChartInstance.destroy();

    const labels = trend.value.map(t => t.label || t.date);
    const values = trend.value.map(t => t.total);

    // Dynamic gradient
    const chartCtx = ctx.getContext('2d');
    const gradient = chartCtx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(219, 39, 119, 0.25)'); // Brand color pink-600
    gradient.addColorStop(1, 'rgba(219, 39, 119, 0)');

    trendChartInstance = new Chart(ctx, {
        type: chartType.value,
        data: {
            labels,
            datasets: [{
                label: 'Pendapatan',
                data: values,
                borderColor: '#db2777', // Brand-600 pink
                borderWidth: chartType.value === 'line' ? 3 : 0,
                backgroundColor: chartType.value === 'line' ? gradient : 'rgba(219, 39, 119, 0.85)',
                fill: true,
                tension: 0.35,
                borderRadius: chartType.value === 'bar' ? 6 : 0,
                pointBackgroundColor: '#db2777',
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#db2777',
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    padding: 12,
                    backgroundColor: '#0f172a',
                    titleFont: { size: 12, weight: 'bold' },
                    bodyFont: { size: 13, weight: '900' },
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return ' ' + formatIDR(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { size: 10, weight: 'bold' } }
                },
                y: {
                    grid: { color: 'rgba(226, 232, 240, 0.4)' },
                    ticks: {
                        color: '#64748b',
                        font: { size: 10, weight: 'bold' },
                        callback: function(value: any) {
                            return value >= 1000000 
                                ? (value / 1000000) + 'jt' 
                                : value >= 1000 ? (value / 1000) + 'rb' : value;
                        }
                    }
                }
            }
        }
    });
};

const renderPaymentChart = () => {
    const ctx = document.getElementById('paymentChart') as HTMLCanvasElement;
    if (!ctx) return;

    if (paymentChartInstance) paymentChartInstance.destroy();

    const methodsMap: Record<string, string> = {
        cash: 'Tunai',
        qris: 'QRIS',
        transfer: 'Transfer Bank'
    };

    const labels = paymentMethods.value.map(p => methodsMap[p.payment_method] || p.payment_method);
    const values = paymentMethods.value.map(p => p.total);

    paymentChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#10b981', // emerald-500 for cash
                    '#6366f1', // indigo-500 for qris
                    '#db2777'  // pink-600 for bank
                ],
                borderWidth: 4,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#475569',
                        font: { size: 11, weight: 'bold' },
                        padding: 16,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    padding: 12,
                    backgroundColor: '#0f172a',
                    callbacks: {
                        label: function(context) {
                            return ' ' + formatIDR(context.parsed as number);
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
};

const renderItemsChart = () => {
    const ctx = document.getElementById('itemsChart') as HTMLCanvasElement;
    if (!ctx) return;

    if (itemsChartInstance) itemsChartInstance.destroy();

    const labels = topItems.value.map(item => item.name);
    const values = topItems.value.map(item => item.total_qty);

    itemsChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: '#db2777', // Brand color pink-600
                borderRadius: 8,
                barThickness: 16
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    padding: 10,
                    backgroundColor: '#0f172a',
                    callbacks: {
                        label: function(context) {
                            return ' Terjual ' + context.parsed.x + ' porsi';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(226, 232, 240, 0.4)' },
                    ticks: { color: '#64748b', font: { size: 10, weight: 'bold' } }
                },
                y: {
                    grid: { display: false },
                    ticks: { color: '#334155', font: { size: 11, weight: 'black' } }
                }
            }
        }
    });
};

// Excel Export Logic (Frontend CSV generation)
const exportToExcel = () => {
    if (transactions.value.length === 0) {
        alert('Tidak ada data transaksi untuk diekspor.');
        return;
    }
    
    // Header
    let csvContent = "No. Transaksi,Tipe,Kategori,Deskripsi,Metode Bayar,Jumlah (Rp),Tanggal,Kasir\r\n";
    
    // Rows
    transactions.value.forEach(t => {
        const typeStr = t.type === 'income' ? 'Pemasukan' : 'Pengeluaran';
        const payStr = t.payment_method ? t.payment_method.toUpperCase() : 'TUNAI';
        const dateStr = new Date(t.transaction_date).toLocaleString('id-ID');
        const userStr = t.user?.name || '—';
        
        // Clean description from comma
        const descStr = t.description ? t.description.replace(/,/g, ' ') : '';
        
        csvContent += `"${t.transaction_number}","${typeStr}","${t.category}","${descStr}","${payStr}",${t.amount},"${dateStr}","${userStr}"\r\n`;
    });
    
    // Trigger download
    const blob = new Blob([new Uint8Array([0xEF, 0xBB, 0xBF]), csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `Laporan_Transaksi_${startDate.value}_s.d._${endDate.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// PDF Print Logic
const triggerPrint = () => {
    window.print();
};

onMounted(() => {
    isMounted.value = true;
    fetchReportData();
});
</script>

<template>
    <Head title="Laporan Keuangan" />

    <div class="p-6 flex flex-col gap-6 overflow-y-auto w-full custom-scrollbar print:p-0 print:bg-white print:text-black">
        
        <!-- 📑 HEADER & TITLE (Plain layout, no container background) -->
        <div class="flex flex-col gap-1 pb-4 print:hidden">
            <h1 class="text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight">Laporan Keuangan</h1>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Pantau pendapatan, pengeluaran, dan tren pesanan restoran</p>
        </div>
        
        <!-- 📅 FILTERS SECTION (Multiple rows, no background container wrapper) -->
        <div class="flex flex-col gap-4 print:hidden">
            <!-- Row 1: Period Selection -->
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-black text-slate-400 uppercase tracking-wider min-w-[120px]">Periode:</span>
                <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl shrink-0">
                    <button
                        type="button"
                        @click="reportType = 'daily'"
                        :class="reportType === 'daily' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition-all"
                    >
                        Harian
                    </button>
                    <button
                        type="button"
                        @click="reportType = 'weekly'"
                        :class="reportType === 'weekly' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition-all"
                    >
                        Mingguan
                    </button>
                    <button
                        type="button"
                        @click="reportType = 'monthly'"
                        :class="reportType === 'monthly' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition-all"
                    >
                        Bulanan
                    </button>
                </div>

                <!-- Date Inputs corresponding to reportType -->
                <div class="flex items-center gap-2">
                    <!-- Harian Picker -->
                    <div v-if="reportType === 'daily'" class="flex items-center gap-2 animate-in slide-in-from-right-2 duration-150">
                        <Input type="date" v-model="selectedDate" class="h-10 text-xs font-bold w-40" />
                    </div>

                    <!-- Mingguan Picker -->
                    <div v-else-if="reportType === 'weekly'" class="flex items-center gap-2 animate-in slide-in-from-right-2 duration-150">
                        <Input type="month" v-model="selectedMonth" class="h-10 text-xs font-bold w-40" />
                        <select v-model="selectedWeek" class="h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 text-xs font-black text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="1">Minggu 1 (Tgl 1-7)</option>
                            <option value="2">Minggu 2 (Tgl 8-14)</option>
                            <option value="3">Minggu 3 (Tgl 15-21)</option>
                            <option value="4">Minggu 4 (Tgl 22-28)</option>
                            <option value="5">Minggu 5 (Tgl 29+)</option>
                        </select>
                    </div>

                    <!-- Bulanan Picker -->
                    <div v-else-if="reportType === 'monthly'" class="flex items-center gap-2 animate-in slide-in-from-right-2 duration-150">
                        <Input type="month" v-model="selectedMonthOnly" class="h-10 text-xs font-bold w-40" />
                    </div>
                </div>
            </div>

            <!-- Row 2: Financial Type View -->
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-black text-slate-400 uppercase tracking-wider min-w-[120px]">Tipe Laporan:</span>
                <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl shrink-0">
                    <button
                        type="button"
                        @click="financialView = 'all'"
                        :class="financialView === 'all' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition-all"
                    >
                        Gabungan
                    </button>
                    <button
                        type="button"
                        @click="financialView = 'income'"
                        :class="financialView === 'income' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition-all"
                    >
                        Pemasukan
                    </button>
                    <button
                        type="button"
                        @click="financialView = 'expense'"
                        :class="financialView === 'expense' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition-all"
                    >
                        Pengeluaran
                    </button>
                </div>
            </div>

            <!-- Row 3: Action buttons -->
            <div class="flex flex-wrap items-center gap-3 pt-2">
                <span class="text-xs font-black text-slate-400 uppercase tracking-wider min-w-[120px]">Aksi:</span>
                <Button @click="fetchReportData" :disabled="loadingDashboard" variant="outline" class="h-10 px-4 rounded-xl border-slate-200 hover:bg-slate-50 font-black text-xs gap-2">
                    <RefreshCw class="h-3.5 w-3.5" :class="loadingDashboard ? 'animate-spin' : ''" />
                    <span>REFRESH</span>
                </Button>
                
                <Button @click="exportToExcel" variant="outline" class="h-10 px-4 rounded-xl border-emerald-200 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-950/30 dark:text-emerald-400 dark:hover:bg-emerald-950/20 font-black text-xs gap-2">
                    <Download class="h-3.5 w-3.5" />
                    <span>EXCEL</span>
                </Button>
                <Button @click="triggerPrint" class="h-10 px-4 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-black text-xs gap-2 shadow-lg shadow-brand-100 dark:shadow-none">
                    <Printer class="h-3.5 w-3.5" />
                    <span>CETAK LAPORAN</span>
                </Button>
            </div>
        </div>

        <!-- 📑 PRINT-ONLY HEADER -->
        <div class="hidden print:flex flex-col items-center justify-center text-center pb-6 border-b border-slate-300 w-full mb-6">
            <h1 class="text-2xl font-black tracking-tight">LAPORAN BUKU KAS TRANSAKSI RESTORAN</h1>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mt-1">Periode: {{ startDate }} s.d. {{ endDate }}</p>
        </div>

        <!-- 📊 KPI METRIC CARDS (Hidden on Print) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 print:hidden">
            <!-- omzet -->
            <div class="relative overflow-hidden bg-gradient-to-br from-brand-50 to-pink-100/50 dark:from-brand-950/20 dark:to-slate-900 border border-brand-100 dark:border-brand-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
                <div class="space-y-2 relative z-10">
                    <span class="text-[10px] font-black uppercase text-brand-600 dark:text-brand-400 tracking-widest block">Total Pendapatan</span>
                    <h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.total_revenue) }}</h3>
                    <div class="flex items-center gap-1 text-[10px] font-black text-emerald-600">
                        <ArrowUpRight class="h-3.5 w-3.5" />
                        <span>Pemasukan Bersih</span>
                    </div>
                </div>
                <div class="p-3.5 bg-brand-500 rounded-2xl text-white shadow-md shadow-brand-100 dark:shadow-none">
                    <DollarSign class="h-6 w-6" />
                </div>
            </div>

            <!-- expense -->
            <div class="relative overflow-hidden bg-gradient-to-br from-rose-50 to-red-100/50 dark:from-rose-950/15 dark:to-slate-900 border border-rose-100 dark:border-rose-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
                <div class="space-y-2 relative z-10">
                    <span class="text-[10px] font-black uppercase text-rose-600 dark:text-rose-400 tracking-widest block">Total Pengeluaran</span>
                    <h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.total_expense) }}</h3>
                    <div class="flex items-center gap-1 text-[10px] font-black text-rose-500">
                        <ArrowDownRight class="h-3.5 w-3.5" />
                        <span>Operasional / HPP</span>
                    </div>
                </div>
                <div class="p-3.5 bg-rose-500 rounded-2xl text-white shadow-md shadow-rose-100 dark:shadow-none">
                    <TrendingUp class="h-6 w-6" />
                </div>
            </div>

            <!-- profit -->
            <div class="relative overflow-hidden bg-gradient-to-br from-emerald-50 to-teal-100/50 dark:from-emerald-950/15 dark:to-slate-900 border border-emerald-100 dark:border-emerald-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
                <div class="space-y-2 relative z-10">
                    <span class="text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400 tracking-widest block">Laba Bersih</span>
                    <h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.net_profit) }}</h3>
                    <div class="flex items-center gap-1 text-[10px] font-black text-emerald-600">
                        <ArrowUpRight class="h-3.5 w-3.5" />
                        <span>Profit Penjualan</span>
                    </div>
                </div>
                <div class="p-3.5 bg-emerald-500 rounded-2xl text-white shadow-md shadow-emerald-100 dark:shadow-none">
                    <ShoppingBag class="h-6 w-6" />
                </div>
            </div>

            <!-- transactions -->
            <div class="relative overflow-hidden bg-gradient-to-br from-indigo-50 to-blue-100/50 dark:from-indigo-950/15 dark:to-slate-900 border border-indigo-100 dark:border-indigo-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
                <div class="space-y-2 relative z-10">
                    <span class="text-[10px] font-black uppercase text-indigo-600 dark:text-indigo-400 tracking-widest block">Rata-rata Transaksi</span>
                    <h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.avg_order_value) }}</h3>
                    <div class="flex items-center gap-1.5 text-[10px] font-black text-slate-400">
                        <Receipt class="h-3.5 w-3.5 text-indigo-500" />
                        <span>{{ kpi.total_transactions }} Transaksi Tercatat</span>
                    </div>
                </div>
                <div class="p-3.5 bg-indigo-500 rounded-2xl text-white shadow-md shadow-indigo-100 dark:shadow-none">
                    <Receipt class="h-6 w-6" />
                </div>
            </div>
        </div>

        <!-- 📉 GRAPHICS & CHARTS GRID (Hidden on Print) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:hidden">
            <!-- Tren Pendapatan (Line/Bar Chart) -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-3xl shadow-sm flex flex-col min-h-[360px] relative">
                <div v-if="loadingDashboard" class="absolute inset-0 bg-white/60 dark:bg-slate-950/60 z-10 flex items-center justify-center rounded-3xl"><Loader2 class="animate-spin h-8 w-8 text-brand-600" /></div>
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-4">
                        <h3 class="font-black text-slate-800 dark:text-slate-100 text-sm uppercase tracking-wider flex items-center gap-2"><TrendingUp class="h-4 w-4 text-brand-600" /> Tren Pendapatan</h3>
                        <div class="flex items-center bg-slate-100 dark:bg-slate-950 p-1 rounded-xl">
                            <button @click="chartType = 'line'" class="text-[10px] font-black uppercase px-3 py-1 rounded-lg transition-all cursor-pointer" :class="chartType === 'line' ? 'bg-white dark:bg-slate-900 text-brand-600 shadow-sm' : 'text-slate-400 hover:text-slate-600'">Garis</button>
                            <button @click="chartType = 'bar'" class="text-[10px] font-black uppercase px-3 py-1 rounded-lg transition-all cursor-pointer" :class="chartType === 'bar' ? 'bg-white dark:bg-slate-900 text-brand-600 shadow-sm' : 'text-slate-400 hover:text-slate-600'">Batang</button>
                        </div>
                    </div>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">{{ startDate }} s.d. {{ endDate }}</span>
                </div>
                <div class="flex-1 w-full relative">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Sebaran Pembayaran (Doughnut Chart) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-3xl shadow-sm flex flex-col min-h-[360px] relative">
                <div v-if="loadingDashboard" class="absolute inset-0 bg-white/60 dark:bg-slate-950/60 z-10 flex items-center justify-center rounded-3xl"><Loader2 class="animate-spin h-8 w-8 text-brand-600" /></div>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-black text-slate-800 dark:text-slate-100 text-sm uppercase tracking-wider flex items-center gap-2"><BarChart3 class="h-4 w-4 text-indigo-500" /> Sebaran Pembayaran</h3>
                </div>
                <div class="flex-1 w-full relative min-h-[220px]">
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 🏆 TOP 5 MENU (Hidden on Print) -->
        <div class="grid grid-cols-1 gap-6 print:hidden">
            <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-3xl shadow-sm flex flex-col min-h-[260px] relative">
                <div v-if="loadingDashboard" class="absolute inset-0 bg-white/60 dark:bg-slate-950/60 z-10 flex items-center justify-center rounded-3xl"><Loader2 class="animate-spin h-8 w-8 text-brand-600" /></div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-black text-slate-800 dark:text-slate-100 text-sm uppercase tracking-wider flex items-center gap-2"><BarChart3 class="h-4 w-4 text-emerald-500" /> Top 5 Menu Paling Laris</h3>
                </div>
                <div class="flex-1 w-full relative min-h-[160px]">
                    <canvas id="itemsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 🧾 DETAILED TRANSACTIONS LEDGER (Always Printed) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm flex flex-col overflow-hidden print:border-none print:shadow-none">
            <!-- Header & Filter -->
            <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 print:hidden">
                <h3 class="font-black text-slate-800 dark:text-slate-100 text-sm uppercase tracking-wider flex items-center gap-2"><Receipt class="h-4 w-4 text-slate-500" /> Buku Kas / Ledger Transaksi</h3>
                
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto relative">
                    <!-- Search -->
                    <div class="relative w-full md:w-56">
                        <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <Input v-model="search" @input="fetchTransactions(1)" placeholder="Cari No. Transaksi..." class="h-9 pl-9 rounded-xl border-slate-200 text-xs font-bold w-full" />
                    </div>

                    <!-- Type Filter -->
                    <select v-model="filterType" @change="fetchTransactions(1)" class="h-9 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 text-xs font-black text-slate-500 focus:outline-none">
                        <option value="">Semua Tipe</option>
                        <option value="income">Pemasukan (Masuk)</option>
                        <option value="expense">Pengeluaran (Keluar)</option>
                    </select>

                    <!-- Payment Method Filter -->
                    <select v-model="filterPayment" @change="fetchTransactions(1)" class="h-9 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 text-xs font-black text-slate-500 focus:outline-none">
                        <option value="">Semua Metode</option>
                        <option value="cash">Tunai (Cash)</option>
                        <option value="qris">QRIS</option>
                        <option value="transfer">Bank Transfer</option>
                    </select>

                    <!-- Column visibility checkbox -->
                    <div class="relative">
                        <Button @click="showColumnDropdown = !showColumnDropdown" variant="outline" class="h-9 px-3 rounded-xl border-slate-200 text-xs font-black gap-1.5 cursor-pointer">
                            <span>Kolom</span>
                        </Button>
                        <div v-if="showColumnDropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-xl p-3.5 z-20 flex flex-col gap-2 animate-in fade-in slide-in-from-top-2 duration-150">
                            <Label class="text-[9px] font-black uppercase text-slate-400 tracking-wider mb-1 block">Tampilkan Kolom</Label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.transaction_number" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>No. Transaksi</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.type" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>Tipe</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.category" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>Kategori</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.description" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>Keterangan</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.payment_method" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>Metode</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.amount" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>Jumlah</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.transaction_date" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>Tanggal</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
                                <input type="checkbox" v-model="visibleColumns.user" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
                                <span>Kasir</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="flex-1 overflow-x-auto w-full custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/20">
                            <th v-if="visibleColumns.transaction_number" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">No. Transaksi</th>
                            <th v-if="visibleColumns.type" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Tipe</th>
                            <th v-if="visibleColumns.category" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Kategori</th>
                            <th v-if="visibleColumns.description" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Keterangan / Deskripsi</th>
                            <th v-if="visibleColumns.payment_method" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest text-center">Metode</th>
                            <th v-if="visibleColumns.amount" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest text-right">Jumlah</th>
                            <th v-if="visibleColumns.transaction_date" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Tanggal</th>
                            <th v-if="visibleColumns.user" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest print:hidden">Kasir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 relative">
                        <tr v-if="loadingTransactions" class="h-28"><td colspan="8" class="text-center"><Loader2 class="animate-spin h-6 w-6 text-brand-600 inline-block mr-2" /><span class="text-xs font-black text-slate-400 uppercase">Memuat data kas...</span></td></tr>
                        <tr v-else-if="transactions.length === 0" class="h-28"><td colspan="8" class="text-center text-xs font-black text-slate-400 uppercase">Tidak ada transaksi tercatat</td></tr>
                        <tr v-for="t in transactions" :key="t.id" v-else class="hover:bg-slate-50/50 dark:hover:bg-slate-950/20 transition-all font-bold text-xs text-slate-600 dark:text-slate-300">
                            <td v-if="visibleColumns.transaction_number" class="px-6 py-4 font-black text-slate-800 dark:text-slate-100 select-all font-mono">{{ t.transaction_number }}</td>
                            <td v-if="visibleColumns.type" class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider" :class="t.type === 'income' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20' : 'bg-rose-50 text-rose-600 dark:bg-rose-950/20'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="t.type === 'income' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                    {{ t.type === 'income' ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td v-if="visibleColumns.category" class="px-6 py-4 capitalize font-semibold">{{ t.category.replace('_', ' ') }}</td>
                            <td v-if="visibleColumns.description" class="px-6 py-4 max-w-xs truncate font-medium text-slate-500 dark:text-slate-400" :title="t.description">{{ t.description || '—' }}</td>
                            <td v-if="visibleColumns.payment_method" class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded-md border text-[9px] font-black uppercase" :class="t.payment_method === 'qris' ? 'border-indigo-200 bg-indigo-50 text-indigo-600 dark:border-indigo-950/50 dark:bg-indigo-950/30' : (t.payment_method === 'transfer' ? 'border-brand-200 bg-brand-50 text-brand-600 dark:border-brand-950/50 dark:bg-brand-950/30' : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900')">
                                    {{ t.payment_method || 'TUNAI' }}
                                </span>
                            </td>
                            <td v-if="visibleColumns.amount" class="px-6 py-4 text-right font-black text-sm" :class="t.type === 'income' ? 'text-slate-800 dark:text-slate-100' : 'text-rose-500'">
                                {{ t.type === 'income' ? '+' : '-' }}{{ formatIDR(t.amount) }}
                            </td>
                            <td v-if="visibleColumns.transaction_date" class="px-6 py-4 font-semibold text-slate-500 select-all">{{ new Date(t.transaction_date).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) }}</td>
                            <td v-if="visibleColumns.user" class="px-6 py-4 font-medium text-slate-400 print:hidden">{{ t.user?.name || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="pagination.total > 0" class="flex items-center justify-between border-t border-slate-100 p-4 dark:border-slate-800 print:hidden">
                <span class="text-xs font-semibold text-slate-400">
                    Menampilkan <span class="font-bold text-slate-700 dark:text-slate-300">{{ pagination.from }}</span> sampai <span class="font-bold text-slate-700 dark:text-slate-300">{{ pagination.to }}</span> dari <span class="font-bold text-slate-700 dark:text-slate-300">{{ pagination.total }}</span> transaksi
                </span>
                
                <div class="flex items-center gap-1">
                    <Button 
                        variant="outline" 
                        size="icon" 
                        class="h-8 w-8 rounded-lg cursor-pointer" 
                        :disabled="pagination.current_page === 1 || loadingTransactions"
                        @click="changePage(1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <Button 
                        variant="outline" 
                        size="icon" 
                        class="h-8 w-8 rounded-lg cursor-pointer" 
                        :disabled="pagination.current_page === 1 || loadingTransactions"
                        @click="changePage(pagination.current_page - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    
                    <span class="text-xs font-black text-slate-500 px-3 select-none">
                        {{ pagination.current_page }} / {{ pagination.last_page }}
                    </span>
                    
                    <Button 
                        variant="outline" 
                        size="icon" 
                        class="h-8 w-8 rounded-lg cursor-pointer" 
                        :disabled="pagination.current_page === pagination.last_page || loadingTransactions"
                        @click="changePage(pagination.current_page + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button 
                        variant="outline" 
                        size="icon" 
                        class="h-8 w-8 rounded-lg cursor-pointer" 
                        :disabled="pagination.current_page === pagination.last_page || loadingTransactions"
                        @click="changePage(pagination.last_page)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* CSS khusus cetak print layout agar rapi menjadi dokumen PDF resmi */
@media print {
    body, html, #app {
        background-color: white !important;
        color: black !important;
        width: 100% !important;
        height: auto !important;
    }
    
    /* Sembunyikan TOTAL seluruh navbar, sidebar, header, dan panel kontrol */
    header, aside, nav, [role="navigation"], button, .sidebar, 
    [data-sidebar="sidebar"], [data-sidebar="header"], .print\:hidden,
    .bg-brand-600, .bg-slate-900 {
        display: none !important;
    }
    
    /* Sesuaikan main content area agar full-width */
    main, .overflow-y-auto, .flex-col, .rounded-3xl, .shadow-sm {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 10px !important; /* Slightly smaller text for print fitting */
    }
    
    th, td {
        padding: 8px 12px !important;
        border-bottom: 1px solid #cbd5e1 !important;
    }
}
</style>
