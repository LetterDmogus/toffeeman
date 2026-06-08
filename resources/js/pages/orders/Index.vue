<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import {
	Calendar,
	Check,
	CheckCircle2,
	ChevronLeft,
	ChevronRight,
	ChevronsLeft,
	ChevronsRight,
	Clock,
	CreditCard,
	Eye,
	Flame,
	Loader2,
	Printer,
	Receipt,
	RefreshCw,
	Search,
	SlidersHorizontal,
	Utensils,
	XCircle,
} from "lucide-vue-next";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog";
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuLabel,
	DropdownMenuSeparator,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Operasional", href: "#" },
			{ title: "Daftar Pesanan", href: "/orders" },
		],
	},
});

// State
const orders = ref<any[]>([]);
const loading = ref(false);
const search = ref("");
const statusFilter = ref("");
const paymentFilter = ref("");
const selectedOrder = ref<any | null>(null);
const detailOpen = ref(false);
const paymentOpen = ref(false);
const paying = ref(false);
const cancelLoading = ref(false);
const selectedPaymentMethod = ref<"cash" | "qris" | "transfer">("cash");
const selectedBank = ref<string>("bca");
const banksList = [
	{ id: "bca", name: "BCA VA" },
	{ id: "bri", name: "BRI VA" },
	{ id: "bni", name: "BNI VA" },
	{ id: "permata", name: "Permata VA" },
	{ id: "cimb", name: "CIMB VA" },
	{ id: "mandiri", name: "Mandiri Bill" },
	{ id: "danamon", name: "Danamon VA" },
	{ id: "bsi", name: "BSI VA" },
	{ id: "seabank", name: "SeaBank VA" },
	{ id: "saqu", name: "Saqu VA" },
];
const discountAmount = ref(0);

// Midtrans QRIS State
const qrModalOpen = ref(false);
const qrUrl = ref("");
const vaNumber = ref("");
const vaBank = ref("");
const requestedBank = ref("");
const activeOrderNumber = ref("");
const activeOrderFinalAmount = ref(0);

// Polling State for QRIS Payment status
const pollingOrderId = ref<number | null>(null);
const paymentSuccess = ref(false);
let pollingInterval: any = null;

const startPolling = (orderId: number) => {
	stopPolling();
	paymentSuccess.value = false;
	pollingOrderId.value = orderId;
	pollingInterval = setInterval(async () => {
		if (!qrModalOpen.value) {
			stopPolling();

			return;
		}

		try {
			const res = await fetch(`/api/orders/${orderId}`, {
				headers: { Accept: "application/json" },
			});

			if (res.ok) {
				const data = await res.json();

				if (data.payment_status === "paid") {
					stopPolling();
					paymentSuccess.value = true;
					setTimeout(() => {
						qrModalOpen.value = false;
						paymentSuccess.value = false;

						// Update current selected order and refresh orders
						if (selectedOrder.value && selectedOrder.value.id === data.id) {
							selectedOrder.value = data;
						}

						fetchOrders(pagination.value.current_page);
					}, 3000);
				}
			}
		} catch (err) {
			console.error("Error polling order status:", err);
		}
	}, 2000);
};

const stopPolling = () => {
	if (pollingInterval) {
		clearInterval(pollingInterval);
		pollingInterval = null;
	}

	pollingOrderId.value = null;
};

onUnmounted(() => {
	stopPolling();
});

// Pagination State
const pagination = ref({
	current_page: 1,
	last_page: 1,
	total: 0,
	from: null as number | null,
	to: null as number | null,
	per_page: 15,
});

// Column Visibility State
const availableColumns = [
	{ key: "order_number", label: "No. Pesanan" },
	{ key: "created_at", label: "Waktu Buat" },
	{ key: "order_type", label: "Tipe / Lokasi" },
	{ key: "items", label: "Item Hidangan" },
	{ key: "total_amount", label: "Subtotal (Gross)" },
	{ key: "discount_amount", label: "Diskon" },
	{ key: "tax_amount", label: "Pajak" },
	{ key: "final_amount", label: "Total Net" },
	{ key: "payment_method", label: "Metode Bayar" },
	{ key: "status", label: "Status Hidangan" },
	{ key: "payment_status", label: "Status Bayar" },
];

const visibleColumns = ref<Record<string, boolean>>({
	order_number: true,
	created_at: true,
	order_type: false,
	items: true,
	total_amount: false,
	discount_amount: false,
	tax_amount: false,
	final_amount: false,
	payment_method: false,
	status: true,
	payment_status: false,
});

const activeColumnsCount = computed(() => {
	return Object.values(visibleColumns.value).filter(Boolean).length + 1; // plus 1 for Aksi column
});

// Status Badge Maps
const statusBadgeMap = {
	pending: {
		label: "Menunggu",
		variant: "outline",
		icon: Clock,
		color:
			"text-amber-600 bg-amber-50 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30",
	},
	processing: {
		label: "Dimasak",
		variant: "secondary",
		icon: Flame,
		color:
			"text-blue-600 bg-blue-50 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900/30",
	},
	ready: {
		label: "Siap Saji",
		variant: "default",
		icon: Utensils,
		color:
			"text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30",
	},
	served: {
		label: "Selesai",
		variant: "default",
		icon: CheckCircle2,
		color:
			"text-brand-600 bg-brand-50 border-brand-200 dark:bg-brand-950/20 dark:text-brand-400 dark:border-brand-900/30",
	},
	cancelled: {
		label: "Batal",
		variant: "destructive",
		icon: XCircle,
		color:
			"text-rose-600 bg-rose-50 border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/30",
	},
};

const paymentBadgeMap = {
	paid: {
		label: "Lunas",
		color:
			"bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400",
	},
	unpaid: {
		label: "Belum Bayar",
		color:
			"bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400",
	},
};

// Formatter for variants array of objects
const formatVariants = (variants: any[]) => {
	if (!variants || !Array.isArray(variants) || variants.length === 0) {
		return "";
	}

	return variants
		.map((v) => {
			if (typeof v === "string") {
				return v;
			}

			const optionName = v.option_name || "";
			const valueName = v.name || v.value_name || v.value || "";

			return optionName ? `${optionName}: ${valueName}` : valueName;
		})
		.join(", ");
};

// Fetch orders
const fetchOrders = async (page = 1) => {
	loading.value = true;

	try {
		let url = `/api/orders?page=${page}&per_page=15`;

		if (statusFilter.value) {
			url += `&status=${statusFilter.value}`;
		}

		if (paymentFilter.value) {
			url += `&payment_status=${paymentFilter.value}`;
		}

		if (search.value) {
			url += `&search=${search.value}`;
		}

		const res = await fetch(url, { headers: { Accept: "application/json" } });

		if (res.ok) {
			const data = await res.json();
			orders.value = data.data || [];
			pagination.value = {
				current_page: data.current_page || 1,
				last_page: data.last_page || 1,
				total: data.total || 0,
				from: data.from || null,
				to: data.to || null,
				per_page: data.per_page || 15,
			};
		}
	} catch (err) {
		console.error("Gagal mengambil data pesanan:", err);
	} finally {
		loading.value = false;
	}
};

// Change page
const changePage = (page: number) => {
	if (page < 1 || page > pagination.value.last_page) {
		return;
	}

	fetchOrders(page);
};

// Filter handlers
const handleFilter = () => {
	fetchOrders(1);
};

const resetFilters = () => {
	search.value = "";
	statusFilter.value = "";
	paymentFilter.value = "";
	fetchOrders(1);
};

// Open order details
const viewOrder = (order: any) => {
	selectedOrder.value = order;
	detailOpen.value = true;
};

// Open payment modal
const openPaymentModal = (order: any) => {
	selectedOrder.value = order;
	selectedPaymentMethod.value = "cash";
	discountAmount.value = order.discount_amount || 0;
	paymentOpen.value = true;
};

// Format Rupiah
const formatRupiah = (value: number) => {
	return new Intl.NumberFormat("id-ID", {
		style: "currency",
		currency: "IDR",
		minimumFractionDigits: 0,
		maximumFractionDigits: 0,
	}).format(value);
};

// Format date
const formatDate = (dateString: string) => {
	if (!dateString) {
		return "—";
	}

	const date = new Date(dateString);

	return new Intl.DateTimeFormat("id-ID", {
		dateStyle: "medium",
		timeStyle: "short",
	}).format(date);
};

// Process order payment
const submitPayment = async () => {
	if (!selectedOrder.value) {
		return;
	}

	paying.value = true;

	try {
		const res = await fetch(`/api/orders/${selectedOrder.value.id}/pay`, {
			method: "PATCH",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
						?.content || "",
			},
			body: JSON.stringify({
				payment_method: selectedPaymentMethod.value,
				discount: discountAmount.value,
				bank:
					selectedPaymentMethod.value === "transfer"
						? selectedBank.value
						: null,
			}),
		});

		if (res.ok) {
			const data = await res.json();

			if (data.qr_url || data.va_number) {
				qrUrl.value = data.qr_url || "";
				vaNumber.value = data.va_number || "";
				vaBank.value = data.va_bank || "";
				requestedBank.value =
					data.requested_bank ||
					data.order?.payment_metadata?.requested_bank ||
					"";
				activeOrderNumber.value =
					data.order?.order_number ||
					data.order_number ||
					selectedOrder.value?.order_number ||
					"";
				activeOrderFinalAmount.value =
					data.order?.final_amount ||
					data.final_amount ||
					selectedOrder.value?.final_amount ||
					0;
				paymentOpen.value = false;
				qrModalOpen.value = true;
				startPolling(selectedOrder.value?.id || data.order?.id || data.id);
			} else {
				// Update in local list
				const index = orders.value.findIndex((o) => o.id === data.id);

				if (index !== -1) {
					orders.value[index] = data;
				}

				selectedOrder.value = data;
				paymentOpen.value = false;
				fetchOrders(pagination.value.current_page);
			}
		} else {
			const errData = await res.json();
			alert(errData.message || "Gagal memproses pembayaran.");
		}
	} catch (err) {
		console.error("Error memproses pembayaran:", err);
	} finally {
		paying.value = false;
	}
};

// Cancel whole order
const cancelOrder = async () => {
	if (
		!selectedOrder.value ||
		!confirm("Apakah Anda yakin ingin membatalkan pesanan ini?")
	) {
		return;
	}

	cancelLoading.value = true;

	try {
		const res = await fetch(`/api/orders/${selectedOrder.value.id}`, {
			method: "DELETE",
			headers: {
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
						?.content || "",
			},
		});

		if (res.ok) {
			fetchOrders(pagination.value.current_page);
			detailOpen.value = false;
		}
	} catch (err) {
		console.error("Error membatalkan pesanan:", err);
	} finally {
		cancelLoading.value = false;
	}
};

// Print receipt utility
const printReceipt = (order: any) => {
	const printWindow = window.open("", "_blank");

	if (!printWindow) {
		return;
	}

	const itemsHtml = order.items
		.map(
			(item: any) => `
        <tr style="border-bottom: 1px dashed #eee;">
            <td style="padding: 6px 0; font-size: 13px;">
                <strong>${item.name}</strong>
                ${item.variants?.length ? `<div style="font-size: 11px; color: #666;">Varian: ${formatVariants(item.variants)}</div>` : ""}
                ${item.add_ons?.length ? `<div style="font-size: 11px; color: #666;">Topping: ${item.add_ons.map((a: any) => a.name).join(", ")}</div>` : ""}
                ${item.notes ? `<div style="font-size: 11px; color: #f59e0b;">Catatan: ${item.notes}</div>` : ""}
            </td>
            <td style="padding: 6px 0; text-align: center; font-size: 13px;">x${item.qty}</td>
            <td style="padding: 6px 0; text-align: right; font-size: 13px;">${formatRupiah(item.price * item.qty)}</td>
        </tr>
    `,
		)
		.join("");

	printWindow.document.write(`
        <html>
        <head>
            <title>Struk Belanja - ${order.order_number}</title>
            <style>
                body { font-family: 'Courier New', Courier, monospace; width: 300px; margin: 0 auto; padding: 20px; color: #333; }
                .center { text-align: center; }
                .bold { font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .hr { border-top: 1px dashed #333; margin: 10px 0; }
                .flex-between { display: flex; justify-content: space-between; margin: 4px 0; }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <div class="center bold" style="font-size: 18px; margin-bottom: 2px;">Toffeeman Bakery</div>
            <div class="center" style="font-size: 12px; color: #555;">Ruko Golden Boulevard Blk. A No. 12</div>
            <div class="center" style="font-size: 12px; color: #555;">Telp: 0812-3456-7890</div>
            <div class="hr"></div>
            <div class="flex-between"><span>No:</span> <span>${order.order_number}</span></div>
            <div class="flex-between"><span>Tanggal:</span> <span>${formatDate(order.created_at)}</span></div>
            <div class="flex-between"><span>Meja:</span> <span>${order.table?.name || "Take Away"}</span></div>
            <div class="flex-between"><span>Tipe:</span> <span>${order.order_type === "dine_in" ? "Dine In" : "Take Away"}</span></div>
            <div class="hr"></div>
            <table>
                <thead>
                    <tr style="border-bottom: 1px dashed #333;">
                        <th style="text-align: left; padding-bottom: 4px; font-size: 12px;">Menu</th>
                        <th style="text-align: center; padding-bottom: 4px; font-size: 12px;">Qty</th>
                        <th style="text-align: right; padding-bottom: 4px; font-size: 12px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>
            <div class="hr"></div>
            <div class="flex-between"><span>Subtotal:</span> <span>${formatRupiah(order.total_amount)}</span></div>
            ${order.discount_amount ? `<div class="flex-between"><span>Diskon:</span> <span>-${formatRupiah(order.discount_amount)}</span></div>` : ""}
            <div class="flex-between"><span>Pajak (10%):</span> <span>+${formatRupiah(order.tax_amount)}</span></div>
            <div class="flex-between bold" style="font-size: 15px;"><span>TOTAL:</span> <span>${formatRupiah(order.final_amount)}</span></div>
            <div class="hr"></div>
            <div class="flex-between"><span>Metode:</span> <span>${order.payment_method?.toUpperCase() || "CASH"}</span></div>
            <div class="flex-between"><span>Status:</span> <span>${order.payment_status === "paid" ? "LUNAS" : "BELUM BAYAR"}</span></div>
            <div class="hr"></div>
            <div class="center bold" style="margin-top: 20px;">Terima Kasih Atas Kunjungan Anda</div>
            <div class="center" style="font-size: 10px; color: #777; margin-top: 4px;">Powered by Laravel POS</div>
        </body>
        </html>
    `);
	printWindow.document.close();
};

onMounted(() => {
	fetchOrders(1);
});
</script>

<template>
    <Head title="Daftar Pesanan" />

    <div class="flex flex-col gap-6 p-6">
        <!-- HEADER SECTION -->
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <Receipt class="h-8 w-8 text-brand-500" />
                    Daftar Pesanan
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Kelola dan pantau seluruh status pemesanan serta pembayaran secara real-time.
                </p>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- COLUMN VISIBILITY TOGGLE BUTTON -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" class="flex items-center gap-2">
                            <SlidersHorizontal class="h-4 w-4" />
                            Kolom Tampilan
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel>Tampilkan Kolom</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem 
                            v-for="col in availableColumns" 
                            :key="col.key"
                            @select.prevent="visibleColumns[col.key] = !visibleColumns[col.key]"
                            class="flex items-center gap-3 cursor-pointer py-2 px-3 focus:bg-slate-100 dark:focus:bg-slate-800 rounded-md"
                        >
                            <div :class="[
                                'h-4 w-4 rounded border flex items-center justify-center transition-all',
                                visibleColumns[col.key] 
                                    ? 'border-brand-600 bg-brand-600 text-white dark:border-brand-500 dark:bg-brand-500' 
                                    : 'border-slate-300 dark:border-slate-700 bg-transparent'
                            ]">
                                <Check v-if="visibleColumns[col.key]" class="h-3 w-3 stroke-[3]" />
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ col.label }}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button @click="fetchOrders(pagination.current_page)" variant="outline" class="flex items-center gap-2">
                    <RefreshCw :class="['h-4 w-4', loading ? 'animate-spin' : '']" />
                    Segarkan
                </Button>
                <Link href="/pos" class="inline-flex items-center justify-center rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium text-sm h-10 px-4 transition-colors">
                    Mulai Transaksi Baru
                </Link>
            </div>
        </div>

        <!-- FILTER BAR CARD -->
        <Card class="border-slate-100 shadow-sm dark:border-slate-800">
            <CardContent class="p-4 flex flex-col md:flex-row gap-3 items-end">
                <div class="flex-1 w-full">
                    <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-1.5">Cari Nomor Pesanan</label>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <Input 
                            v-model="search" 
                            type="text" 
                            placeholder="Cari ORD-xxxxxx..." 
                            class="pl-9 h-10 border-slate-200 dark:border-slate-800 focus-visible:ring-brand-500"
                            @keyup.enter="handleFilter" 
                        />
                    </div>
                </div>

                <div class="w-full md:w-48">
                    <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-1.5">Status Pesanan</label>
                    <select 
                        v-model="statusFilter" 
                        class="w-full h-10 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900"
                        @change="handleFilter"
                    >
                        <option value="">Semua Status</option>
                        <option value="pending">Menunggu Konfirmasi</option>
                        <option value="processing">Dimasak</option>
                        <option value="ready">Siap Saji</option>
                        <option value="served">Selesai / Disajikan</option>
                        <option value="cancelled">Batal</option>
                    </select>
                </div>

                <div class="w-full md:w-48">
                    <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-1.5">Pembayaran</label>
                    <select 
                        v-model="paymentFilter" 
                        class="w-full h-10 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-slate-800 dark:bg-slate-900"
                        @change="handleFilter"
                    >
                        <option value="">Semua Pembayaran</option>
                        <option value="paid">Lunas</option>
                        <option value="unpaid">Belum Bayar</option>
                    </select>
                </div>

                <div class="flex gap-2 w-full md:w-auto">
                    <Button @click="handleFilter" class="bg-brand-600 hover:bg-brand-700 text-white flex-1 md:flex-none">
                        Filter
                    </Button>
                    <Button @click="resetFilters" variant="outline" class="flex-1 md:flex-none">
                        Reset
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- ORDERS LIST TABLE -->
        <Card class="border-slate-100 shadow-sm dark:border-slate-800">
            <CardContent class="p-0">
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900/50">
                                <th v-if="visibleColumns.order_number" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300">No. Pesanan</th>
                                <th v-if="visibleColumns.created_at" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300">Waktu Buat</th>
                                <th v-if="visibleColumns.order_type" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300">Tipe / Lokasi</th>
                                <th v-if="visibleColumns.items" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300">Item Hidangan</th>
                                <th v-if="visibleColumns.total_amount" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-right">Subtotal (Gross)</th>
                                <th v-if="visibleColumns.discount_amount" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-right">Diskon</th>
                                <th v-if="visibleColumns.tax_amount" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-right">Pajak</th>
                                <th v-if="visibleColumns.final_amount" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-right">Total Net</th>
                                <th v-if="visibleColumns.payment_method" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-center">Metode</th>
                                <th v-if="visibleColumns.status" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-center">Status</th>
                                <th v-if="visibleColumns.payment_status" class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-center">Bayar</th>
                                <th class="px-6 py-4 font-semibold text-sm text-slate-600 dark:text-slate-300 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <!-- Premium Skeleton Loading State -->
                            <template v-if="loading">
                                <tr v-for="n in 5" :key="'skeleton-' + n" class="animate-pulse">
                                    <td v-if="visibleColumns.order_number" class="px-6 py-4">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-24"></div>
                                    </td>
                                    <td v-if="visibleColumns.created_at" class="px-6 py-4">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-32"></div>
                                    </td>
                                    <td v-if="visibleColumns.items" class="px-6 py-4">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-48"></div>
                                    </td>
                                    <td v-if="visibleColumns.order_type" class="px-6 py-4 text-center">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-16 mx-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.table_id" class="px-6 py-4 text-center">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-12 mx-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.customer_id" class="px-6 py-4">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-20"></div>
                                    </td>
                                    <td v-if="visibleColumns.waiter_id" class="px-6 py-4">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-20"></div>
                                    </td>
                                    <td v-if="visibleColumns.total_amount" class="px-6 py-4 text-right">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-20 ml-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.discount_amount" class="px-6 py-4 text-right">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-16 ml-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.tax_amount" class="px-6 py-4 text-right">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-16 ml-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.final_amount" class="px-6 py-4 text-right">
                                        <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-24 ml-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.payment_method" class="px-6 py-4 text-center">
                                        <div class="h-5 bg-slate-200 dark:bg-slate-800 rounded-full w-14 mx-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.status" class="px-6 py-4 text-center">
                                        <div class="h-5 bg-slate-200 dark:bg-slate-800 rounded-full w-16 mx-auto"></div>
                                    </td>
                                    <td v-if="visibleColumns.payment_status" class="px-6 py-4 text-center">
                                        <div class="h-5 bg-slate-200 dark:bg-slate-800 rounded-full w-16 mx-auto"></div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="h-8 bg-slate-200 dark:bg-slate-800 rounded-lg w-20 ml-auto"></div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Empty State -->
                            <tr v-else-if="orders.length === 0">
                                <td :colspan="activeColumnsCount" class="text-center py-12 text-slate-400">
                                    Belum ada pesanan yang sesuai dengan filter atau kata kunci pencarian.
                                </td>
                            </tr>

                            <!-- Rows -->
                            <tr 
                                v-else 
                                v-for="order in orders" 
                                :key="order.id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                            >
                                <td v-if="visibleColumns.order_number" class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">
                                    {{ order.order_number }}
                                </td>
                                <td v-if="visibleColumns.created_at" class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ formatDate(order.created_at) }}
                                </td>
                                <td v-if="visibleColumns.order_type" class="px-6 py-4 text-sm">
                                    <span class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-medium">
                                        <span v-if="order.order_type === 'dine_in'" class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950/30 dark:text-blue-400">
                                            Dine In : {{ order.table?.name || 'Meja ' + order.table_id }}
                                        </span>
                                        <span v-else class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                            Take Away
                                        </span>
                                    </span>
                                </td>
                                <td v-if="visibleColumns.items" class="px-6 py-4 text-sm max-w-[250px] truncate text-slate-600 dark:text-slate-400">
                                    <span v-for="(item, idx) in order.items" :key="item.id">
                                        {{ item.qty }}x {{ item.name }}{{ idx !== order.items.length - 1 ? ', ' : '' }}
                                    </span>
                                </td>
                                <td v-if="visibleColumns.total_amount" class="px-6 py-4 text-right font-medium text-slate-600 dark:text-slate-400">
                                    {{ formatRupiah(order.total_amount) }}
                                </td>
                                <td v-if="visibleColumns.discount_amount" class="px-6 py-4 text-right font-medium text-rose-600">
                                    {{ order.discount_amount ? `-${formatRupiah(order.discount_amount)}` : '—' }}
                                </td>
                                <td v-if="visibleColumns.tax_amount" class="px-6 py-4 text-right font-medium text-slate-600 dark:text-slate-400">
                                    {{ formatRupiah(order.tax_amount) }}
                                </td>
                                <td v-if="visibleColumns.final_amount" class="px-6 py-4 text-right font-semibold text-slate-900 dark:text-slate-100">
                                    {{ formatRupiah(order.final_amount) }}
                                </td>
                                <td v-if="visibleColumns.payment_method" class="px-6 py-4 text-center font-medium text-sm text-slate-700 dark:text-slate-300 capitalize">
                                    {{ order.payment_method || '—' }}
                                </td>
                                <td v-if="visibleColumns.status" class="px-6 py-4 text-center">
                                    <span 
                                        v-if="statusBadgeMap[order.status]"
                                        :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border', statusBadgeMap[order.status].color]"
                                    >
                                        <component :is="statusBadgeMap[order.status].icon" class="h-3.5 w-3.5" />
                                        {{ statusBadgeMap[order.status].label }}
                                    </span>
                                </td>
                                <td v-if="visibleColumns.payment_status" class="px-6 py-4 text-center">
                                    <span 
                                        v-if="paymentBadgeMap[order.payment_status]"
                                        :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold', paymentBadgeMap[order.payment_status].color]"
                                    >
                                        {{ paymentBadgeMap[order.payment_status].label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button @click="viewOrder(order)" size="sm" variant="outline" class="flex items-center gap-1.5 h-8">
                                            <Eye class="h-3.5 w-3.5" />
                                            Detail
                                        </Button>
                                        <Button 
                                            v-if="order.payment_status === 'unpaid' && order.status !== 'cancelled'" 
                                            @click="openPaymentModal(order)" 
                                            size="sm" 
                                            class="bg-brand-600 hover:bg-brand-700 text-white flex items-center gap-1.5 h-8"
                                        >
                                            <CreditCard class="h-3.5 w-3.5" />
                                            Bayar
                                        </Button>
                                        <Button @click="printReceipt(order)" size="sm" variant="ghost" class="h-8 text-slate-500 hover:text-brand-600">
                                            <Printer class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 📋 PAGINATION FOOTER -->
                <div v-if="pagination.total > 0" class="flex items-center justify-between border-t border-slate-100 p-4 dark:border-slate-800">
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        Menampilkan <span class="font-bold">{{ pagination.from }}</span> sampai <span class="font-bold">{{ pagination.to }}</span> dari <span class="font-bold">{{ pagination.total }}</span> pesanan
                    </span>

                    <div class="flex items-center gap-1.5">
                        <Button 
                            variant="outline" 
                            size="icon-sm" 
                            :disabled="pagination.current_page === 1 || loading"
                            @click="changePage(1)"
                            title="Halaman Pertama"
                        >
                            <ChevronsLeft class="h-4 w-4" />
                        </Button>
                        <Button 
                            variant="outline" 
                            size="icon-sm" 
                            :disabled="pagination.current_page === 1 || loading"
                            @click="changePage(pagination.current_page - 1)"
                            title="Halaman Sebelumnya"
                        >
                            <ChevronLeft class="h-4 w-4" />
                        </Button>

                        <div class="flex items-center gap-1 px-2">
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Halaman {{ pagination.current_page }} / {{ pagination.last_page }}
                            </span>
                        </div>

                        <Button 
                            variant="outline" 
                            size="icon-sm" 
                            :disabled="pagination.current_page === pagination.last_page || loading"
                            @click="changePage(pagination.current_page + 1)"
                            title="Halaman Selanjutnya"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                        <Button 
                            variant="outline" 
                            size="icon-sm" 
                            :disabled="pagination.current_page === pagination.last_page || loading"
                            @click="changePage(pagination.last_page)"
                            title="Halaman Terakhir"
                        >
                            <ChevronsRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- 📄 DETIL PESANAN DIALOG -->
        <Dialog :open="detailOpen" @update:open="detailOpen = $event">
            <DialogContent class="sm:max-w-lg max-h-[85vh] overflow-y-auto">
                <DialogHeader class="border-b pb-4">
                    <DialogTitle class="text-xl font-bold flex items-center justify-between">
                        <span>Detail Pesanan: {{ selectedOrder?.order_number }}</span>
                        <Badge 
                            v-if="selectedOrder && paymentBadgeMap[selectedOrder.payment_status]"
                            :class="[paymentBadgeMap[selectedOrder.payment_status].color]"
                        >
                            {{ paymentBadgeMap[selectedOrder.payment_status].label }}
                        </Badge>
                    </DialogTitle>
                    <DialogDescription class="flex items-center gap-2 mt-1">
                        <Calendar class="h-4 w-4 text-slate-400" />
                        <span>{{ formatDate(selectedOrder?.created_at) }}</span>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedOrder" class="py-4 space-y-4">
                    <!-- Info Pemesan -->
                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-3 rounded-lg dark:bg-slate-900 text-sm">
                        <div>
                            <span class="text-slate-400 block text-xs font-semibold">Tipe Layanan</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ selectedOrder.order_type === 'dine_in' ? 'Dine In' : 'Take Away' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-xs font-semibold">Lokasi Meja</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ selectedOrder.table?.name || 'Take Away' }}
                            </span>
                        </div>
                    </div>

                    <!-- List Item Makanan -->
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-2">Item Hidangan</h4>
                        <div class="divide-y border rounded-lg overflow-hidden bg-white dark:bg-slate-950 dark:border-slate-800">
                            <div v-for="item in selectedOrder.items" :key="item.id" class="p-3 flex items-start justify-between gap-3 hover:bg-slate-50/50">
                                <div>
                                    <span class="font-semibold text-slate-800 dark:text-slate-200 text-sm block">
                                        {{ item.name }} <span class="text-slate-400">x{{ item.qty }}</span>
                                    </span>
                                    
                                    <!-- Options & Toppings details -->
                                    <div v-if="item.variants?.length" class="text-xs text-slate-500 mt-0.5 font-medium">
                                        Varian: {{ formatVariants(item.variants) }}
                                    </div>
                                    <div v-if="item.add_ons?.length" class="text-xs text-brand-600 font-semibold uppercase mt-0.5">
                                        Topping: {{ item.add_ons.map((a: any) => a.name).join(', ') }}
                                    </div>
                                    <div v-if="item.notes" class="text-xs text-amber-600 mt-1 italic font-semibold">
                                        Catatan: "${item.notes}"
                                    </div>
                                </div>
                                <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                    {{ formatRupiah(item.price * item.qty) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Total Rincian Biaya -->
                    <div class="space-y-1.5 border-t pt-4 text-sm">
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Subtotal</span>
                            <span>{{ formatRupiah(selectedOrder.total_amount) }}</span>
                        </div>
                        <div v-if="selectedOrder.discount_amount" class="flex justify-between text-rose-600 font-medium">
                            <span>Potongan Diskon</span>
                            <span>-{{ formatRupiah(selectedOrder.discount_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Pajak Restoran (10%)</span>
                            <span>+{{ formatRupiah(selectedOrder.tax_amount) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-base text-slate-900 dark:text-slate-100 border-t border-dashed pt-2.5">
                            <span>Total Pembayaran</span>
                            <span>{{ formatRupiah(selectedOrder.final_amount) }}</span>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t pt-4 gap-2 flex-col sm:flex-row">
                    <!-- Cancel Action if unpaid and active -->
                    <Button 
                        v-if="selectedOrder && selectedOrder.status !== 'cancelled' && selectedOrder.payment_status === 'unpaid'"
                        @click="cancelOrder" 
                        variant="destructive"
                        :disabled="cancelLoading"
                        class="sm:mr-auto"
                    >
                        Batalkan Pesanan
                    </Button>

                    <Button @click="detailOpen = false" variant="outline">Tutup</Button>
                    
                    <Button @click="printReceipt(selectedOrder)" class="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center gap-1.5">
                        <Printer class="h-4 w-4" />
                        Cetak Struk
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- 💳 FORM PEMBAYARAN KASIR -->
        <Dialog :open="paymentOpen" @update:open="paymentOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader class="border-b pb-4">
                    <DialogTitle class="text-xl font-bold flex items-center gap-2">
                        <CreditCard class="h-5 w-5 text-brand-500" />
                        Pembayaran Transaksi
                    </DialogTitle>
                    <DialogDescription>
                        Pesanan: {{ selectedOrder?.order_number }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedOrder" class="py-4 space-y-4">
                    <div class="bg-brand-50 p-4 rounded-xl dark:bg-brand-950/20 text-center">
                        <span class="text-xs text-slate-500 font-semibold block uppercase tracking-wider mb-1">Jumlah Tagihan</span>
                        <span class="text-3xl font-black text-brand-700 dark:text-brand-400">
                            {{ formatRupiah(selectedOrder.final_amount) }}
                        </span>
                    </div>

                    <!-- Diskon Input -->
                    <div>
                        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-1.5">Diskon Tambahan (Rp)</label>
                        <Input 
                            v-model.number="discountAmount" 
                            type="number" 
                            placeholder="0"
                            class="h-10 border-slate-200 focus-visible:ring-brand-500"
                        />
                    </div>

                    <!-- Pilihan Metode Pembayaran -->
                    <div>
                        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 block mb-2">Metode Pembayaran</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button 
                                type="button"
                                @click="selectedPaymentMethod = 'cash'"
                                :class="[
                                    'py-3.5 px-3 rounded-lg border text-sm font-semibold flex flex-col items-center justify-center gap-1.5 transition-all',
                                    selectedPaymentMethod === 'cash' 
                                        ? 'border-brand-500 bg-brand-50/50 text-brand-700 ring-2 ring-brand-500/20 dark:bg-brand-950/20' 
                                        : 'border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-600 dark:border-slate-800'
                                ]"
                            >
                                <span class="text-lg">💵</span>
                                Cash / Tunai
                            </button>
                            <button 
                                type="button"
                                @click="selectedPaymentMethod = 'qris'"
                                :class="[
                                    'py-3.5 px-3 rounded-lg border text-sm font-semibold flex flex-col items-center justify-center gap-1.5 transition-all',
                                    selectedPaymentMethod === 'qris' 
                                        ? 'border-brand-500 bg-brand-50/50 text-brand-700 ring-2 ring-brand-500/20 dark:bg-brand-950/20' 
                                        : 'border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-600 dark:border-slate-800'
                                ]"
                            >
                                <span class="text-lg">📱</span>
                                QRIS
                            </button>
                            <button 
                                type="button"
                                @click="selectedPaymentMethod = 'transfer'"
                                :class="[
                                    'py-3.5 px-3 rounded-lg border text-sm font-semibold flex flex-col items-center justify-center gap-1.5 transition-all',
                                    selectedPaymentMethod === 'transfer' 
                                        ? 'border-brand-500 bg-brand-50/50 text-brand-700 ring-2 ring-brand-500/20 dark:bg-brand-950/20' 
                                        : 'border-slate-200 hover:bg-slate-50 hover:border-slate-300 text-slate-600 dark:border-slate-800'
                                ]"
                            >
                                <span class="text-lg">🏦</span>
                                Bank Transfer
                            </button>
                        </div>

                        <!-- Bank Selection for Bank Transfer -->
                        <div v-if="selectedPaymentMethod === 'transfer'" class="mt-4 bg-brand-50/50 dark:bg-brand-950/10 p-4 rounded-xl border border-brand-100 dark:border-brand-900/50 animate-in slide-in-from-bottom-2 duration-300">
                            <label class="text-[10px] font-black text-brand-500 uppercase tracking-widest block mb-2">Pilih Bank Transfer VA</label>
                            <div class="grid grid-cols-2 gap-2 max-h-[160px] overflow-y-auto pr-1 custom-scrollbar">
                                <button v-for="b in banksList" :key="b.id" @click="selectedBank = b.id" type="button" class="py-2.5 px-3 rounded-lg border text-[11px] font-bold transition-all text-left flex items-center justify-between" :class="selectedBank === b.id ? 'border-brand-500 bg-brand-50 text-brand-700 font-extrabold dark:bg-brand-950/50' : 'border-slate-200 bg-white text-slate-500 hover:border-brand-200 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900'">
                                    <span>{{ b.name }}</span>
                                    <span v-if="selectedBank === b.id" class="w-1.5 h-1.5 rounded-full bg-brand-500"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t pt-4">
                    <Button @click="paymentOpen = false" variant="outline" class="h-10">Batal</Button>
                    <Button 
                        @click="submitPayment" 
                        :disabled="paying"
                        class="bg-brand-600 hover:bg-brand-700 text-white h-10 px-6 flex items-center justify-center gap-2"
                    >
                        <RefreshCw v-if="paying" class="animate-spin h-4 w-4" />
                        <Check class="h-4 w-4" />
                        <span>Selesaikan Transaksi</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- 📱 DIALOG: Midtrans QRIS Sandbox Code -->
        <Dialog :open="qrModalOpen" @update:open="val => { qrModalOpen = val; if (!val) { stopPolling(); qrUrl = ''; vaNumber = ''; vaBank = ''; fetchOrders(pagination.current_page); } }">
            <DialogContent class="sm:max-w-md text-center">
                <DialogHeader class="border-b pb-4">
                    <DialogTitle class="text-xl font-bold flex items-center justify-center gap-2">
                        <CreditCard class="h-6 w-6 text-brand-600" />
                        {{ vaNumber ? 'Virtual Account Pembayaran' : 'Scan QRIS Pembayaran' }}
                    </DialogTitle>
                    <DialogDescription class="text-xs uppercase font-semibold text-slate-500">
                        No. Pesanan: {{ activeOrderNumber }}
                    </DialogDescription>
                </DialogHeader>

                <div class="py-6 flex flex-col items-center justify-center space-y-4">
                    <div class="bg-brand-50/50 p-6 rounded-[30px] border-2 border-dashed border-brand-200 dark:bg-brand-950/20 dark:border-brand-900/50 flex flex-col items-center justify-center">
                        <div v-if="paymentSuccess" class="h-60 w-60 flex flex-col items-center justify-center space-y-4 bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-inner">
                            <div class="h-24 w-24 rounded-full bg-emerald-50 dark:bg-emerald-950/30 border-4 border-emerald-500 text-emerald-500 flex items-center justify-center animate-bounce shadow-lg shadow-emerald-100 dark:shadow-none">
                                <Check class="h-12 w-12 stroke-[3]" />
                            </div>
                            <span class="text-emerald-600 dark:text-emerald-400 font-extrabold text-sm uppercase tracking-wider animate-pulse">Pembayaran Berhasil!</span>
                        </div>
                        <div v-else-if="vaNumber" class="h-60 w-60 flex flex-col items-center justify-center p-4 bg-white dark:bg-slate-900 rounded-2xl shadow-inner border border-slate-100 dark:border-slate-800 space-y-2">
                            <span class="text-xs font-bold text-brand-600 dark:text-brand-400 uppercase tracking-widest text-center leading-none">
                                <template v-if="requestedBank && requestedBank.toLowerCase() !== vaBank.toLowerCase()">
                                    {{ requestedBank.toUpperCase() }}<br/><span class="text-[9px] text-slate-400 font-semibold">(via {{ vaBank.toUpperCase() }} VA)</span>
                                </template>
                                <template v-else>
                                    {{ vaBank.toUpperCase() }} {{ vaBank.toLowerCase() === 'mandiri' ? 'BILL' : 'VA' }}
                                </template>
                            </span>
                            <div v-if="vaBank.toLowerCase() === 'mandiri' && vaNumber.includes(' - ')" class="w-full space-y-2">
                                <div class="p-2 bg-brand-50 dark:bg-brand-950/30 rounded-xl w-full text-center">
                                    <span class="text-[9px] font-black uppercase text-slate-400 block mb-0.5">Kode Perusahaan (Biller)</span>
                                    <span class="text-sm font-black text-slate-800 dark:text-slate-200 tracking-wider block select-all font-mono">{{ vaNumber.split(' - ')[0] }}</span>
                                </div>
                                <div class="p-2 bg-brand-50 dark:bg-brand-950/30 rounded-xl w-full text-center">
                                    <span class="text-[9px] font-black uppercase text-slate-400 block mb-0.5">Kode Pelanggan (Bill Key)</span>
                                    <span class="text-base font-black text-slate-800 dark:text-slate-200 tracking-wider block select-all font-mono">{{ vaNumber.split(' - ')[1] }}</span>
                                </div>
                            </div>
                            <div v-else class="p-3 bg-brand-50 dark:bg-brand-950/30 rounded-xl w-full text-center">
                                <span class="text-xl font-black text-slate-800 dark:text-slate-200 tracking-wider block select-all font-mono">{{ vaNumber }}</span>
                            </div>
                            <p v-if="requestedBank && requestedBank.toLowerCase() !== vaBank.toLowerCase()" class="text-[8px] text-amber-600 dark:text-amber-400 font-extrabold uppercase leading-tight text-center">
                                *PENTING: Pilih bank tujuan "BANK {{ vaBank.toUpperCase() }}" saat transfer
                            </p>
                            <p class="text-[10px] text-slate-400 font-bold uppercase leading-tight text-center">
                                Salin nomor di atas untuk simulasi pembayaran
                            </p>
                        </div>
                        <img 
                            v-else-if="qrUrl" 
                            :src="qrUrl" 
                            alt="QRIS Code" 
                            class="h-60 w-60 object-contain mix-blend-multiply dark:mix-blend-normal rounded-2xl" 
                        />
                        <div v-else class="h-60 w-60 flex items-center justify-center text-slate-400 bg-white rounded-2xl">
                            <Loader2 class="animate-spin h-8 w-8 text-brand-500" />
                        </div>
                        
                        <span class="mt-4 text-xs font-bold text-slate-500 uppercase tracking-widest block">Total Tagihan</span>
                        <span class="text-3xl font-black text-brand-600 dark:text-brand-400">{{ formatRupiah(activeOrderFinalAmount) }}</span>
                    </div>


                </div>

                <DialogFooter class="border-t pt-4">
                    <Button 
                        @click="() => { qrModalOpen = false; stopPolling(); qrUrl = ''; vaNumber = ''; vaBank = ''; fetchOrders(pagination.current_page); }" 
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white h-12 rounded-xl font-bold uppercase tracking-wider"
                    >
                        Selesai / Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
