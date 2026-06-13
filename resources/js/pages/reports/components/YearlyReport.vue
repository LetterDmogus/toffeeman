<script setup lang="ts">
import Chart from "chart.js/auto";
import {
	ArrowDownRight,
	ArrowUpRight,
	BarChart3,
	ChevronLeft,
	ChevronRight,
	DollarSign,
	Download,
	Eye,
	Loader2,
	Printer,
	Receipt,
	RefreshCw,
	Search,
	ShoppingBag,
	TrendingUp,
} from "lucide-vue-next";
import { onMounted, ref, watch } from "vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
	Dialog,
	DialogContent,
	DialogHeader,
	DialogTitle,
	DialogFooter,
} from "@/components/ui/dialog";

// Year Selection
const currentYear = new Date().getFullYear();
const selectedYear = ref(currentYear);
const startDate = ref("");
const endDate = ref("");
const showYearlyExpense = ref(true);
const financialView = ref("all");

const updateDateRange = () => {
	startDate.value = `${selectedYear.value}-01-01`;
	endDate.value = `${selectedYear.value}-12-31`;
};

updateDateRange();

// Data States
const loadingDashboard = ref(false);
const loadingTransactions = ref(false);
const kpi = ref({
	total_revenue: 0,
	total_expense: 0,
	total_transactions: 0,
	avg_order_value: 0,
	net_profit: 0,
});
const trend = ref<any[]>([]);
const paymentMethods = ref<any[]>([]);
const topItems = ref<any[]>([]);
const transactions = ref<any[]>([]);
const search = ref("");
const filterType = ref("");
const filterPayment = ref("");
const chartType = ref<"line" | "bar">("bar");

const pagination = ref({
	current_page: 1,
	last_page: 1,
	from: 0,
	to: 0,
	total: 0,
});

const visibleColumns = ref<Record<string, boolean>>({
	transaction_number: true,
	type: true,
	category: true,
	description: false,
	payment_method: true,
	amount: true,
	transaction_date: true,
	user: false,
});
const showColumnDropdown = ref(false);

const showDetailDialog = ref(false);
const selectedTransaction = ref<any>(null);

const openDetail = (row: any) => {
	selectedTransaction.value = row;
	showDetailDialog.value = true;
};

const categoryLabels: Record<string, string> = {
	electricity: "Listrik",
	marketing: "Promosi / Iklan",
	water: "Air (PAM)",
	internet: "Internet & Telepon",
	rent: "Sewa Tempat",
	maintenance: "Pemeliharaan / Perbaikan",
	office_supplies: "Alat Tulis Kantor",
	other: "Lainnya",
	payroll: "Gaji Karyawan",
	inventory_purchase: "Pembelian Inventaris",
	sales: "Penjualan / Pesanan",
	order: "Pesanan",
};

const paymentMethodLabels: Record<string, string> = {
	cash: "Tunai (Cash)",
	bank_transfer: "Transfer Bank",
	e_wallet: "E-Wallet",
	card: "Kartu Debit/Kredit",
	qris: "QRIS",
	transfer: "Transfer Bank",
};

let trendChartInstance: any = null;
let paymentChartInstance: any = null;

const formatIDR = (value: number) => {
	return new Intl.NumberFormat("id-ID", {
		style: "currency",
		currency: "IDR",
		minimumFractionDigits: 0,
		maximumFractionDigits: 0,
	}).format(value);
};

const fetchReportData = async () => {
	loadingDashboard.value = true;

	try {
		const queryParams = new URLSearchParams({
			start_date: startDate.value,
			end_date: endDate.value,
		});
		const res = await fetch(`/api/reports/dashboard?${queryParams.toString()}`);

		if (res.ok) {
			const data = await res.json();
			kpi.value = data.kpi;
			trend.value = data.trend;
			paymentMethods.value = data.payment_methods;
			topItems.value = data.top_items;

			renderCharts();
		}
	} catch (e) {
		console.error("Error loading yearly report data:", e);
	} finally {
		loadingDashboard.value = false;
	}

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
			payment_method: filterPayment.value,
		});
		const res = await fetch(
			`/api/reports/transactions?${queryParams.toString()}`,
		);

		if (res.ok) {
			const data = await res.json();
			transactions.value = data.data;
			pagination.value = {
				current_page: data.current_page,
				last_page: data.last_page,
				from: data.from || 0,
				to: data.to || 0,
				total: data.total || 0,
			};
		}
	} catch (e) {
		console.error("Error fetching transactions:", e);
	} finally {
		loadingTransactions.value = false;
	}
};

const changePage = (page: number) => {
	if (page < 1 || page > pagination.value.last_page) {
		return;
	}
	fetchTransactions(page);
};

const renderCharts = () => {
	renderTrendChart();
	renderPaymentChart();
};

const renderTrendChart = () => {
	const ctx = document.getElementById("trendChartYearly") as HTMLCanvasElement;
	if (!ctx) return;

	if (trendChartInstance) trendChartInstance.destroy();

	const labels = trend.value.map((t) => t.label);
	const incomes = trend.value.map((t) => t.income);
	const expenses = trend.value.map((t) => t.expense);

	trendChartInstance = new Chart(ctx, {
		type: chartType.value,
		data: {
			labels,
			datasets: [
				{
					label: "Pendapatan",
					data: incomes,
					backgroundColor: "rgba(219, 39, 119, 0.85)",
					borderColor: "#db2777",
					borderWidth: 1,
					borderRadius: 4,
				},
				{
					label: "Pengeluaran",
					data: expenses,
					backgroundColor: "rgba(244, 63, 94, 0.85)",
					borderColor: "#f43f5e",
					borderWidth: 1,
					borderRadius: 4,
				},
			],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { display: true, position: "top" },
				tooltip: {
					callbacks: {
						label: (context) => " " + context.dataset.label + ": " + formatIDR(context.parsed.y),
					},
				},
			},
			scales: {
				x: { grid: { display: false } },
				y: {
					ticks: {
						callback: (value: any) =>
							value >= 1000000 ? value / 1000000 + "jt" : value >= 1000 ? value / 1000 + "rb" : value,
					},
				},
			},
		},
	});
};

const renderPaymentChart = () => {
	const ctx = document.getElementById("paymentChartYearly") as HTMLCanvasElement;
	if (!ctx) return;

	if (paymentChartInstance) paymentChartInstance.destroy();

	const methodsMap: Record<string, string> = {
		cash: "Tunai",
		qris: "QRIS",
		transfer: "Transfer Bank",
	};

	const labels = paymentMethods.value.map((p) => methodsMap[p.payment_method] || p.payment_method);
	const values = paymentMethods.value.map((p) => p.total);

	paymentChartInstance = new Chart(ctx, {
		type: "doughnut",
		data: {
			labels,
			datasets: [
				{
					data: values,
					backgroundColor: ["#10b981", "#6366f1", "#db2777"],
					borderWidth: 4,
					borderColor: "#ffffff",
				},
			],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { position: "bottom" },
			},
			cutout: "65%",
		},
	});
};

const exportToExcel = () => {
	if (transactions.value.length === 0) {
		alert("Tidak ada data transaksi untuk diekspor.");
		return;
	}

	let csvContent =
		"No. Transaksi,Tipe,Kategori,Deskripsi,Metode Bayar,Jumlah (Rp),Tanggal,Kasir\r\n";

	transactions.value.forEach((t) => {
		const typeStr = t.type === "income" ? "Pemasukan" : "Pengeluaran";
		const payStr = t.payment_method ? t.payment_method.toUpperCase() : "TUNAI";
		const dateStr = new Date(t.transaction_date).toLocaleString("id-ID");
		const userStr = t.user?.name || "—";
		const descStr = t.description ? t.description.replace(/,/g, " ") : "";

		csvContent += `"${t.transaction_number}","${typeStr}","${t.category}","${descStr}","${payStr}",${t.amount},"${dateStr}","${userStr}"\r\n`;
	});

	const blob = new Blob([new Uint8Array([0xef, 0xbb, 0xbf]), csvContent], {
		type: "text/csv;charset=utf-8;",
	});
	const url = URL.createObjectURL(blob);
	const link = document.createElement("a");
	link.setAttribute("href", url);
	link.setAttribute("download", `Rekap_Tahunan_${selectedYear.value}.csv`);
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
};

const triggerPrint = () => {
	window.print();
};

watch(selectedYear, () => {
	updateDateRange();
	fetchReportData();
});

watch(chartType, () => {
	renderTrendChart();
});

watch(financialView, (newVal) => {
	filterType.value = newVal === "all" ? "" : newVal;
	fetchTransactions(1);
});

onMounted(() => {
	fetchReportData();
});
</script>

<template>
	<!-- Filters -->
	<div class="flex flex-col gap-4 print:hidden">
		<div class="flex flex-wrap items-center gap-3">
			<span class="text-xs font-black text-slate-400 uppercase tracking-wider min-w-[120px]">Pilih Tahun:</span>
			<select v-model="selectedYear" class="h-10 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 text-xs font-black text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-500">
				<option v-for="yr in [currentYear, currentYear-1, currentYear-2, currentYear-3]" :key="yr" :value="yr">Tahun {{ yr }}</option>
			</select>

			<div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl shrink-0 ml-4">
				<button type="button" @click="financialView = 'all'" :class="financialView === 'all' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-black transition-all">Gabungan</button>
				<button type="button" @click="financialView = 'income'" :class="financialView === 'income' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-black transition-all">Pemasukan</button>
				<button type="button" @click="financialView = 'expense'" :class="financialView === 'expense' ? 'bg-white dark:bg-slate-900 shadow-sm text-brand-600' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-black transition-all">Pengeluaran</button>
			</div>
		</div>

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

	<!-- Print Header -->
	<div class="hidden print:flex flex-col items-center justify-center text-center pb-6 border-b border-slate-300 w-full mb-6">
		<h1 class="text-2xl font-black tracking-tight">REKAP TAHUNAN RESTORAN</h1>
		<p class="text-xs font-bold uppercase tracking-wider text-slate-500 mt-1">Tahun: {{ selectedYear }}</p>
	</div>

	<!-- KPI Metric Cards -->
	<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 print:hidden">
		<!-- Pendapatan -->
		<div class="relative overflow-hidden bg-gradient-to-br from-brand-50 to-pink-100/50 dark:from-brand-950/20 dark:to-slate-900 border border-brand-100 dark:border-brand-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
			<div class="space-y-2 relative z-10">
				<span class="text-[10px] font-black uppercase text-brand-600 dark:text-brand-400 tracking-widest block font-mono">Pendapatan Tahunan</span>
				<h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.total_revenue) }}</h3>
				<div class="flex items-center gap-1 text-[10px] font-black text-emerald-600">
					<ArrowUpRight class="h-3.5 w-3.5" />
					<span>Kotor Tahunan</span>
				</div>
			</div>
			<div class="p-3.5 bg-brand-500 rounded-2xl text-white shadow-md">
				<DollarSign class="h-6 w-6" />
			</div>
		</div>

		<!-- Pengeluaran -->
		<div class="relative overflow-hidden bg-gradient-to-br from-rose-50 to-red-100/50 dark:from-rose-950/15 dark:to-slate-900 border border-rose-100 dark:border-rose-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
			<div class="space-y-2 relative z-10">
				<span class="text-[10px] font-black uppercase text-rose-600 dark:text-rose-400 tracking-widest block font-mono">Pengeluaran Tahunan</span>
				<h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.total_expense) }}</h3>
				<div class="flex items-center gap-1 text-[10px] font-black text-rose-500">
					<ArrowDownRight class="h-3.5 w-3.5" />
					<span>Operasional / HPP</span>
				</div>
			</div>
			<div class="p-3.5 bg-rose-500 rounded-2xl text-white shadow-md">
				<TrendingUp class="h-6 w-6" />
			</div>
		</div>

		<!-- Laba Bersih -->
		<div class="relative overflow-hidden bg-gradient-to-br from-emerald-50 to-teal-100/50 dark:from-emerald-950/15 dark:to-slate-900 border border-emerald-100 dark:border-emerald-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
			<div class="space-y-2 relative z-10">
				<span class="text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400 tracking-widest block font-mono">Laba Bersih Tahunan</span>
				<h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.net_profit) }}</h3>
				<div class="flex items-center gap-1 text-[10px] font-black text-emerald-600">
					<ArrowUpRight class="h-3.5 w-3.5" />
					<span>Bersih Tahunan</span>
				</div>
			</div>
			<div class="p-3.5 bg-emerald-500 rounded-2xl text-white shadow-md">
				<ShoppingBag class="h-6 w-6" />
			</div>
		</div>

		<!-- Rata-rata Nilai Transaksi (AOV) -->
		<div class="relative overflow-hidden bg-gradient-to-br from-indigo-50 to-blue-100/50 dark:from-indigo-950/15 dark:to-slate-900 border border-indigo-100 dark:border-indigo-950/50 p-6 rounded-3xl shadow-sm flex items-center justify-between">
			<div class="space-y-2 relative z-10">
				<span class="text-[10px] font-black uppercase text-indigo-600 dark:text-indigo-400 tracking-widest block font-mono">AOV Tahunan</span>
				<h3 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatIDR(kpi.avg_order_value) }}</h3>
				<div class="flex items-center gap-1.5 text-[10px] font-black text-slate-400">
					<Receipt class="h-3.5 w-3.5 text-indigo-500" />
					<span>{{ kpi.total_transactions }} Transaksi</span>
				</div>
			</div>
			<div class="p-3.5 bg-indigo-500 rounded-2xl text-white shadow-md">
				<Receipt class="h-6 w-6" />
			</div>
		</div>
	</div>

	<!-- Graphs -->
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:hidden">
		<!-- Tren Pendapatan Bulanan -->
		<div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-3xl shadow-sm flex flex-col min-h-[360px] relative">
			<div v-if="loadingDashboard" class="absolute inset-0 bg-white/60 dark:bg-slate-950/60 z-10 flex items-center justify-center rounded-3xl"><Loader2 class="animate-spin h-8 w-8 text-brand-600" /></div>
			<div class="flex justify-between items-center mb-6">
				<div class="flex items-center gap-4">
					<h3 class="font-black text-slate-800 dark:text-slate-100 text-sm uppercase tracking-wider flex items-center gap-2"><TrendingUp class="h-4 w-4 text-brand-600" /> Tren Bulanan (Tahun {{ selectedYear }})</h3>
					<div class="flex items-center bg-slate-100 dark:bg-slate-950 p-1 rounded-xl">
						<button @click="chartType = 'bar'" class="text-[10px] font-black uppercase px-3 py-1 rounded-lg transition-all cursor-pointer" :class="chartType === 'bar' ? 'bg-white dark:bg-slate-900 text-brand-600 shadow-sm' : 'text-slate-400'">Batang</button>
						<button @click="chartType = 'line'" class="text-[10px] font-black uppercase px-3 py-1 rounded-lg transition-all cursor-pointer" :class="chartType === 'line' ? 'bg-white dark:bg-slate-900 text-brand-600 shadow-sm' : 'text-slate-400'">Garis</button>
					</div>
				</div>
			</div>
			<div class="flex-1 w-full relative">
				<canvas id="trendChartYearly"></canvas>
			</div>
		</div>

		<!-- Sebaran Pembayaran -->
		<div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-6 rounded-3xl shadow-sm flex flex-col min-h-[360px] relative">
			<div v-if="loadingDashboard" class="absolute inset-0 bg-white/60 dark:bg-slate-950/60 z-10 flex items-center justify-center rounded-3xl"><Loader2 class="animate-spin h-8 w-8 text-brand-600" /></div>
			<div class="flex justify-between items-center mb-6">
				<h3 class="font-black text-slate-800 dark:text-slate-100 text-sm uppercase tracking-wider flex items-center gap-2"><BarChart3 class="h-4 w-4 text-indigo-500" /> Sebaran Pembayaran</h3>
			</div>
			<div class="flex-1 w-full relative min-h-[220px]">
				<canvas id="paymentChartYearly"></canvas>
			</div>
		</div>
	</div>

	<!-- Detailed Ledger -->
	<div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm flex flex-col overflow-hidden print:border-none print:shadow-none">
		<div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 print:hidden">
			<h3 class="font-black text-slate-800 dark:text-slate-100 text-sm uppercase tracking-wider flex items-center gap-2"><Receipt class="h-4 w-4 text-slate-500" /> Buku Kas / Ledger Transaksi</h3>

			<div class="flex flex-wrap items-center gap-3 w-full md:w-auto relative">
				<div class="relative w-full md:w-56">
					<Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
					<Input v-model="search" @input="fetchTransactions(1)" placeholder="Cari No. Transaksi..." class="h-9 pl-9 rounded-xl border-slate-200 text-xs font-bold w-full" />
				</div>

				<select v-model="filterType" @change="fetchTransactions(1)" class="h-9 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 text-xs font-black text-slate-500 focus:outline-none">
					<option value="">Semua Tipe</option>
					<option value="income">Pemasukan (Masuk)</option>
					<option value="expense">Pengeluaran (Keluar)</option>
				</select>

				<select v-model="filterPayment" @change="fetchTransactions(1)" class="h-9 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 text-xs font-black text-slate-500 focus:outline-none">
					<option value="">Semua Metode</option>
					<option value="cash">Tunai (Cash)</option>
					<option value="qris">QRIS</option>
					<option value="transfer">Bank Transfer</option>
				</select>

				<div class="relative">
					<Button @click="showColumnDropdown = !showColumnDropdown" variant="outline" class="h-9 px-3 rounded-xl border-slate-200 text-xs font-black gap-1.5 cursor-pointer">
						<span>Kolom</span>
					</Button>
					<div v-if="showColumnDropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-xl p-3.5 z-20 flex flex-col gap-2 animate-in fade-in slide-in-from-top-2 duration-150">
						<Label class="text-[9px] font-black uppercase text-slate-400 tracking-wider mb-1 block">Tampilkan Kolom</Label>
						<label v-for="(val, col) in visibleColumns" :key="col" class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">
							<input type="checkbox" v-model="visibleColumns[col]" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-3.5 w-3.5" />
							<span class="capitalize">{{ col.replace('_', ' ') }}</span>
						</label>
					</div>
				</div>
			</div>
		</div>

		<div class="flex-1 overflow-x-auto w-full custom-scrollbar">
			<table class="w-full text-left border-collapse">
				<thead>
					<tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/20">
						<th v-if="visibleColumns.transaction_number" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">No. Transaksi</th>
						<th v-if="visibleColumns.type" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Tipe</th>
						<th v-if="visibleColumns.category" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Kategori</th>
						<th v-if="visibleColumns.description" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Keterangan</th>
						<th v-if="visibleColumns.payment_method" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest text-center">Metode</th>
						<th v-if="visibleColumns.amount" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest text-right">Jumlah</th>
						<th v-if="visibleColumns.transaction_date" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest">Tanggal</th>
						<th v-if="visibleColumns.user" class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest print:hidden">Kasir</th>
						<th class="px-6 py-4 font-black text-xs text-slate-400 uppercase tracking-widest text-center print:hidden w-20">Aksi</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-slate-100 dark:divide-slate-800 relative">
					<tr v-if="loadingTransactions" class="h-28"><td colspan="9" class="text-center"><Loader2 class="animate-spin h-6 w-6 text-brand-600 inline-block mr-2" /><span class="text-xs font-black text-slate-400 uppercase">Memuat data kas...</span></td></tr>
					<tr v-else-if="transactions.length === 0" class="h-28"><td colspan="9" class="text-center text-xs font-black text-slate-400 uppercase">Tidak ada transaksi tercatat</td></tr>
					<tr v-for="t in transactions" :key="t.id" v-else class="hover:bg-slate-50/50 dark:hover:bg-slate-950/20 transition-all font-bold text-xs text-slate-600 dark:text-slate-300">
						<td v-if="visibleColumns.transaction_number" class="px-6 py-4 font-black text-slate-800 dark:text-slate-100 select-all font-mono">{{ t.transaction_number }}</td>
						<td v-if="visibleColumns.type" class="px-6 py-4">
							<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider" :class="t.type === 'income' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20' : 'bg-rose-50 text-rose-600 dark:bg-rose-950/20'">
								<span class="w-1.5 h-1.5 rounded-full" :class="t.type === 'income' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
								{{ t.type === 'income' ? 'Masuk' : 'Keluar' }}
							</span>
						</td>
						<td v-if="visibleColumns.category" class="px-6 py-4 capitalize font-semibold">{{ categoryLabels[t.category] || t.category.replace('_', ' ') }}</td>
						<td v-if="visibleColumns.description" class="px-6 py-4 max-w-xs truncate font-medium text-slate-500 dark:text-slate-400" :title="t.description">{{ t.description || '—' }}</td>
						<td v-if="visibleColumns.payment_method" class="px-6 py-4 text-center">
							<span class="px-2 py-0.5 rounded-md border text-[9px] font-black uppercase" :class="t.payment_method === 'qris' ? 'border-indigo-200 bg-indigo-50 text-indigo-600 dark:border-indigo-950/50 dark:bg-indigo-950/30' : (t.payment_method === 'transfer' ? 'border-brand-200 bg-brand-50 text-brand-600 dark:border-brand-950/50 dark:bg-brand-950/30' : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900')">
								{{ paymentMethodLabels[t.payment_method] || t.payment_method || 'TUNAI' }}
							</span>
						</td>
						<td v-if="visibleColumns.amount" class="px-6 py-4 text-right font-black text-sm" :class="t.type === 'income' ? 'text-slate-800 dark:text-slate-100' : 'text-rose-500'">
							{{ t.type === 'income' ? '+' : '-' }}{{ formatIDR(t.amount) }}
						</td>
						<td v-if="visibleColumns.transaction_date" class="px-6 py-4 font-semibold text-slate-500 select-all">{{ new Date(t.transaction_date).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) }}</td>
						<td v-if="visibleColumns.user" class="px-6 py-4 font-medium text-slate-400 print:hidden">{{ t.user?.name || '—' }}</td>
						<td class="px-6 py-4 text-center print:hidden">
							<Button 
								size="icon-sm" 
								variant="ghost" 
								class="text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:text-blue-300 dark:hover:bg-blue-950/50"
								@click="openDetail(t)" 
								title="Cek Detail"
							>
								<Eye class="h-4 w-4" />
							</Button>
						</td>
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
				<Button variant="outline" size="icon" class="h-8 w-8 rounded-lg cursor-pointer" :disabled="pagination.current_page === 1 || loadingTransactions" @click="changePage(1)"><ChevronLeft class="h-4 w-4" /></Button>
				<Button variant="outline" size="icon" class="h-8 w-8 rounded-lg cursor-pointer" :disabled="pagination.current_page === 1 || loadingTransactions" @click="changePage(pagination.current_page - 1)"><ChevronLeft class="h-4 w-4" /></Button>
				<span class="text-xs font-black text-slate-500 px-3 select-none">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
				<Button variant="outline" size="icon" class="h-8 w-8 rounded-lg cursor-pointer" :disabled="pagination.current_page === pagination.last_page || loadingTransactions" @click="changePage(pagination.current_page + 1)"><ChevronRight class="h-4 w-4" /></Button>
				<Button variant="outline" size="icon" class="h-8 w-8 rounded-lg cursor-pointer" :disabled="pagination.current_page === pagination.last_page || loadingTransactions" @click="changePage(pagination.last_page)"><ChevronRight class="h-4 w-4" /></Button>
			</div>
		</div>
	</div>

	<!-- Detail Dialog Modal -->
	<Dialog v-model:open="showDetailDialog">
		<DialogContent class="sm:max-w-lg text-slate-900 dark:text-slate-100">
			<DialogHeader>
				<DialogTitle class="text-xl font-bold flex items-center gap-2">
					<Eye class="h-5 w-5 text-blue-600 dark:text-blue-400" />
					Detail Transaksi
				</DialogTitle>
			</DialogHeader>

			<div v-if="selectedTransaction" class="mt-4 space-y-4 text-sm">
				<div class="grid grid-cols-2 gap-4 border-b border-border pb-4">
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">No. Transaksi</span>
						<span class="font-mono text-base font-semibold text-foreground">{{ selectedTransaction.transaction_number }}</span>
					</div>
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Tanggal Transaksi</span>
						<span class="font-semibold text-foreground">
							{{ selectedTransaction.transaction_date ? new Date(selectedTransaction.transaction_date).toLocaleDateString("id-ID", { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '—' }}
						</span>
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Kategori</span>
						<span class="inline-flex items-center rounded-md bg-secondary px-2.5 py-1 text-xs font-semibold text-secondary-foreground mt-0.5 capitalize">
							{{ categoryLabels[selectedTransaction.category] || selectedTransaction.category.replace('_', ' ') }}
						</span>
					</div>
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Nominal</span>
						<span class="text-lg font-bold" :class="selectedTransaction.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
							{{ selectedTransaction.type === 'income' ? '+' : '-' }}Rp {{ Math.round(selectedTransaction.amount).toLocaleString("id-ID") }}
						</span>
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Metode Pembayaran</span>
						<span class="font-medium text-foreground uppercase">
							{{ paymentMethodLabels[selectedTransaction.payment_method] || selectedTransaction.payment_method || 'TUNAI' }}
						</span>
					</div>
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Tipe Transaksi</span>
						<span 
							class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold mt-0.5"
							:class="selectedTransaction.type === 'income' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400'"
						>
							{{ selectedTransaction.type === "income" ? "Pemasukan (Masuk)" : "Pengeluaran (Keluar)" }}
						</span>
					</div>
				</div>

				<div class="border-t border-border pt-4">
					<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Keterangan / Catatan</span>
					<p class="text-foreground mt-1 bg-muted/30 p-2.5 rounded-md border border-border/50 min-h-[60px] whitespace-pre-line italic">
						{{ selectedTransaction.description || 'Tidak ada keterangan.' }}
					</p>
				</div>

				<div class="grid grid-cols-2 gap-4 border-t border-border pt-4">
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Kasir / Input Oleh</span>
						<span class="font-medium text-foreground">
							{{ selectedTransaction.user?.name || '—' }}
						</span>
					</div>
					<div>
						<span class="text-muted-foreground block text-xs font-medium uppercase tracking-wider">Waktu Input</span>
						<span class="font-medium text-foreground">
							{{ new Date(selectedTransaction.created_at).toLocaleString("id-ID", { dateStyle: 'short', timeStyle: 'short' }) }}
						</span>
					</div>
				</div>
			</div>

			<DialogFooter class="mt-6">
				<Button variant="outline" @click="showDetailDialog = false">
					Tutup
				</Button>
			</DialogFooter>
		</DialogContent>
	</Dialog>
</template>
