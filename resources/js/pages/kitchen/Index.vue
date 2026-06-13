<script setup lang="ts">
import {
	AlertCircle,
	ArrowLeft,
	Check,
	CheckCircle2,
	ChefHat,
	ChevronLeft,
	ChevronRight,
	Clock,
	History,
	LayoutDashboard,
	Loader2,
	MoveRight,
	RotateCcw,
	Utensils,
	Volume2,
	VolumeX,
	X,
	Search,
	Menu,
	SlidersHorizontal,
	Bell
} from "lucide-vue-next";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Dapur (KDS)", href: "#" },
		],
	},
});

const orders = ref<any[]>([]);
const menuItems = ref<any[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const viewMode = ref<"active" | "history">("active");
const historyOrders = ref<any[]>([]);

// Real-time ticking time tracking
const nowTime = ref(Date.now());
let timeTicker: any;

// Sidebar states
const sidebarOpen = ref(true);
const sidebarTab = ref<'summary' | 'availability'>('summary');
const searchMenuQuery = ref('');

// Filter & Search states
const searchQuery = ref('');
const showFilters = ref(false);
const filterOrderType = ref<'all' | 'dine-in' | 'takeaway'>('all');
const filterOrderStatus = ref<'all' | 'preparing' | 'ready' | 'pending'>('all');

// Sound Notification controls
const soundEnabled = ref(true);
const ttsEnabled = ref(false);
let lastOrdersCount = 0;

// Element Reference for horizontal scroll
const orderScrollContainer = ref<HTMLElement | null>(null);

const fetchOrders = async (silent = false) => {
	if (!silent) {
		loading.value = true;
	} else {
		refreshing.value = true;
	}

	try {
		const res = await fetch("/api/kitchen/orders", {
			headers: { Accept: "application/json" },
		});

		if (res.ok) {
			const data = await res.json();
			orders.value = data;

			// Bell chime on new orders arrival
			if (silent && soundEnabled.value && data.length > lastOrdersCount) {
				playChime();
				// Auto TTS announcements for new order
				if (ttsEnabled.value) {
					// Speak the latest new order
					const latestOrder = data[data.length - 1];
					if (latestOrder) {
						speakOrder(latestOrder.id);
					}
				}
			}
			lastOrdersCount = data.length;
		}
	} catch (e) {
		console.error("Gagal mengambil data pesanan:", e);
	} finally {
		loading.value = false;
		refreshing.value = false;
	}
};

const fetchMenuItems = async () => {
	try {
		const res = await fetch("/api/kitchen/menu-items", {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			menuItems.value = await res.json();
		}
	} catch (e) {
		console.error("Gagal mengambil daftar menu:", e);
	}
};

const toggleHistory = async () => {
	if (viewMode.value === "active") {
		loading.value = true;

		try {
			const res = await fetch("/api/orders?status=served&per_page=20", {
				headers: { Accept: "application/json" },
			});

			if (res.ok) {
				const data = await res.json();
				historyOrders.value = data.data;
				viewMode.value = "history";
			}
		} catch (e) {
			console.error(e);
		} finally {
			loading.value = false;
		}
	} else {
		viewMode.value = "active";
		fetchOrders();
	}
};

const updateStatus = async (orderId: number, status: string) => {
	try {
		const res = await fetch(`/api/kitchen/orders/${orderId}/status`, {
			method: "PATCH",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
			body: JSON.stringify({ status }),
		});

		if (res.ok) {
			if (viewMode.value === "history") {
				await toggleHistory(); // Refresh history
			} else {
				await fetchOrders(true);
			}
		} else {
			const data = await res.json();
			alert(data.message || "Gagal memperbarui status");
		}
	} catch (e) {
		console.error("Terjadi kesalahan:", e);
		alert("Kesalahan koneksi");
	}
};

const toggleItemStatus = async (item: any) => {
	const newStatus = item.status === "done" ? "cooking" : "done";
	await updateItemStatus(item, newStatus);
};

const updateItemStatus = async (item: any, status: string) => {
	try {
		const res = await fetch(`/api/kitchen/items/${item.id}/status`, {
			method: "PATCH",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
			body: JSON.stringify({ status }),
		});

		if (res.ok) {
			item.status = status;
		}
	} catch (e) {
		console.error(e);
	}
};

// Toggle menu item availability (active/inactive / available/sold_out)
const toggleMenuItemStatus = async (menuItem: any) => {
	const oldStatus = menuItem.status;
	const newStatus = oldStatus === 'available' ? 'sold_out' : 'available';
	
	// Optimistic update
	menuItem.status = newStatus;

	try {
		const res = await fetch(`/api/kitchen/menu-items/${menuItem.id}/toggle`, {
			method: "PATCH",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			}
		});

		if (!res.ok) {
			// Rollback
			menuItem.status = oldStatus;
			alert("Gagal memperbarui status menu");
		} else {
			// Refresh orders to sync menu item availability status inline
			fetchOrders(true);
		}
	} catch (e) {
		menuItem.status = oldStatus;
		console.error(e);
	}
};

const isAllItemsDone = (order: any) => {
	return order.items.every(
		(item: any) => item.status === "done" || item.status === "cancelled",
	);
};

// Play nice kitchen notification sound
const playChime = () => {
	try {
		const audio = new Audio("https://assets.mixkit.co/active_storage/sfx/2869/2869-84.wav");
		audio.volume = 0.5;
		audio.play().catch(() => {});
	} catch (e) {
		console.warn("Audio play failed:", e);
	}
};

// Horizontal Scroll logic
const scrollGrid = (direction: 'left' | 'right') => {
	if (orderScrollContainer.value) {
		const scrollAmount = 400;
		const container = orderScrollContainer.value;
		container.scrollTo({
			left: direction === 'left' ? container.scrollLeft - scrollAmount : container.scrollLeft + scrollAmount,
			behavior: 'smooth'
		});
	}
};

// ─── Filtered Active Orders Calculation ──────────────────────────────────────
const filteredOrders = computed(() => {
	return orders.value.filter((order) => {
		// 1. Text Search (order number or table number)
		if (searchQuery.value) {
			const s = searchQuery.value.toLowerCase();
			const matchesNumber = order.order_number.toLowerCase().includes(s);
			const matchesTable = order.table?.number?.toLowerCase().includes(s);
			const matchesName = order.customer?.name?.toLowerCase().includes(s);
			if (!matchesNumber && !matchesTable && !matchesName) return false;
		}

		// 2. Order Type Filter (Dine-in vs Takeaway)
		if (filterOrderType.value !== 'all') {
			const isDineIn = order.order_type === 'dine_in' || !!order.table;
			if (filterOrderType.value === 'dine-in' && !isDineIn) return false;
			if (filterOrderType.value === 'takeaway' && isDineIn) return false;
		}

		// 3. Status Filter (Pending, Preparing, Ready)
		if (filterOrderStatus.value !== 'all') {
			if (order.status !== filterOrderStatus.value) return false;
		}

		return true;
	});
});

// ─── Summary Calculation (Grouped by Category) ───────────────────────────────
const productSummary = computed(() => {
	const summary: Record<
		string,
		{ category: string; items: Record<string, number> }
	> = {};

	orders.value.forEach((order) => {
		if (order.status !== "ready") {
			order.items.forEach((item: any) => {
				if (item.status !== "done" && item.status !== "cancelled") {
					const catName =
						item.menu_item?.category?.name ||
						(item.package_id ? "Paket" : "Lainnya");

					if (!summary[catName]) {
						summary[catName] = { category: catName, items: {} };
					}

					if (!summary[catName].items[item.name]) {
						summary[catName].items[item.name] = 0;
					}

					summary[catName].items[item.name] += item.qty;
				}
			});
		}
	});

	return Object.values(summary).sort((a, b) =>
		a.category.localeCompare(b.category),
	);
});

// Grouped Menu Items for availability settings
const groupedMenuItems = computed(() => {
	const grouped: Record<string, any[]> = {};
	
	menuItems.value.forEach(item => {
		const catName = item.category?.name || 'Lainnya';
		// Filter by search inside sidebar
		if (searchMenuQuery.value) {
			const s = searchMenuQuery.value.toLowerCase();
			if (!item.name.toLowerCase().includes(s)) return;
		}

		if (!grouped[catName]) {
			grouped[catName] = [];
		}
		grouped[catName].push(item);
	});

	return Object.entries(grouped).map(([category, items]) => ({
		category,
		items
	})).sort((a, b) => a.category.localeCompare(b.category));
});

// Polling for new orders every 12 seconds
let pollInterval: any;
onMounted(() => {
	fetchOrders();
	fetchMenuItems();
	
	// Active ticker to make waiting timers tick real-time
	timeTicker = setInterval(() => {
		nowTime.value = Date.now();
	}, 15000);

	pollInterval = setInterval(() => {
		if (viewMode.value === "active") {
			fetchOrders(true);
		}
	}, 12000);
});

onUnmounted(() => {
	clearInterval(pollInterval);
	clearInterval(timeTicker);
});

const formatTime = (dateString: string) => {
	const date = new Date(dateString);

	return date.toLocaleTimeString("id-ID", {
		hour: "2-digit",
		minute: "2-digit",
	});
};

const getWaitTime = (dateString: string) => {
	const start = new Date(dateString).getTime();
	const diff = Math.floor((nowTime.value - start) / 60000); // minutes
	return diff >= 0 ? diff : 0;
};

const getHeaderColor = (order: any) => {
	const wait = getWaitTime(order.created_at);

	if (order.status === "preparing") {
		return "bg-brand-600 text-white dark:bg-brand-700";
	}

	if (order.status === "ready") {
		return "bg-emerald-500 text-white";
	}

	if (wait > 20) {
		return "bg-red-600 text-white";
	}

	if (wait > 10) {
		return "bg-amber-500 text-white";
	}

	return "bg-slate-800 text-white dark:bg-slate-900";
};

// ─── Text-to-Speech (TTS) Logic ──────────────────────────────────────────────
const speakText = (text: string) => {
	console.log("[TTS] Mencoba menyuarakan teks:", text);

	if (!window.speechSynthesis) {
		console.error("[TTS] Browser Anda tidak mendukung Web Speech API (speechSynthesis).");
		return;
	}

	window.speechSynthesis.cancel();

	const utterance = new SpeechSynthesisUtterance(text);
	utterance.lang = "id-ID";
	utterance.rate = 1.0;
	utterance.pitch = 1.0;

	const voices = window.speechSynthesis.getVoices();
	let idVoice = voices.find(
		(v) =>
			v.lang.toLowerCase().replace("_", "-").includes("id") &&
			v.localService === true,
	);

	if (!idVoice) {
		idVoice = voices.find((v) =>
			v.lang.toLowerCase().replace("_", "-").includes("id"),
		);
	}

	if (idVoice) {
		utterance.voice = idVoice;
	}

	window.speechSynthesis.speak(utterance);
};

const speakItem = async (itemId: number) => {
	try {
		const res = await fetch(`/api/kitchen/items/${itemId}/tts-text`, {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			const data = await res.json();
			speakText(data.text);
		}
	} catch (e) {
		console.error("[TTS Item] Error fetching:", e);
	}
};

const speakOrder = async (orderId: number) => {
	try {
		const res = await fetch(`/api/kitchen/orders/${orderId}/tts-text`, {
			headers: { Accept: "application/json" },
		});
		if (res.ok) {
			const data = await res.json();
			speakText(data.text);
		}
	} catch (e) {
		console.error("[TTS Order] Error fetching:", e);
	}
};

watch(sidebarTab, (val) => {
	if (val === 'availability') {
		fetchMenuItems();
	}
});
</script>

<template>
    <div class="flex h-[calc(100vh-64px)] bg-slate-100 dark:bg-neutral-950 overflow-hidden font-sans select-none">
        
        <!-- 🛒 MAIN AREA: Order Cards & Navigation -->
        <div class="flex-1 flex flex-col overflow-hidden relative">
            
            <!-- ⚡ HEADER ACTION BAR: Compact Search & Popover Filters -->
            <div class="bg-white dark:bg-neutral-900 border-b border-slate-200 dark:border-neutral-800 px-6 py-2.5 flex items-center justify-between gap-4 shrink-0 shadow-sm z-20">
                <div class="flex items-center gap-3">
                    <h1 class="text-sm font-black text-slate-800 dark:text-neutral-250 uppercase tracking-wider flex items-center gap-1.5 mr-2">
                        <ChefHat class="h-4 w-4 text-brand-600 dark:text-brand-500" />
                        <span>KDS Dapur</span>
                    </h1>
                    
                    <!-- Search Input -->
                    <div class="relative w-56">
                        <Search class="absolute left-3 top-2 h-3.5 w-3.5 text-slate-400 dark:text-neutral-500" />
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Cari Meja / No Pesanan..."
                            class="w-full pl-9 pr-3 py-1.5 text-[11px] rounded-xl border border-slate-200 dark:border-neutral-800 bg-slate-50 dark:bg-neutral-950 text-slate-800 dark:text-neutral-100 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-brand-500 transition"
                        />
                    </div>

                    <!-- Filter Button -->
                    <button 
                        @click="showFilters = !showFilters"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold rounded-xl border transition cursor-pointer select-none"
                        :class="[
                            showFilters 
                                ? 'bg-brand-50 border-brand-200 text-brand-600 dark:bg-brand-950/20 dark:border-brand-900 dark:text-brand-400' 
                                : 'bg-slate-50 border-slate-205 dark:border-neutral-800 text-slate-600 dark:text-neutral-350 hover:bg-slate-100 dark:hover:bg-neutral-850 bg-white dark:bg-neutral-900'
                        ]"
                    >
                        <SlidersHorizontal class="h-3 w-3" />
                        <span>Filter</span>
                        <Badge v-if="filterOrderType !== 'all' || filterOrderStatus !== 'all'" class="bg-brand-600 text-white text-[8px] h-4 min-w-4 px-1 flex items-center justify-center border-none">Active</Badge>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Bell Sound alert toggle -->
                    <button 
                        @click="soundEnabled = !soundEnabled"
                        class="p-1.5 border rounded-lg hover:bg-slate-50 dark:hover:bg-neutral-850 transition cursor-pointer"
                        :class="soundEnabled ? 'border-brand-200 bg-brand-50 text-brand-600 dark:bg-brand-950/20 dark:border-brand-900' : 'border-slate-200 dark:border-neutral-800 text-slate-455'"
                        :title="soundEnabled ? 'Matikan Suara Bel' : 'Nyalakan Suara Bel'"
                    >
                        <Bell class="h-3.5 w-3.5" :class="{ 'animate-bounce': soundEnabled && refreshing }" />
                    </button>

                    <!-- Text to Speech switch -->
                    <button 
                        @click="ttsEnabled = !ttsEnabled"
                        class="px-2.5 py-1.5 text-[10px] font-black uppercase border rounded-lg transition cursor-pointer flex items-center gap-1"
                        :class="ttsEnabled ? 'border-orange-200 bg-orange-50 text-orange-650 dark:bg-orange-950/20 dark:border-orange-900' : 'border-slate-200 dark:border-neutral-800 text-slate-400'"
                    >
                        <Volume2 class="h-3 w-3" />
                        <span>TTS: {{ ttsEnabled ? 'ON' : 'OFF' }}</span>
                    </button>
                </div>

                <!-- 🎛️ ABSOLUTE FILTERS POPOVER -->
                <div 
                    v-if="showFilters" 
                    class="absolute left-64 top-14 w-80 bg-white dark:bg-neutral-900 border border-slate-200 dark:border-neutral-800 shadow-2xl rounded-xl p-4 z-50 flex flex-col gap-4 animate-in fade-in slide-in-from-top-2 duration-200"
                >
                    <div class="flex justify-between items-center pb-2 border-b dark:border-neutral-800">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pengaturan Filter</span>
                        <button @click="showFilters = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-neutral-200 cursor-pointer">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Filter Order Type -->
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-wider text-slate-400">Tipe Pesanan</label>
                        <div class="grid grid-cols-3 bg-slate-100 dark:bg-neutral-950 p-0.5 rounded-lg border dark:border-neutral-850">
                            <button @click="filterOrderType = 'all'" class="py-1 text-[9px] font-black uppercase rounded-md transition cursor-pointer" :class="filterOrderType === 'all' ? 'bg-white dark:bg-neutral-800 text-brand-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ">Semua</button>
                            <button @click="filterOrderType = 'dine-in'" class="py-1 text-[9px] font-black uppercase rounded-md transition cursor-pointer" :class="filterOrderType === 'dine-in' ? 'bg-white dark:bg-neutral-800 text-brand-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ">Dine-In</button>
                            <button @click="filterOrderType = 'takeaway'" class="py-1 text-[9px] font-black uppercase rounded-md transition cursor-pointer" :class="filterOrderType === 'takeaway' ? 'bg-white dark:bg-neutral-800 text-brand-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ">Takeaway</button>
                        </div>
                    </div>

                    <!-- Filter Order Status -->
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-black uppercase tracking-wider text-slate-400">Status Proses</label>
                        <div class="grid grid-cols-3 bg-slate-100 dark:bg-neutral-950 p-0.5 rounded-lg border dark:border-neutral-850">
                            <button @click="filterOrderStatus = 'all'" class="py-1 text-[9px] font-black uppercase rounded-md transition cursor-pointer" :class="filterOrderStatus === 'all' ? 'bg-white dark:bg-neutral-800 text-brand-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ">Semua</button>
                            <button @click="filterOrderStatus = 'preparing'" class="py-1 text-[9px] font-black uppercase rounded-md transition cursor-pointer" :class="filterOrderStatus === 'preparing' ? 'bg-white dark:bg-neutral-800 text-brand-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ">Cooking</button>
                            <button @click="filterOrderStatus = 'ready'" class="py-1 text-[9px] font-black uppercase rounded-md transition cursor-pointer" :class="filterOrderStatus === 'ready' ? 'bg-white dark:bg-neutral-800 text-brand-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ">Ready</button>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-2 pt-2 border-t dark:border-neutral-800">
                        <button 
                            @click="filterOrderType = 'all'; filterOrderStatus = 'all'; showFilters = false;"
                            class="px-2.5 py-1.5 text-[9px] font-black uppercase border border-slate-200 dark:border-neutral-850 rounded-lg hover:bg-slate-50 dark:hover:bg-neutral-800 text-slate-500 cursor-pointer"
                        >
                            Reset Filter
                        </button>
                        <button 
                            @click="showFilters = false"
                            class="px-2.5 py-1.5 text-[9px] font-black uppercase bg-slate-900 text-white rounded-lg hover:bg-black dark:bg-neutral-800 dark:hover:bg-neutral-750 cursor-pointer"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scrollable Grid of Cards -->
            <div ref="orderScrollContainer" class="flex-1 overflow-x-auto p-6 custom-scrollbar bg-slate-50/50 dark:bg-neutral-950/50">
                <div v-if="loading" class="h-full flex items-center justify-center">
                    <Loader2 class="h-12 w-12 animate-spin text-brand-600 dark:text-brand-500" />
                </div>

                <!-- HISTORY VIEW -->
                <div v-else-if="viewMode === 'history'" class="h-full flex flex-col animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-black text-slate-800 dark:text-neutral-200 uppercase tracking-tight">Recall Orders</h2>
                            <p class="text-xs font-bold text-slate-500 dark:text-neutral-400 uppercase tracking-widest">Daftar pesanan yang baru saja diselesaikan</p>
                        </div>
                        <Button @click="viewMode = 'active'" variant="outline" class="bg-white dark:bg-neutral-800 border-slate-200 dark:border-neutral-700 text-slate-600 dark:text-neutral-300 h-12 rounded-xl gap-2 font-black uppercase text-[10px] tracking-widest px-6 shadow-sm">
                            <ArrowLeft class="h-4 w-4" /> Kembali ke Pesanan Aktif
                        </Button>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                        <div v-if="historyOrders.length === 0" class="h-full flex flex-col items-center justify-center text-slate-300 dark:text-neutral-700">
                            <History class="h-20 w-20 mb-4 opacity-20" />
                            <p class="font-black uppercase tracking-widest">Belum ada riwayat pesanan</p>
                        </div>
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 pb-8">
                            <div v-for="h in historyOrders" :key="h.id" class="bg-white dark:bg-neutral-900 border-2 border-slate-100 dark:border-neutral-800 shadow-sm flex flex-col overflow-hidden rounded-xl">
                                <div class="p-6 flex flex-col gap-4 flex-1">
                                    <div class="flex justify-between items-start">
                                        <div class="flex gap-4 items-center">
                                            <Badge class="h-12 w-20 bg-slate-900 dark:bg-neutral-950 text-white font-black text-sm flex items-center justify-center border-none rounded-xl">#{{ h.order_number.slice(-4) }}</Badge>
                                            <div>
                                                <h4 class="text-lg font-black text-slate-800 dark:text-neutral-200 uppercase leading-none">{{ h.table ? 'Table ' + h.table.number : 'Take Away' }}</h4>
                                                <p class="text-[10px] font-bold text-slate-400 dark:text-neutral-550 uppercase tracking-widest mt-1">{{ formatTime(h.updated_at) }} • {{ h.customer?.name || 'Walk-in' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <Separator class="bg-slate-50 dark:bg-neutral-800" />
                                    <div class="space-y-2">
                                        <div v-for="item in h.items" :key="item.id" class="flex justify-between text-[11px] font-bold text-slate-500 dark:text-neutral-400 uppercase">
                                            <span>{{ item.qty }}x {{ item.name }}</span>
                                            <span v-if="item.status === 'cancelled'" class="text-red-500">CANCELLED</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Footer Recall -->
                                <button @click="updateStatus(h.id, 'preparing')" class="h-12 w-full bg-slate-50 dark:bg-neutral-850 hover:bg-brand-50 dark:hover:bg-brand-950/20 border-t border-slate-100 dark:border-neutral-800 text-brand-600 dark:text-brand-400 font-black text-[10px] tracking-widest uppercase transition-colors">
                                    Recall Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTIVE ORDERS VIEW -->
                <template v-else>
                    <div v-if="filteredOrders.length === 0" class="h-full flex flex-col items-center justify-center bg-white dark:bg-neutral-900 border-2 border-dashed border-slate-200 dark:border-neutral-800 shadow-inner rounded-xl p-8 text-center max-w-lg mx-auto my-12">
                        <CheckCircle2 class="h-24 w-24 text-emerald-100 dark:text-emerald-950 mb-4" />
                        <h2 class="text-2xl font-black text-slate-400 dark:text-neutral-550 uppercase">KDS Dapur Bersih!</h2>
                        <p class="text-sm font-bold text-slate-300 dark:text-neutral-600 italic mt-2">Menunggu pesanan baru masuk atau ubah filter pencarian Anda...</p>
                    </div>

                    <div v-else class="flex gap-6 h-full min-w-max pb-4">
                        <div v-for="order in filteredOrders" :key="order.id" 
                            class="w-[340px] bg-white dark:bg-neutral-900 border-2 flex flex-col shadow-2xl rounded-xl transition-all relative overflow-hidden group"
                            :class="[
                                order.status === 'preparing' ? 'border-brand-500 ring-8 ring-brand-500/10 scale-[1.02] z-10' : 'border-slate-100 dark:border-neutral-800',
                                order.status === 'ready' ? 'opacity-40 grayscale-[0.5]' : ''
                            ]"
                        >
                            <!-- Order Header -->
                            <div class="p-4 shrink-0" :class="getHeaderColor(order)">
                                <div class="flex justify-between items-center mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-black">#{{ order.order_number.slice(-4) }}</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest opacity-70 bg-black/20 px-1.5 py-0.5 rounded">{{ order.order_type }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs font-black bg-black/15 px-2 py-1 rounded-lg">
                                        <button 
                                            @click.stop="speakOrder(order.id)" 
                                            class="p-1 rounded-md bg-white/10 hover:bg-white/20 text-white transition-all mr-1 cursor-pointer"
                                            title="Bacakan Pesanan"
                                        >
                                            <Volume2 class="h-3 w-3" />
                                        </button>
                                        <Clock class="h-3 w-3" />
                                        {{ getWaitTime(order.created_at) }}'
                                    </div>
                                </div>
                                <h3 class="text-xl font-black uppercase tracking-tight truncate leading-none mt-2">
                                    {{ order.table ? 'Table ' + order.table.number : 'Take Away' }}
                                </h3>
                                <div class="mt-3 flex items-center justify-between opacity-80 text-[10px]">
                                    <span class="font-black uppercase tracking-widest">{{ order.customer?.name || 'Walk-in' }}</span>
                                    <span class="font-bold uppercase tracking-widest">{{ formatTime(order.created_at) }}</span>
                                </div>
                            </div>

                            <!-- Items List -->
                            <div class="flex-1 overflow-y-auto p-5 space-y-4 custom-scrollbar bg-slate-50/50 dark:bg-neutral-900/40">
                                <div v-for="item in order.items" :key="item.id" 
                                    @click="toggleItemStatus(item)"
                                    class="cursor-pointer transition-all active:scale-[0.98]"
                                >
                                    <div class="flex gap-3 items-start">
                                        <!-- Item Qty / Check Status Checkbox -->
                                        <div class="h-8 w-8 rounded-xl flex items-center justify-center text-xs font-black shrink-0 border-2"
                                            :class="item.status === 'done' ? 'bg-emerald-500 border-emerald-500 text-white' : (item.status === 'cancelled' ? 'bg-red-500 border-red-500 text-white' : 'bg-white dark:bg-neutral-800 border-slate-200 dark:border-neutral-700 text-slate-700 dark:text-neutral-200')"
                                        >
                                            <Check v-if="item.status === 'done'" class="h-4 w-4" />
                                            <X v-else-if="item.status === 'cancelled'" class="h-4 w-4" />
                                            <span v-else>{{ item.qty }}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start">
                                                <p class="text-sm font-black uppercase leading-tight" :class="item.status === 'done' ? 'line-through text-slate-350 dark:text-neutral-600' : (item.status === 'cancelled' ? 'line-through text-red-300 dark:text-red-950' : 'text-slate-800 dark:text-neutral-100')">
                                                    {{ item.name }}
                                                </p>
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <!-- Global availability toggle button directly inside card list! -->
                                                    <button 
                                                        v-if="item.menu_item && item.status !== 'cancelled' && order.status !== 'ready'"
                                                        @click.stop="toggleMenuItemStatus(item.menu_item)"
                                                        class="p-1 rounded-md border text-[8px] font-black uppercase transition-all select-none cursor-pointer"
                                                        :class="item.menu_item.status === 'available' 
                                                            ? 'bg-emerald-50 border-emerald-250 text-emerald-600 dark:bg-emerald-950/20 dark:border-emerald-900 dark:text-emerald-400' 
                                                            : 'bg-rose-50 border-rose-250 text-rose-600 dark:bg-rose-950/20 dark:border-rose-900 dark:text-rose-400'"
                                                        :title="item.menu_item.status === 'available' ? 'Atur menjadi Habis (Sold Out)' : 'Atur menjadi Tersedia'"
                                                    >
                                                        {{ item.menu_item.status === 'available' ? 'Ready' : 'Habis' }}
                                                    </button>

                                                    <button 
                                                        v-if="item.status !== 'cancelled'"
                                                        @click.stop="speakItem(item.id)" 
                                                        class="text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors p-1"
                                                        title="Bacakan Menu"
                                                    >
                                                        <Volume2 class="h-3.5 w-3.5" />
                                                    </button>
                                                    <button 
                                                        v-if="item.status !== 'cancelled' && order.status !== 'ready'"
                                                        @click.stop="updateItemStatus(item, 'cancelled')" 
                                                        class="text-slate-300 hover:text-red-500 transition-colors p-1"
                                                    >
                                                        <X class="h-3 w-3" />
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Customizations -->
                                            <div v-if="item.variants?.length || item.add_ons?.length" class="flex flex-wrap gap-1 mt-1">
                                                <span v-for="v in item.variants" :key="v.id" class="text-[9px] font-black text-slate-500 dark:text-neutral-450 bg-white dark:bg-neutral-800 border border-slate-200 dark:border-neutral-700 px-1.5 py-0.5 rounded italic">
                                                    {{ v.option_name ? v.option_name + ': ' : '' }}{{ v.name || v.value_name }}
                                                </span>
                                                <span v-for="a in item.add_ons" :key="a.name" class="text-[9px] font-black text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-950/20 border border-brand-100 dark:border-brand-900/50 px-1.5 py-0.5 rounded uppercase">
                                                    +{{ a.name }}
                                                </span>
                                            </div>
 
                                            <p v-if="item.notes" class="mt-2 p-2 bg-amber-50 dark:bg-amber-950/10 rounded-xl border border-amber-100 dark:border-amber-900/20 text-[10px] font-bold text-amber-700 dark:text-amber-400 italic">
                                                "{{ item.notes }}"
                                            </p>
                                            
                                            <Badge v-if="item.status === 'cancelled'" variant="outline" class="mt-2 bg-red-50 dark:bg-red-950/10 text-red-600 dark:text-red-400 border-red-100 dark:border-red-900/30 text-[8px] font-black uppercase">CANCELLED</Badge>
                                        </div>
                                    </div>
                                    <Separator class="bg-slate-200/50 dark:bg-neutral-800/50 mt-4" />
                                </div>

                                <!-- Order Level Notes -->
                                <div v-if="order.notes" class="p-4 bg-red-50 dark:bg-red-950/10 rounded-xl border border-red-100 dark:border-red-900/30 flex items-start gap-3">
                                    <AlertCircle class="h-4 w-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                                    <p class="text-[10px] font-bold text-red-800 dark:text-red-400 italic leading-relaxed uppercase tracking-tight">{{ order.notes }}</p>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="p-5 bg-white dark:bg-neutral-900 border-t border-slate-100 dark:border-neutral-800 shrink-0">
                                <!-- ACTION: Pending -> Cooking -->
                                <Button 
                                    v-if="order.status === 'pending'" 
                                    @click="updateStatus(order.id, 'preparing')"
                                    class="w-full h-14 rounded-xl bg-brand-600 hover:bg-brand-700 dark:bg-brand-700 dark:hover:bg-brand-600 text-white font-black uppercase tracking-widest shadow-xl shadow-brand-100 dark:shadow-none flex items-center justify-between px-6 transition-all active:scale-[0.98] cursor-pointer"
                                >
                                    <span>COOKING</span>
                                    <Utensils class="h-5 w-5" />
                                </Button>

                                <!-- ACTION: Preparing -> Done -->
                                <div v-else-if="order.status === 'preparing'" class="flex gap-2">
                                    <Button 
                                        variant="outline"
                                        @click="updateStatus(order.id, 'pending')"
                                        class="h-14 w-14 rounded-xl border-slate-200 dark:border-neutral-800 text-slate-300 dark:text-neutral-700 hover:bg-slate-50 dark:hover:bg-neutral-800 hover:text-slate-500 cursor-pointer"
                                    >
                                        <RotateCcw class="h-5 w-5" />
                                    </Button>
                                    <Button 
                                        @click="updateStatus(order.id, 'ready')"
                                        class="flex-1 h-14 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black uppercase tracking-widest shadow-lg shadow-emerald-100 dark:shadow-none flex items-center justify-between px-6 transition-all active:scale-[0.98] cursor-pointer"
                                    >
                                        <span>DONE</span>
                                        <CheckCircle2 class="h-5 w-5" />
                                    </Button>
                                </div>

                                <!-- ACTION: Ready -> Served (Bump) -->
                                <Button 
                                    v-else-if="order.status === 'ready'" 
                                    @click="updateStatus(order.id, 'served')"
                                    class="w-full h-14 rounded-xl bg-slate-900 hover:bg-black dark:bg-neutral-850 dark:hover:bg-neutral-800 text-white font-black uppercase tracking-widest shadow-xl flex items-center justify-between px-6 transition-all active:scale-[0.98] cursor-pointer"
                                >
                                    <span>BUMP ORDER</span>
                                    <MoveRight class="h-5 w-5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Bottom Action Bar (Horizontal scroll buttons & Refresh status) -->
            <div class="h-14 bg-slate-900 dark:bg-neutral-900 border-t dark:border-neutral-800 flex items-center justify-between px-8 shrink-0">
                <div class="flex gap-4">
                    <button 
                        @click="scrollGrid('left')"
                        class="text-white hover:text-white/80 font-black uppercase tracking-[0.2em] text-[10px] gap-2 flex items-center p-2 rounded-lg cursor-pointer transition select-none"
                    >
                        <ChevronLeft class="h-4 w-4" /> Prev
                    </button>
                    <button 
                        @click="scrollGrid('right')"
                        class="text-white hover:text-white/80 font-black uppercase tracking-[0.2em] text-[10px] gap-2 flex items-center p-2 rounded-lg cursor-pointer transition select-none"
                    >
                        Next <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex gap-3 items-center">
                    <div v-if="refreshing" class="text-[9px] font-black text-white uppercase tracking-[0.2em] mr-4 flex items-center gap-2">
                        <Loader2 class="h-3 w-3 animate-spin" /> Syncing
                    </div>
                    
                    <!-- Toggle right sidebar view -->
                    <button 
                        @click="sidebarOpen = !sidebarOpen"
                        class="bg-slate-800 border-slate-700 text-white hover:bg-slate-700 h-10 rounded-xl gap-3 font-black uppercase text-[10px] tracking-widest px-6 flex items-center border cursor-pointer animate-in fade-in"
                    >
                        <SlidersHorizontal class="h-4 w-4" />
                        {{ sidebarOpen ? 'Sembunyikan Menu/Ringkasan' : 'Tampilkan Menu/Ringkasan' }}
                    </button>

                    <Button @click="toggleHistory" variant="outline" class="bg-slate-800 border-slate-700 text-white hover:bg-slate-700 h-10 rounded-xl gap-3 font-black uppercase text-[10px] tracking-widest px-6 cursor-pointer">
                        <History v-if="viewMode === 'active'" class="h-4 w-4" />
                        <Utensils v-else class="h-4 w-4" />
                        {{ viewMode === 'active' ? 'RECALL LAST' : 'PESANAN AKTIF' }}
                    </Button>
                    
                    <Button @click="fetchOrders()" variant="outline" class="bg-brand-600 border-brand-500 text-white hover:bg-brand-500 h-10 rounded-xl gap-3 font-black uppercase text-[10px] tracking-widest px-6 cursor-pointer">
                        REFRESH
                    </Button>
                </div>
            </div>
        </div>

        <!-- 📊 COLLAPSIBLE RIGHT SIDEBAR: Product Summary & Menu Availability -->
        <div 
            v-if="sidebarOpen"
            class="w-[340px] bg-white dark:bg-neutral-900 border-l border-slate-200 dark:border-neutral-800 flex flex-col shrink-0 animate-in slide-in-from-right duration-350"
        >
            <!-- Sidebar Header & Tab selector -->
            <div class="p-6 border-b border-slate-100 dark:border-neutral-800 bg-slate-900 dark:bg-neutral-950 text-white shrink-0">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Panel Dapur</h2>
                    <Badge class="bg-brand-600 text-white text-[9px] px-2 py-0 border-none">{{ orders.length }} Pesanan</Badge>
                </div>
                
                <!-- Tab Controls -->
                <div class="bg-slate-800 dark:bg-neutral-900 p-1 rounded-xl flex gap-1 border border-slate-700/50 dark:border-neutral-800/30 w-full mt-2">
                    <button 
                        @click="sidebarTab = 'summary'"
                        class="flex-1 text-center py-2 text-[10px] font-black uppercase rounded-lg transition cursor-pointer"
                        :class="sidebarTab === 'summary' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                    >
                        Kebutuhan
                    </button>
                    <button 
                        @click="sidebarTab = 'availability'"
                        class="flex-1 text-center py-2 text-[10px] font-black uppercase rounded-lg transition cursor-pointer"
                        :class="sidebarTab === 'availability' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-400 hover:text-white'"
                    >
                        Ketersediaan
                    </button>
                </div>
            </div>

            <!-- TAB 1: PRODUCT SUMMARY (Kebutuhan Menu) -->
            <div v-if="sidebarTab === 'summary'" class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-white dark:bg-neutral-900">
                <div v-if="productSummary.length === 0" class="h-40 flex flex-col items-center justify-center text-slate-300 dark:text-neutral-700 italic p-8 text-center border-2 border-dashed border-slate-100 dark:border-neutral-800 rounded-xl">
                    <p class="text-xs font-bold uppercase tracking-widest">Tidak ada item aktif</p>
                </div>
                
                <div v-for="cat in productSummary" :key="cat.category" class="mb-8 last:mb-0 animate-in fade-in duration-200">
                    <h3 class="text-[10px] font-black text-brand-500 uppercase tracking-[0.2em] mb-4 border-b border-brand-105 dark:border-brand-900 pb-2 flex justify-between items-center">
                        {{ cat.category }}
                        <span class="text-slate-355 dark:text-neutral-600 font-normal">({{ Object.keys(cat.items).length }})</span>
                    </h3>
                    <div class="space-y-3">
                        <div v-for="(qty, name) in cat.items" :key="name" class="flex justify-between items-start gap-4">
                            <span class="text-xs font-bold text-slate-700 dark:text-neutral-300 uppercase leading-tight flex-1">{{ name }}</span>
                            <span class="text-sm font-black text-slate-900 dark:text-neutral-100 shrink-0">x{{ qty }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: MENU AVAILABILITY TOGGLES (Ketersediaan Menu) -->
            <div v-else class="flex-1 flex flex-col overflow-hidden bg-white dark:bg-neutral-900">
                <!-- Search within Sidebar Menu -->
                <div class="p-4 border-b border-slate-100 dark:border-neutral-800 shrink-0">
                    <div class="relative">
                        <Search class="absolute left-3.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
                        <input 
                            v-model="searchMenuQuery" 
                            type="text" 
                            placeholder="Cari item menu..."
                            class="w-full pl-9 pr-3 py-1.5 text-[11px] rounded-xl border border-slate-200 dark:border-neutral-800 bg-slate-50 dark:bg-neutral-950 text-slate-800 dark:text-neutral-100 placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-brand-500"
                        />
                    </div>
                </div>

                <!-- Scrollable Menu list with active/inactive toggles -->
                <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                    <div v-if="groupedMenuItems.length === 0" class="text-center text-slate-400 dark:text-neutral-600 py-12 text-xs">
                        Tidak ada item menu cocok.
                    </div>
                    
                    <div v-for="cat in groupedMenuItems" :key="cat.category" class="mb-6 animate-in fade-in duration-200">
                        <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] border-b border-slate-100 dark:border-neutral-800 pb-1.5 mb-3">{{ cat.category }}</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <div 
                                v-for="menuItem in cat.items" 
                                :key="menuItem.id" 
                                @click="toggleMenuItemStatus(menuItem)"
                                class="flex flex-col border rounded-xl overflow-hidden cursor-pointer transition relative bg-slate-50/50 dark:bg-neutral-950/30 hover:bg-slate-50 dark:hover:bg-neutral-950/60 border-slate-100/50 dark:border-neutral-850/50"
                                :class="[menuItem.status === 'available' ? 'opacity-100' : 'grayscale opacity-50 contrast-75']"
                            >
                                <!-- Image or Placeholder -->
                                <div class="w-full h-20 bg-slate-100 dark:bg-neutral-900 flex items-center justify-center overflow-hidden relative border-b dark:border-neutral-850">
                                    <img 
                                        v-if="menuItem.image_url" 
                                        :src="menuItem.image_url" 
                                        :alt="menuItem.name" 
                                        class="w-full h-full object-cover" 
                                    />
                                    <div v-else class="flex items-center justify-center text-slate-350 dark:text-neutral-700">
                                        <Utensils class="h-6 w-6" />
                                    </div>
                                    <!-- Status Badge Overlay -->
                                    <span 
                                        class="absolute top-1 right-1 px-1.5 py-0.5 text-[7px] font-black uppercase rounded shadow-xs"
                                        :class="menuItem.status === 'available' ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'"
                                    >
                                        {{ menuItem.status === 'available' ? 'Ready' : 'Habis' }}
                                    </span>
                                </div>

                                <!-- Text Details -->
                                <div class="p-2 flex-1 flex flex-col justify-between">
                                    <p class="text-[9px] font-black text-slate-800 dark:text-neutral-200 uppercase line-clamp-2 leading-tight">{{ menuItem.name }}</p>
                                    <p class="text-[8px] font-semibold text-slate-400 dark:text-neutral-500 mt-1">Rp {{ Math.round(menuItem.price).toLocaleString('id-ID') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Icon decoration -->
            <div class="p-6 border-t border-slate-100 dark:border-neutral-800 bg-slate-50 dark:bg-neutral-950/20 flex items-center justify-center shrink-0">
                <ChefHat class="h-6 w-6 text-slate-300 dark:text-neutral-850" />
            </div>
        </div>
    </div>
</template>

<style>
.custom-scrollbar::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
