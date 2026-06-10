<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import { dashboard } from "@/routes";
import DailyReport from "./components/DailyReport.vue";
import MonthlyReport from "./components/MonthlyReport.vue";
import YearlyReport from "./components/YearlyReport.vue";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: dashboard().url },
			{ title: "Laporan Keuangan", href: "#" },
		],
	},
});

// Tab Switcher
const activeTab = ref<"daily" | "monthly" | "yearly">("daily");
</script>

<template>
	<Head title="Laporan Keuangan" />

	<div class="p-6 flex flex-col gap-6 overflow-y-auto w-full custom-scrollbar print:p-0 print:bg-white print:text-black">
		<!-- HEADER & TITLE (Plain layout, no container background) -->
		<div class="flex flex-col gap-1 pb-4 print:hidden">
			<h1 class="text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight">Laporan Keuangan</h1>
			<p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Pantau pendapatan, pengeluaran, dan rekap keuangan restoran</p>
		</div>

		<!-- TAB SELECTOR -->
		<div class="flex items-center border-b border-slate-100 dark:border-slate-800 pb-2 gap-4 print:hidden">
			<button
				type="button"
				@click="activeTab = 'daily'"
				:class="activeTab === 'daily' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
				class="border-b-2 px-4 py-2 text-sm font-black transition-all cursor-pointer"
			>
				Laporan Harian
			</button>
			<button
				type="button"
				@click="activeTab = 'monthly'"
				:class="activeTab === 'monthly' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
				class="border-b-2 px-4 py-2 text-sm font-black transition-all cursor-pointer"
			>
				Laporan Bulanan
			</button>
			<button
				type="button"
				@click="activeTab = 'yearly'"
				:class="activeTab === 'yearly' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700'"
				class="border-b-2 px-4 py-2 text-sm font-black transition-all cursor-pointer"
			>
				Rekap 1 Tahun
			</button>
		</div>

		<!-- Tab Components -->
		<div class="flex flex-col gap-6">
			<DailyReport v-if="activeTab === 'daily'" />
			<MonthlyReport v-else-if="activeTab === 'monthly'" />
			<YearlyReport v-else-if="activeTab === 'yearly'" />
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
		font-size: 10px !important;
	}

	th, td {
		padding: 8px 12px !important;
		border-bottom: 1px solid #cbd5e1 !important;
	}
}
</style>
