<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import {
	CheckCircle2,
	ChevronLeft,
	Flame,
	History,
	Info,
	List,
	LogOut,
	Mic,
	Minus,
	Plus,
	ShieldAlert,
	ShoppingBag,
	Tag,
	Trash2,
	UtensilsCrossed,
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const props = defineProps({
	token: String,
	table: Object,
	categories: Array,
	menuItems: Array,
	packages: Array,
	promos: Array,
	customer: Object,
});

const getPromoImage = (img: string | null) => {
	if (!img) {
		return "";
	}

	if (img.startsWith("http") || img.startsWith("/")) {
		return img;
	}

	if (img.startsWith("promo_")) {
		return `/${img}`;
	}

	return `/storage/${img}`;
};

const isMounted = ref(false);
let orderPollInterval: any = null;

const guestOrders = ref<any[]>([]);
const isGuestHistoryOpen = ref(false);
const isLoadingHistory = ref(false);
const isListening = ref(false);
const errorSpeech = ref("");

const SpeechRecognition =
	window.SpeechRecognition || window.webkitSpeechRecognition;
let recognition: any = null;

if (SpeechRecognition) {
	errorSpeech.value = "";
	recognition = new SpeechRecognition();
	recognition.continuous = false; // Berhenti merekam setelah selesai berbicara
	recognition.lang = "id-ID"; // Set bahasa ke Bahasa Indonesia
	recognition.interimResults = false; // Hanya ambil hasil akhir saja
	recognition.onstart = () => {
		isListening.value = true;
	};
	recognition.onend = () => {
		isListening.value = false;
	};
	recognition.onerror = (event: any) => {
		console.error("speech recognition error", event.error);
		isListening.value = false;

		if (event.error === "network") {
			errorSpeech.value =
				"Gagal mengenali suara: Pastikan perangkat terhubung ke internet dan tidak diblokir oleh jaringan.";
		} else if (event.error === "not-allowed") {
			errorSpeech.value =
				"Gagal mengakses mikrofon: Harap izinkan akses mikrofon pada peramban Anda.";
		}
	};
}

function startSpeechToText(targetRef: { value: string }) {
	if (!recognition) {
		alert(
			"Fitur Voice-to-Text tidak didukung di peramban ini. Silakan gunakan Google Chrome.",
		);

		return;
	}

	if (isListening.value) {
		recognition.stop();

		return;
	}

	recognition.onresult = (event: any) => {
		const transcript = event.results[0][0].transcript;
		// Tambahkan transkrip suara ke input teks
		targetRef.value = targetRef.value
			? `${targetRef.value} ${transcript}`
			: transcript;
	};

	recognition.start();
}

onMounted(() => {
	isMounted.value = true;

	// Check if there is an active order in local storage
	const savedOrder = localStorage.getItem(`kiosk_active_order_${props.token}`);

	if (savedOrder) {
		try {
			const parsedOrder = JSON.parse(savedOrder);

			// Cek apakah order belum selesai/batal, kalau sudah selesai, tak perlu diload lagi sebagai active order
			if (
				["pending", "preparing", "ready", "served"].includes(parsedOrder.status)
			) {
				submittedOrder.value = parsedOrder;
				startOrderPolling();
			} else {
				localStorage.removeItem(`kiosk_active_order_${props.token}`);
			}
		} catch {
			localStorage.removeItem(`kiosk_active_order_${props.token}`);
		}
	}

	// Load guest order history
	const history = localStorage.getItem(`kiosk_guest_orders_${props.token}`);

	if (history) {
		try {
			guestOrders.value = JSON.parse(history);
		} catch {
			localStorage.removeItem(`kiosk_guest_orders_${props.token}`);
		}
	}
});

onUnmounted(() => {
	if (orderPollInterval) {
		clearInterval(orderPollInterval);
	}
});

function startOrderPolling() {
	if (orderPollInterval) {
		clearInterval(orderPollInterval);
	}

	orderPollInterval = setInterval(async () => {
		if (!submittedOrder.value) {
			clearInterval(orderPollInterval);

			return;
		}

		try {
			const res = await fetch(
				`/kiosk/${props.token}/api/orders/${submittedOrder.value.id}`,
				{
					headers: { Accept: "application/json" },
				},
			);

			if (res.ok) {
				const data = await res.json();
				submittedOrder.value = data;
				localStorage.setItem(
					`kiosk_active_order_${props.token}`,
					JSON.stringify(data),
				);

				// Sync with guest history
				const idx = guestOrders.value.findIndex((o) => o.id === data.id);

				if (idx !== -1) {
					guestOrders.value[idx] = data;
					localStorage.setItem(
						`kiosk_guest_orders_${props.token}`,
						JSON.stringify(guestOrders.value),
					);
				}

				// Stop polling if completed or cancelled to save resources
				if (data.status === "completed" || data.status === "cancelled") {
					clearInterval(orderPollInterval);
				}
			}
		} catch {
			console.error("Error polling order status");
		}
	}, 5000);
}

function clearActiveOrder() {
	submittedOrder.value = null;
	localStorage.removeItem(`kiosk_active_order_${props.token}`);

	if (orderPollInterval) {
		clearInterval(orderPollInterval);
	}
}

// ── State ─────────────────────────────────────────────────────────────────
const activeCategory = ref(null);
const cart = ref([]);
const isCartOpen = ref(false);
const isItemModalOpen = ref(false);
const selectedItem = ref(null);
const itemQty = ref(1);
const itemNotes = ref("");
const selectedVariants = ref({});
const selectedAddOns = ref([]);
const guestName = ref(props.customer?.name ?? "");
const orderNotes = ref("");
const isSubmitting = ref(false);
const submittedOrder = ref(null);
const selectedPromo = ref(null);

const isPromoDetailOpen = ref(false);
const activeDetailPromo = ref<any>(null);
const promoCodeInput = ref("");
const promoError = ref("");
const promoCopied = ref(false);

function applyPromoCode() {
	promoError.value = "";
	if (!promoCodeInput.value.trim()) {
		promoError.value = "Masukkan kode promo terlebih dahulu.";
		return;
	}
	const code = promoCodeInput.value.trim().toLowerCase();
	const promo = props.promos?.find((p: any) => p.code.toLowerCase() === code);
	if (promo) {
		selectedPromo.value = promo;
		promoCodeInput.value = "";
	} else {
		promoError.value = "Kode promo tidak ditemukan atau tidak valid.";
	}
}

function copyPromoCode(code: string) {
	navigator.clipboard.writeText(code).then(() => {
		promoCopied.value = true;
		setTimeout(() => {
			promoCopied.value = false;
		}, 2000);
	}).catch(() => {});
}

const isAuthModalOpen = ref(false);
const authMode = ref("login"); // 'login' | 'register'
const authLogin = ref("");
const authPassword = ref("");
const authName = ref("");
const authPhone = ref("");
const authEmail = ref("");
const authError = ref("");
const authLoading = ref(false);

// Poll order status after submission
// usePoll(5000, { only: [] }) // We now use custom polling with startOrderPolling

// ── Computed ──────────────────────────────────────────────────────────────
const filteredItems = computed(() => {
	const pkgs = props.packages?.map((p) => ({ ...p, isPackage: true })) || [];

	if (activeCategory.value === "paket") {
		return pkgs;
	}

	if (!activeCategory.value) {
		return [...(props.menuItems || []), ...pkgs];
	}

	return (props.menuItems || []).filter(
		(i) => i.category_id === activeCategory.value,
	);
});

const groupedItems = computed(() => {
	const items = filteredItems.value;
	const groups = {};

	items.forEach((item) => {
		let key = "";

		if (item.isPackage) {
			key = "Paket Spesial";
		} else {
			key = item.category?.name || "Lain-lain";
		}

		if (!groups[key]) {
			groups[key] = [];
		}

		groups[key].push(item);
	});

	return Object.entries(groups).map(([name, list]) => ({
		name,
		items: list,
	}));
});

const cartCount = computed(() => cart.value.reduce((s, i) => s + i.qty, 0));
const cartTotal = computed(() =>
	cart.value.reduce((s, i) => s + i.subtotal, 0),
);

const promoDiscount = computed(() => {
	if (!selectedPromo.value) {
		return 0;
	}

	const promo = selectedPromo.value;

	// Check condition
	if (promo.condition_type === "min_amount") {
		if (cartTotal.value < Number(promo.condition_value)) {
			return 0;
		}
	} else if (promo.condition_type === "min_qty") {
		if (cartCount.value < Number(promo.condition_value)) {
			return 0;
		}
	} else if (promo.condition_type === "specific_item_qty") {
		const itemQtyInCart = cart.value
			.filter((i: any) => i.menu_item_id === promo.condition_menu_item_id)
			.reduce((s, i) => s + i.qty, 0);

		if (itemQtyInCart < Number(promo.condition_value)) {
			return 0;
		}
	}

	// Calculate reward
	if (promo.reward_type === "discount_percent") {
		return Math.round(cartTotal.value * (Number(promo.reward_value) / 100));
	}

	if (promo.reward_type === "discount_nominal") {
		return Math.min(Number(promo.reward_value), cartTotal.value);
	}

	return 0;
});

const taxAmount = computed(() => {
	const subtotalAfterDiscount = Math.max(
		0,
		cartTotal.value - promoDiscount.value,
	);

	return Math.round(subtotalAfterDiscount * 0.1);
});

const finalTotal = computed(() => {
	const subtotalAfterDiscount = Math.max(
		0,
		cartTotal.value - promoDiscount.value,
	);

	return subtotalAfterDiscount + taxAmount.value;
});

function formatRp(amount) {
	return new Intl.NumberFormat("id-ID", {
		style: "currency",
		currency: "IDR",
		minimumFractionDigits: 0,
	}).format(amount);
}

// ── Item Modal ────────────────────────────────────────────────────────────
function openItem(item: any, isPackage = false) {
	selectedItem.value = { ...item, isPackage };
	itemQty.value = 1;
	itemNotes.value = "";
	selectedVariants.value = {};
	selectedAddOns.value = [];
	isItemModalOpen.value = true;
}

function closeItemModal() {
	isItemModalOpen.value = false;
}

function computedItemPrice() {
	if (!selectedItem.value) {
		return 0;
	}

	let price = Number(selectedItem.value.price);

	Object.entries(selectedVariants.value).forEach(([optId, val]) => {
		if (Array.isArray(val)) {
			val.forEach((vId) => {
				const opt = selectedItem.value.options?.find(
					(o: any) => o.id === Number(optId),
				);
				const optionVal = opt?.values?.find((v: any) => v.id === Number(vId));

				if (optionVal) {
					price += Number(optionVal.additional_price || 0);
				}
			});
		} else {
			const opt = selectedItem.value.options?.find(
				(o: any) => o.id === Number(optId),
			);
			const optionVal = opt?.values?.find((v: any) => v.id === Number(val));

			if (optionVal) {
				price += Number(optionVal.additional_price || 0);
			}
		}
	});

	for (const addOnId of selectedAddOns.value) {
		const addOn = selectedItem.value.add_ons?.find(
			(a: any) => a.id === addOnId,
		);

		if (addOn) {
			price += Number(addOn.price ?? 0);
		}
	}

	return price;
}

const toggleOption = (opt: any, valId: number) => {
	if (opt.type === "single") {
		selectedVariants.value[opt.id] = valId;
	} else {
		if (
			!selectedVariants.value[opt.id] ||
			!Array.isArray(selectedVariants.value[opt.id])
		) {
			selectedVariants.value[opt.id] = [];
		}

		const index = selectedVariants.value[opt.id].indexOf(valId);

		if (index > -1) {
			selectedVariants.value[opt.id].splice(index, 1);
		} else {
			selectedVariants.value[opt.id].push(valId);
		}
	}
};

const isOptionSelected = (opt: any, valId: number) => {
	const selected = selectedVariants.value[opt.id];

	if (!selected) {
		return false;
	}

	if (opt.type === "single") {
		return selected === valId;
	}

	return Array.isArray(selected) && selected.includes(valId);
};

function addToCart() {
	if (!selectedItem.value) {
		return;
	}

	const missingRequired = selectedItem.value.options?.filter((opt: any) => {
		const selected = selectedVariants.value[opt.id];

		return (
			opt.is_required &&
			(!selected || (Array.isArray(selected) && selected.length === 0))
		);
	});

	if (missingRequired?.length > 0) {
		alert(
			`Silakan pilih opsi wajib: ${missingRequired.map((o: any) => o.name).join(", ")}`,
		);

		return;
	}

	const price = computedItemPrice();
	const item = selectedItem.value;
	const isPackage = item.isPackage;

	const variantLabels: string[] = [];
	Object.entries(selectedVariants.value).forEach(([optId, val]) => {
		const opt = item.options?.find((o: any) => o.id === Number(optId));

		if (Array.isArray(val)) {
			val.forEach((vId) => {
				const optionVal = opt?.values?.find((v: any) => v.id === Number(vId));

				if (optionVal) {
					variantLabels.push(`${opt.name}: ${optionVal.name}`);
				}
			});
		} else {
			const optionVal = opt?.values?.find((v: any) => v.id === Number(val));

			if (optionVal) {
				variantLabels.push(`${opt.name}: ${optionVal.name}`);
			}
		}
	});

	const addOnLabels = selectedAddOns.value
		.map((id) => {
			return item.add_ons?.find((a: any) => a.id === id)?.name;
		})
		.filter(Boolean);

	const variantKey = Object.entries(selectedVariants.value)
		.map(([k, v]) => {
			if (Array.isArray(v)) {
				return `${k}:${[...v].sort().join("-")}`;
			}

			return `${k}:${v}`;
		})
		.sort()
		.join(",");

	const addOnKey = [...selectedAddOns.value].sort().join(",");
	const noteKey = (itemNotes.value || "").trim();
	const customizationHash = `${isPackage ? "pkg-" : "itm-"}${item.id}-${variantKey}-${addOnKey}-${noteKey}`;

	const existing = cart.value.find(
		(i: any) => i.customizationHash === customizationHash,
	);

	if (existing) {
		existing.qty += itemQty.value;
		existing.subtotal = existing.price * existing.qty;
	} else {
		cart.value.push({
			id: Date.now(),
			menu_item_id: isPackage ? null : item.id,
			package_id: isPackage ? item.id : null,
			isPackage,
			name: item.name,
			qty: itemQty.value,
			price,
			subtotal: price * itemQty.value,
			notes: itemNotes.value || null,
			variants: variantLabels,
			add_ons: addOnLabels,
			variantData: selectedVariants.value,
			addOnData: selectedAddOns.value,
			image_url: item.image_url,
			customizationHash,
		});
	}

	isItemModalOpen.value = false;
}

function removeFromCart(id: number) {
	cart.value = cart.value.filter((i: any) => i.id !== id);
}

function updateQty(id: number, delta: number) {
	const item: any = cart.value.find((i: any) => i.id === id);

	if (!item) {
		return;
	}

	item.qty = Math.max(1, item.qty + delta);
	item.subtotal = item.price * item.qty;
}

// ── Submit Order ──────────────────────────────────────────────────────────
async function submitOrder() {
	if (cart.value.length === 0) {
		return;
	}

	isSubmitting.value = true;

	const payload = {
		guest_name: guestName.value || null,
		notes: orderNotes.value || null,
		promo_id:
			selectedPromo.value &&
			(promoDiscount.value > 0 ||
				selectedPromo.value.reward_type === "free_item")
				? selectedPromo.value.id
				: null,
		items: cart.value.map((i: any) => ({
			menu_item_id: i.isPackage ? null : i.menu_item_id,
			package_id: i.isPackage ? i.package_id : null,
			name: i.name,
			qty: i.qty,
			price: i.price,
			notes: i.notes,
			variants: i.variantData,
			add_ons: i.addOnData,
		})),
	};

	try {
		const res = await fetch(`/kiosk/${props.token}/api/orders`, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				"X-CSRF-TOKEN":
					document.querySelector('meta[name="csrf-token"]')?.content ?? "",
				Accept: "application/json",
			},
			body: JSON.stringify(payload),
		});

		if (!res.ok) {
			if (res.status === 422) {
				const errData = await res.json();

				throw new Error(
					errData.message || "Stok tidak mencukupi untuk pesanan Anda.",
				);
			}

			throw new Error("Gagal mengirim pesanan. Silakan coba lagi.");
		}

		const data = await res.json();
		submittedOrder.value = data;
		localStorage.setItem(
			`kiosk_active_order_${props.token}`,
			JSON.stringify(data),
		);

		// Append to guest history
		guestOrders.value.unshift(data);

		if (guestOrders.value.length > 10) {
			guestOrders.value.pop();
		} // Keep last 10 max

		localStorage.setItem(
			`kiosk_guest_orders_${props.token}`,
			JSON.stringify(guestOrders.value),
		);

		cart.value = [];
		isCartOpen.value = false;
		startOrderPolling();
	} catch (e: any) {
		alert(e.message || "Gagal mengirim pesanan. Silakan coba lagi.");
	} finally {
		isSubmitting.value = false;
	}
}

async function openGuestHistory() {
	isGuestHistoryOpen.value = true;
	isLoadingHistory.value = true;

	try {
		// Fetch latest status for all orders in guestOrders
		const promises = guestOrders.value.map((order) =>
			fetch(`/kiosk/${props.token}/api/orders/${order.id}`, {
				headers: { Accept: "application/json" },
			}).then((r) => r.json()),
		);
		const updatedOrders = await Promise.all(promises);
		guestOrders.value = updatedOrders;
		localStorage.setItem(
			`kiosk_guest_orders_${props.token}`,
			JSON.stringify(guestOrders.value),
		);
	} catch {
		console.error("Failed to sync guest history");
	} finally {
		isLoadingHistory.value = false;
	}
}

// ── Auth ──────────────────────────────────────────────────────────────────
async function submitAuth() {
	authError.value = "";
	authLoading.value = true;
	const url =
		authMode.value === "login"
			? `/kiosk/${props.token}/api/auth/login`
			: `/kiosk/${props.token}/api/auth/register`;

	const body =
		authMode.value === "login"
			? { login: authLogin.value, password: authPassword.value }
			: {
					name: authName.value,
					email: authEmail.value || undefined,
					phone: authPhone.value || undefined,
					password: authPassword.value,
				};

	try {
		const res = await fetch(url, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				"X-CSRF-TOKEN":
					document.querySelector('meta[name="csrf-token"]')?.content ?? "",
				Accept: "application/json",
			},
			body: JSON.stringify(body),
		});
		const data = await res.json();

		if (!res.ok) {
			authError.value = data.message ?? "Terjadi kesalahan.";
		} else {
			router.reload({ only: ["customer"] });
			isAuthModalOpen.value = false;
		}
	} catch {
		authError.value = "Koneksi gagal. Coba lagi.";
	} finally {
		authLoading.value = false;
	}
}

async function logout() {
	await fetch(`/kiosk/${props.token}/api/auth/logout`, {
		method: "POST",
		headers: {
			"X-CSRF-TOKEN":
				document.querySelector('meta[name="csrf-token"]')?.content ?? "",
		},
	});
	router.reload({ only: ["customer"] });
}

const statusMap = {
	pending: {
		label: "Menunggu konfirmasi",
		color:
			"bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
	},
	preparing: {
		label: "Sedang dimasak",
		color:
			"bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400",
	},
	ready: {
		label: "Siap disajikan",
		color:
			"bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
	},
	served: {
		label: "Sudah disajikan",
		color:
			"bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
	},
};

function getStepIndex(status: string) {
	return ["pending", "preparing", "ready", "served"].indexOf(status);
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400..800;1,400..800&display=swap');

.font-garamond {
    font-family: 'EB Garamond', serif;
}
</style>

<template>
    <Head :title="`Self Order - Meja ${table.number}`" />
    <div
        class="font-garamond relative mx-auto flex min-h-[100dvh] max-w-md flex-col bg-background text-foreground shadow-2xl md:max-w-4xl md:border-x lg:max-w-6xl xl:max-w-7xl"
    >
        <!-- Header -->
        <header
            class="sticky top-0 z-40 flex flex-col justify-between gap-3 border-b bg-background/90 px-4 py-3 backdrop-blur-md md:flex-row md:items-center md:gap-0 md:px-6"
        >
            <div
                class="flex w-full items-center justify-between gap-3 md:w-auto md:justify-start"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <UtensilsCrossed class="h-5 w-5" />
                    </div>
                    <div>
                        <h1
                            class="text-base font-bold tracking-tight md:text-xl"
                        >
                            Self Order
                        </h1>
                        <div
                            class="text-xs font-medium text-muted-foreground md:text-sm"
                        >
                            Meja {{ table.number
                            }}<span v-if="table.name"> · {{ table.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div
                class="flex w-full items-center justify-end gap-2 border-t pt-2 md:w-auto md:border-t-0 md:pt-0"
            >
                <template v-if="customer">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="font-garamond h-8 flex-1 justify-center px-3 text-sm font-semibold md:h-10 md:flex-none md:text-base"
                        @click="router.visit(`/kiosk/${token}/orders`)"
                    >
                        <History class="mr-1 h-4 w-4 md:h-5 md:w-5" /> Riwayat
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 shrink-0 px-3 text-destructive hover:bg-destructive/10 hover:text-destructive md:h-10"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4 md:h-5 md:w-5" />
                    </Button>
                </template>
                <template v-else>
                    <Button
                        v-if="guestOrders.length > 0"
                        variant="ghost"
                        size="sm"
                        class="font-garamond h-8 flex-1 justify-center px-3 text-sm font-semibold md:h-10 md:flex-none md:text-base"
                        @click="openGuestHistory"
                    >
                        <History class="mr-1 h-4 w-4 md:h-5 md:w-5" /> Pesanan
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="font-garamond h-8 flex-1 justify-center px-4 text-sm font-semibold md:h-10 md:flex-none md:text-base"
                        @click="
                            isAuthModalOpen = true;
                            authMode = 'login';
                        "
                    >
                        Masuk / Daftar
                    </Button>
                </template>
            </div>
        </header>

        <!-- Order Confirmation -->
        <div
            v-if="submittedOrder"
            class="flex flex-1 items-center justify-center p-6"
        >
            <div
                class="mx-auto max-w-xs animate-in text-center duration-300 zoom-in-95 fade-in md:max-w-md"
            >
                <div
                    class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600 md:h-28 md:w-28 dark:bg-green-900/30 dark:text-green-500"
                >
                    <CheckCircle2 class="h-10 w-10 md:h-14 md:w-14" />
                </div>
                <h2 class="mb-2 text-2xl font-bold md:text-3xl">
                    Pesanan Dikirim!
                </h2>
                <p
                    class="mb-2 text-xl font-bold tracking-widest text-primary md:text-2xl"
                >
                    {{ submittedOrder.order_number }}
                </p>
                <p class="mb-6 text-base text-muted-foreground md:text-lg">
                    Silakan tunggu, pesananmu sedang diproses dapur.
                </p>

                <div
                    class="mb-6 inline-flex rounded-full px-4 py-2 text-sm font-semibold md:px-6 md:py-3 md:text-base"
                    :class="
                        statusMap[submittedOrder.status]?.color ||
                        'bg-secondary text-secondary-foreground'
                    "
                >
                    {{
                        statusMap[submittedOrder.status]?.label ??
                        submittedOrder.status
                    }}
                </div>

                <!-- Stepper Progress -->
                <div
                    v-if="getStepIndex(submittedOrder.status) !== -1"
                    class="mx-auto mb-8 w-full max-w-sm space-y-4 rounded-2xl border bg-secondary/30 p-5"
                >
                    <div class="relative flex items-center justify-between">
                        <!-- Connecting Line -->
                        <div
                            class="absolute top-3.5 right-[12.5%] left-[12.5%] -z-10 h-0.5 bg-muted"
                        >
                            <div
                                class="h-full bg-primary transition-all duration-500"
                                :style="{
                                    width:
                                        (getStepIndex(submittedOrder.status) /
                                            3) *
                                            100 +
                                        '%',
                                }"
                            ></div>
                        </div>

                        <!-- Steps -->
                        <div
                            v-for="(step, idx) in [
                                'pending',
                                'preparing',
                                'ready',
                                'served',
                            ]"
                            :key="step"
                            class="relative flex flex-1 flex-col items-center"
                        >
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-all duration-300"
                                :class="
                                    getStepIndex(submittedOrder.status) >= idx
                                        ? 'scale-110 bg-primary text-primary-foreground shadow-sm'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                <span class="relative z-10">{{ idx + 1 }}</span>
                            </div>
                            <span
                                class="mt-2 text-center text-[10px] font-semibold transition-colors md:text-xs"
                                :class="
                                    getStepIndex(submittedOrder.status) >= idx
                                        ? 'font-bold text-primary'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    step === 'pending'
                                        ? 'Diterima'
                                        : step === 'preparing'
                                          ? 'Dimasak'
                                          : step === 'ready'
                                            ? 'Siap'
                                            : 'Disajikan'
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <Button
                    class="font-garamond h-12 w-full rounded-xl text-lg font-bold tracking-wide md:h-14 md:text-xl"
                    @click="clearActiveOrder"
                >
                    <Plus class="mr-2 h-5 w-5 md:h-6 md:w-6" /> Pesan Lagi
                </Button>
            </div>
        </div>

        <!-- Main Content -->
        <main v-else class="flex-1 pb-32">
            <!-- Promo Banner Carousel/Cards -->
            <div v-if="promos && promos.length" class="px-4 pt-4 pb-2 md:px-6">
                <h3
                    class="font-garamond mb-3 text-lg font-bold text-primary md:text-xl"
                >
                    Promo Spesial Untukmu
                </h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div
                        v-for="promo in promos"
                        :key="promo.id"
                        class="relative cursor-pointer overflow-hidden rounded-2xl border-2 border-border hover:border-primary/50 transition-all duration-300"
                        @click="
                            activeDetailPromo = promo;
                            isPromoDetailOpen = true;
                        "
                    >
                        <div class="relative aspect-[21/9] bg-secondary">
                            <img
                                :src="getPromoImage(promo.image)"
                                class="h-full w-full object-cover"
                                :alt="promo.name"
                            />
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"
                            ></div>

                            <!-- Badges -->
                            <div
                                class="absolute top-3 left-3 flex flex-wrap gap-1.5"
                            >
                                <Badge
                                    class="bg-primary text-[10px] text-primary-foreground"
                                >
                                    {{
                                        promo.reward_type === 'free_item'
                                            ? 'Gratis Item'
                                            : promo.reward_type ===
                                                'discount_percent'
                                              ? `Diskon ${Number(promo.reward_value)}%`
                                              : `Potongan ${formatRp(promo.reward_value)}`
                                    }}
                                </Badge>
                                <Badge
                                    variant="secondary"
                                    class="text-[10px]"
                                    v-if="promo.condition_type === 'min_amount'"
                                >
                                    Min. {{ formatRp(promo.condition_value) }}
                                </Badge>
                                <Badge
                                    variant="secondary"
                                    class="text-[10px]"
                                    v-else-if="
                                        promo.condition_type === 'min_qty'
                                    "
                                >
                                    Min.
                                    {{ Number(promo.condition_value) }} Item
                                </Badge>
                                <Badge
                                    variant="secondary"
                                    class="text-[10px]"
                                    v-else-if="
                                        promo.condition_type ===
                                        'specific_item_qty'
                                    "
                                >
                                    Beli {{ Number(promo.condition_value) }}x
                                    {{ promo.condition_menu_item?.name }}
                                </Badge>
                            </div>

                            <div
                                class="absolute right-3 bottom-3 left-3 text-white"
                            >
                                <h4
                                    class="line-clamp-1 text-base leading-tight font-bold md:text-lg"
                                >
                                    {{ promo.name }}
                                </h4>
                                <p
                                    class="text-[11px] font-medium text-white/80"
                                >
                                    Kode:
                                    <span
                                        class="rounded bg-white/20 px-1 py-0.5 font-mono"
                                        >{{ promo.code }}</span
                                    >
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Tabs -->
            <div
                class="sticky top-[112px] z-30 flex scrollbar-none gap-2 overflow-x-auto border-b bg-background/95 p-4 backdrop-blur-sm md:top-[64px] md:px-6 [&::-webkit-scrollbar]:hidden"
            >
                <button
                    class="shrink-0 rounded-full border-2 px-5 py-2 text-base font-semibold transition-colors md:px-6 md:py-2.5 md:text-lg"
                    :class="
                        activeCategory === null
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground hover:bg-secondary'
                    "
                    @click="activeCategory = null"
                >
                    Semua
                </button>
                <button
                    class="shrink-0 rounded-full border-2 px-5 py-2 text-base font-semibold transition-colors md:px-6 md:py-2.5 md:text-lg"
                    :class="
                        activeCategory === 'paket'
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground hover:bg-secondary'
                    "
                    @click="activeCategory = 'paket'"
                >
                    Paket
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    class="shrink-0 rounded-full border-2 px-5 py-2 text-base font-semibold transition-colors md:px-6 md:py-2.5 md:text-lg"
                    :class="
                        activeCategory === cat.id
                            ? 'border-primary bg-primary/10 text-primary'
                            : 'border-border bg-background text-muted-foreground hover:bg-secondary'
                    "
                    @click="activeCategory = cat.id"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Menu List / Grid Grouped by Category -->
            <div class="space-y-8 px-4 pt-6 md:px-6">
                <div
                    v-for="group in groupedItems"
                    :key="group.name"
                    class="space-y-4"
                >
                    <h2
                        class="font-garamond border-b pb-2 text-xl font-bold text-primary md:text-2xl"
                    >
                        {{ group.name }}
                    </h2>
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <div
                            v-for="item in group.items"
                            :key="item.id"
                            class="group flex cursor-pointer flex-row items-stretch gap-4 border-b py-4 transition-colors hover:border-primary/40 md:flex-col md:gap-0 md:overflow-hidden md:rounded-2xl md:border md:bg-card md:py-0 md:shadow-sm"
                            @click="openItem(item, item.isPackage)"
                        >
                            <div
                                class="relative h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-secondary/50 shadow-sm md:aspect-[4/3] md:h-48 md:w-full md:rounded-none md:shadow-none"
                            >
                                <img
                                    v-if="item.image_url"
                                    :src="item.image_url"
                                    :alt="item.name"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                />
                                <UtensilsCrossed
                                    v-else
                                    class="absolute top-1/2 left-1/2 h-8 w-8 -translate-x-1/2 -translate-y-1/2 text-muted-foreground/30 md:h-12 md:w-12"
                                />
                                <Badge
                                    v-if="item.isPackage"
                                    class="font-garamond absolute top-2 right-2 bg-primary px-2 py-0.5 text-[9px] tracking-wider text-primary-foreground uppercase shadow-sm"
                                >
                                    Paket
                                </Badge>
                            </div>

                            <div
                                class="flex flex-1 flex-col justify-between py-1 md:p-4"
                            >
                                <div>
                                    <h3
                                        class="mb-1 text-lg leading-tight font-bold transition-colors group-hover:text-primary md:text-xl"
                                    >
                                        {{ item.name }}
                                    </h3>
                                    <p
                                        v-if="!item.isPackage"
                                        class="mb-2 line-clamp-2 text-sm leading-snug font-medium text-muted-foreground md:line-clamp-3 md:text-base"
                                    >
                                        {{ item.description }}
                                    </p>
                                    <div
                                        v-else-if="
                                            item.package_items &&
                                            item.package_items.length
                                        "
                                        class="mb-3 space-y-0.5 text-xs font-medium text-muted-foreground md:text-sm"
                                    >
                                        <div
                                            v-for="pkgItem in item.package_items"
                                            :key="pkgItem.id"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="font-semibold text-primary/80"
                                                >{{ pkgItem.qty }}x</span
                                            >
                                            <span>{{
                                                pkgItem.menu_item?.name ||
                                                pkgItem.inventory_item?.name
                                            }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="mt-auto flex items-center justify-between md:mt-4"
                                >
                                    <div
                                        class="text-base font-bold text-primary md:text-xl"
                                    >
                                        {{ formatRp(item.price) }}
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="font-garamond h-8 rounded-full border-primary px-4 font-bold text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground md:h-10 md:text-base"
                                    >
                                        Tambah
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Floating Cart Bar -->
        <div
            v-if="cartCount > 0 && !submittedOrder && isMounted"
            class="font-garamond fixed bottom-6 left-1/2 z-50 w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 animate-in slide-in-from-bottom-8 md:max-w-lg lg:max-w-2xl"
        >
            <Button
                size="lg"
                class="flex h-14 w-full items-center justify-between rounded-2xl px-5 text-lg font-bold tracking-wide shadow-xl shadow-primary/25 md:h-16 md:rounded-3xl md:px-6 md:text-xl"
                @click="isCartOpen = true"
            >
                <div class="flex items-center gap-3 md:gap-4">
                    <div
                        class="rounded-lg bg-primary-foreground/20 px-3 py-1 text-base text-primary-foreground md:rounded-xl md:px-4 md:py-1.5 md:text-lg"
                    >
                        {{ cartCount }}
                    </div>
                    <span>Total Harga</span>
                </div>
                <span>{{ formatRp(finalTotal) }}</span>
            </Button>
        </div>

        <!-- Cart Full Page Overlay -->
        <div
            v-if="isMounted && isCartOpen"
            class="fixed inset-0 z-50 flex justify-center bg-background/80 backdrop-blur-sm"
        >
            <div
                class="font-garamond flex h-full w-full max-w-md animate-in flex-col bg-background shadow-2xl duration-300 slide-in-from-right-8 md:max-w-4xl md:border-x lg:max-w-6xl xl:max-w-7xl"
            >
                <header
                    class="sticky top-0 z-10 flex flex-none items-center border-b bg-background px-4 py-3 md:px-6"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="font-garamond -ml-2 h-10 px-2 text-base text-muted-foreground"
                        @click="isCartOpen = false"
                    >
                        <ChevronLeft class="mr-1 h-6 w-6" /> Kembali
                    </Button>
                    <h1
                        class="mx-auto pr-16 text-lg font-bold md:pr-20 md:text-xl"
                    >
                        Pesananmu
                    </h1>
                </header>

                <div class="flex-1 overflow-y-auto">
                    <div
                        class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-6 py-4"
                    >
                        <div class="flex flex-col gap-4">
                            <div
                                v-for="item in cart"
                                :key="item.id"
                                class="flex gap-3 border-b pb-4 last:border-0 last:pb-0"
                            >
                                <div class="flex-1">
                                    <h4 class="text-base font-bold md:text-lg">
                                        {{ item.name }}
                                    </h4>
                                    <p
                                        v-if="item.variants.length"
                                        class="mt-0.5 text-sm text-muted-foreground md:text-base"
                                    >
                                        {{ item.variants.join(' · ') }}
                                    </p>
                                    <p
                                        v-if="item.add_ons.length"
                                        class="mt-0.5 text-sm text-muted-foreground md:text-base"
                                    >
                                        + {{ item.add_ons.join(', ') }}
                                    </p>
                                    <p
                                        v-if="item.notes"
                                        class="mt-0.5 text-sm text-muted-foreground italic md:text-base"
                                    >
                                        "{{ item.notes }}"
                                    </p>
                                    <div
                                        class="mt-1 text-base font-bold text-primary md:text-lg"
                                    >
                                        {{ formatRp(item.price) }}
                                    </div>
                                </div>
                                <div
                                    class="flex flex-col items-end justify-between"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-muted-foreground hover:text-destructive"
                                        @click="removeFromCart(item.id)"
                                    >
                                        <Trash2 class="h-5 w-5" />
                                    </Button>
                                    <div
                                        class="flex items-center gap-2 rounded-lg border bg-secondary p-1.5"
                                    >
                                        <button
                                            class="flex h-7 w-7 items-center justify-center rounded bg-background text-foreground shadow-sm active:scale-95"
                                            @click="updateQty(item.id, -1)"
                                        >
                                            <Minus class="h-3.5 w-3.5" />
                                        </button>
                                        <span
                                            class="w-5 text-center text-base font-bold"
                                            >{{ item.qty }}</span
                                        >
                                        <button
                                            class="flex h-7 w-7 items-center justify-center rounded bg-background text-foreground shadow-sm active:scale-95"
                                            @click="updateQty(item.id, 1)"
                                        >
                                            <Plus class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 border-t pt-4">
                            <div class="space-y-2">
                                <Label for="guestName" class="text-base"
                                    >Nama Pemesan</Label
                                >
                                <Input
                                    id="guestName"
                                    v-model="guestName"
                                    placeholder="Nama kamu..."
                                    class="font-garamond h-11 text-base is_required"
                                    />
                            </div>
                            <div class="space-y-2">
                                <Label for="orderNotes" class="text-base"
                                    >Catatan untuk Dapur (opsional)</Label
                                >
                                <textarea
                                    id="orderNotes"
                                    v-model="orderNotes"
                                    placeholder="Contoh: jangan terlalu pedas..."
                                    class="font-garamond flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                ></textarea>
                            </div>
                        </div>

                        <!-- Promo Section in Checkout -->
                        <div class="space-y-4 border-t pt-4">
                            <Label class="text-base font-bold">Promo & Voucher</Label>
                            
                            <!-- Input Code & Button -->
                            <div class="flex gap-2">
                                <Input
                                    v-model="promoCodeInput"
                                    placeholder="Masukkan kode promo..."
                                    class="font-garamond h-10 text-base"
                                    @keydown.enter.prevent="applyPromoCode"
                                />
                                <Button
                                    variant="outline"
                                    class="h-10 text-sm font-semibold px-4"
                                    @click="applyPromoCode"
                                >
                                    Terapkan
                                </Button>
                            </div>

                            <p v-if="promoError" class="text-xs text-destructive mt-1 font-medium">
                                {{ promoError }}
                            </p>

                            <!-- Selected Promo Display -->
                            <div v-if="selectedPromo" class="flex items-center justify-between rounded-xl border-2 border-emerald-500 bg-emerald-50 dark:bg-emerald-950/20 p-3 mt-2">
                                <div class="flex items-center gap-2.5">
                                    <Tag class="h-5 w-5 text-emerald-600 shrink-0" />
                                    <div>
                                        <p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">
                                            {{ selectedPromo.name }}
                                        </p>
                                        <p class="text-xs font-semibold text-emerald-600/80 font-mono">
                                            Kode: {{ selectedPromo.code }}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 text-xs font-semibold text-destructive hover:bg-destructive/10"
                                    @click="selectedPromo = null"
                                >
                                    Hapus
                                </Button>
                            </div>

                            <!-- Alert if requirements not met -->
                            <div
                                v-if="selectedPromo && promoDiscount === 0"
                                class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-600 dark:bg-amber-950/20"
                            >
                                <Info class="h-4 w-4 shrink-0" />
                                <span v-if="selectedPromo.condition_type === 'min_amount'">
                                    Belum memenuhi minimal order {{ formatRp(selectedPromo.condition_value) }} (Total: {{ formatRp(cartTotal) }}).
                                </span>
                                <span v-else-if="selectedPromo.condition_type === 'min_qty'">
                                    Belum memenuhi minimal {{ Number(selectedPromo.condition_value) }} item (Total: {{ cartCount }}).
                                </span>
                                <span v-else-if="selectedPromo.condition_type === 'specific_item_qty'">
                                    Belum memenuhi syarat pembelian item {{ selectedPromo.condition_menu_item?.name }}.
                                </span>
                            </div>

                            <!-- List of promo cards -->
                            <div v-if="promos && promos.length" class="space-y-2 mt-2">
                                <span class="text-xs font-medium text-muted-foreground">Pilih promo yang tersedia:</span>
                                <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-none snap-x">
                                    <div
                                        v-for="promo in promos"
                                        :key="promo.id"
                                        class="flex-none w-64 snap-center relative cursor-pointer overflow-hidden rounded-xl border-2 transition-all duration-300 bg-card"
                                        :class="
                                            selectedPromo?.id === promo.id
                                                ? 'border-primary shadow-sm bg-primary/5'
                                                : 'border-border hover:border-primary/50'
                                        "
                                        @click="
                                            selectedPromo = selectedPromo?.id === promo.id ? null : promo;
                                            promoError = '';
                                        "
                                    >
                                        <div class="p-3 space-y-2">
                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <h4 class="text-sm font-bold line-clamp-1 leading-tight">{{ promo.name }}</h4>
                                                    <span class="text-[10px] text-muted-foreground font-mono bg-secondary px-1 py-0.5 rounded mt-1 inline-block">
                                                        {{ promo.code }}
                                                    </span>
                                                </div>
                                                <div
                                                    v-if="selectedPromo?.id === promo.id"
                                                    class="rounded-full bg-primary p-0.5 text-primary-foreground shrink-0"
                                                >
                                                    <CheckCircle2 class="h-4 w-4" />
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap gap-1">
                                                <Badge class="bg-primary text-[9px] text-primary-foreground py-0 px-1.5 h-4">
                                                    {{
                                                        promo.reward_type === 'free_item'
                                                            ? 'Gratis Item'
                                                            : promo.reward_type === 'discount_percent'
                                                              ? `Diskon ${Number(promo.reward_value)}%`
                                                              : `Potongan ${formatRp(promo.reward_value)}`
                                                    }}
                                                </Badge>
                                                <Badge variant="secondary" class="text-[9px] py-0 px-1.5 h-4">
                                                    {{
                                                        promo.condition_type === 'min_amount'
                                                            ? `Min. ${formatRp(promo.condition_value)}`
                                                            : promo.condition_type === 'min_qty'
                                                              ? `Min. ${Number(promo.condition_value)} Item`
                                                              : `Beli ${Number(promo.condition_value)}x`
                                                    }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 mb-8 space-y-3 border-t pt-6">
                            <div
                                class="flex justify-between text-base text-muted-foreground md:text-lg"
                            >
                                <span>Subtotal</span>
                                <span class="font-semibold">{{
                                    formatRp(cartTotal)
                                }}</span>
                            </div>
                            <div
                                v-if="
                                    selectedPromo &&
                                    (promoDiscount > 0 ||
                                        (selectedPromo.reward_type ===
                                            'free_item' &&
                                            cartTotal > 0))
                                "
                                class="flex justify-between text-base text-emerald-600 md:text-lg"
                            >
                                <span class="flex items-center gap-1">
                                    <Tag class="h-4 w-4" />
                                    Promo ({{
                                        selectedPromo.code ??
                                        selectedPromo.name
                                    }})
                                </span>
                                <span
                                    class="font-semibold"
                                    v-if="
                                        selectedPromo.reward_type !==
                                        'free_item'
                                    "
                                    >-{{ formatRp(promoDiscount) }}</span
                                >
                                <span class="font-semibold" v-else
                                    >Gratis Item</span
                                >
                            </div>
                            <div
                                class="flex justify-between text-base text-muted-foreground md:text-lg"
                            >
                                <span>Pajak (10%)</span>
                                <span class="font-semibold">{{
                                    formatRp(taxAmount)
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between border-t pt-3 text-xl font-bold md:text-2xl"
                            >
                                <span>Total Tagihan</span>
                                <span class="text-primary">{{
                                    formatRp(finalTotal)
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-none border-t bg-background p-4 md:p-6">
                    <div class="mx-auto w-full max-w-3xl">
                        <Button
                            size="lg"
                            class="font-garamond h-14 w-full rounded-2xl text-xl font-bold tracking-wide md:h-16 md:rounded-3xl"
                            :disabled="isSubmitting"
                            @click="submitOrder"
                        >
                            {{
                                isSubmitting
                                    ? 'Mengirim...'
                                    : 'Kirim Pesanan'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Item Details Full Page Overlay -->
        <div
            v-if="isMounted && isItemModalOpen"
            class="fixed inset-0 z-50 flex justify-center bg-background/80 backdrop-blur-sm"
        >
            <div
                class="font-garamond flex h-full w-full max-w-md animate-in flex-col bg-background shadow-2xl duration-300 slide-in-from-right-8 md:max-w-4xl md:border-x lg:max-w-6xl xl:max-w-7xl"
            >
                <header
                    class="sticky top-0 z-20 flex flex-none items-center border-b bg-background/90 px-4 py-3 backdrop-blur-md md:px-6"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="font-garamond -ml-2 h-10 px-2 text-base text-muted-foreground"
                        @click="closeItemModal"
                    >
                        <ChevronLeft class="mr-1 h-6 w-6" /> Kembali
                    </Button>
                    <h1
                        class="mx-auto pr-16 text-lg font-bold md:pr-20 md:text-xl"
                    >
                        Detail Menu
                    </h1>
                </header>

                <div class="flex-1 overflow-y-auto">
                    <div
                        class="relative flex aspect-[4/3] w-full shrink-0 items-center justify-center bg-secondary/50 md:aspect-[21/9]"
                    >
                        <img
                            v-if="selectedItem?.image_url"
                            :src="selectedItem.image_url"
                            :alt="selectedItem?.name"
                            class="h-full w-full object-cover"
                        />
                        <UtensilsCrossed
                            v-else
                            class="h-16 w-16 text-muted-foreground/30"
                        />
                    </div>

                    <div
                        class="mx-auto max-w-3xl space-y-6 p-5 md:space-y-8 md:p-8"
                    >
                        <div>
                            <h2 class="mb-2 text-2xl font-bold md:text-3xl">
                                {{ selectedItem?.name }}
                            </h2>
                            <p
                                class="mb-4 text-base font-medium text-muted-foreground md:text-lg"
                            >
                                {{ selectedItem?.description }}
                            </p>

                            <!-- Detailed Information -->
                            <div class="mb-6 flex flex-wrap gap-2">
                                <Badge
                                    variant="secondary"
                                    class="font-garamond px-2.5 py-1 text-sm"
                                    v-if="selectedItem?.category"
                                >
                                    <List class="mr-1 h-3.5 w-3.5" />
                                    {{ selectedItem.category.name }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="font-garamond border-orange-200 bg-orange-50 px-2.5 py-1 text-sm text-orange-600 dark:bg-orange-950/30"
                                    v-if="selectedItem?.calories"
                                >
                                    <Flame class="mr-1 h-3.5 w-3.5" />
                                    {{ selectedItem.calories }} kkal
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="font-garamond border-red-200 bg-red-50 px-2.5 py-1 text-sm text-red-600 dark:bg-red-950/30"
                                    v-if="selectedItem?.allergens?.length"
                                >
                                    <ShieldAlert class="mr-1 h-3.5 w-3.5" />
                                    Alergen:
                                    {{ selectedItem.allergens.join(', ') }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="font-garamond border-blue-200 bg-blue-50 px-2.5 py-1 text-sm text-blue-600 dark:bg-blue-950/30"
                                    v-if="selectedItem?.tags?.length"
                                >
                                    <Tag class="mr-1 h-3.5 w-3.5" />
                                    {{ selectedItem.tags.join(', ') }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Package Items -->
                        <div
                            v-if="
                                selectedItem?.isPackage &&
                                selectedItem?.package_items?.length
                            "
                            class="space-y-3 md:space-y-4"
                        >
                            <Label class="text-lg font-bold md:text-xl"
                                >Isi Paket</Label
                            >
                            <div
                                class="divide-y overflow-hidden rounded-2xl border bg-muted/20"
                            >
                                <div
                                    v-for="item in selectedItem.package_items"
                                    :key="item.id"
                                    class="font-garamond flex items-center justify-between px-5 py-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted"
                                        >
                                            <UtensilsCrossed
                                                v-if="item.menu_item"
                                                class="h-5 w-5 text-muted-foreground/60"
                                            />
                                            <ShoppingBag
                                                v-else
                                                class="h-5 w-5 text-muted-foreground/60"
                                            />
                                        </div>
                                        <div class="flex flex-col">
                                            <span
                                                class="text-base font-bold md:text-lg"
                                            >
                                                {{
                                                    item.menu_item?.name ||
                                                    item.inventory_item?.name
                                                }}
                                            </span>
                                            <span
                                                v-if="item.notes"
                                                class="text-xs text-muted-foreground italic md:text-sm"
                                            >
                                                * {{ item.notes }}
                                            </span>
                                        </div>
                                    </div>
                                    <span
                                        class="rounded-full bg-primary/10 px-3 py-1 text-base font-bold text-primary md:text-lg"
                                    >
                                        {{ item.qty }}x
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Options -->
                        <div
                            v-for="option in selectedItem?.options"
                            :key="option.id"
                            class="space-y-3 md:space-y-4"
                        >
                            <div class="flex items-center gap-2">
                                <Label class="text-lg font-bold md:text-xl">{{
                                    option.name
                                }}</Label>
                                <Badge
                                    v-if="option.is_required"
                                    variant="destructive"
                                    class="px-2 py-0.5 text-[11px] uppercase"
                                    >Wajib</Badge
                                >
                            </div>
                            <div class="flex flex-col gap-2 md:gap-3">
                                <button
                                    v-for="val in option.values"
                                    :key="val.id"
                                    class="font-garamond flex items-center justify-between rounded-xl border-2 px-5 py-3 text-left text-base font-semibold transition-colors"
                                    :class="
                                        isOptionSelected(option, val.id)
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background hover:bg-secondary'
                                    "
                                    @click="toggleOption(option, val.id)"
                                >
                                    <span>{{ val.name }}</span>
                                    <span
                                        v-if="val.additional_price > 0"
                                        class="opacity-70"
                                        >+{{
                                            formatRp(val.additional_price)
                                        }}</span
                                    >
                                </button>
                            </div>
                        </div>

                        <!-- Add-ons -->
                        <div
                            v-if="selectedItem?.add_ons?.length"
                            class="space-y-3 md:space-y-4"
                        >
                            <Label class="text-lg font-bold md:text-xl"
                                >Tambahan</Label
                            >
                            <div class="flex flex-col gap-2 md:gap-3">
                                <button
                                    v-for="addOn in selectedItem.add_ons"
                                    :key="addOn.id"
                                    class="font-garamond flex items-center justify-between rounded-xl border-2 px-5 py-3 text-left text-base font-semibold transition-colors"
                                    :class="
                                        selectedAddOns.includes(addOn.id)
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background hover:bg-secondary'
                                    "
                                    @click="
                                        selectedAddOns.includes(addOn.id)
                                            ? selectedAddOns.splice(
                                                  selectedAddOns.indexOf(
                                                      addOn.id,
                                                  ),
                                                  1,
                                              )
                                            : selectedAddOns.push(addOn.id)
                                    "
                                >
                                    <span>{{ addOn.name }}</span>
                                    <span
                                        v-if="addOn.price > 0"
                                        class="opacity-70"
                                        >+{{ formatRp(addOn.price) }}</span
                                    >
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label
                                for="itemNotes"
                                class="text-lg font-bold md:text-xl"
                                >Catatan Khusus</Label
                            >
                            <small class="text-sm text-red-500">{{
                                errorSpeech
                            }}</small>
                            <div class="relative flex items-center">
                                <Input
                                    id="itemNotes"
                                    v-model="itemNotes"
                                    placeholder="Contoh: tanpa bawang..."
                                    class="font-garamond h-12 text-base md:h-14 md:text-lg"
                                />
                                <button
                                    type="button"
                                    @click="startSpeechToText(itemNotes)"
                                    class="absolute right-3 rounded-full p-2 transition-colors hover:bg-secondary"
                                    :class="
                                        isListening
                                            ? 'animate-pulse text-red-500'
                                            : 'text-muted-foreground'
                                    "
                                    title="Input dengan Suara"
                                >
                                    <Mic class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-none border-t bg-background p-4 md:p-6">
                    <div
                        class="mx-auto flex w-full max-w-3xl flex-row items-center gap-4"
                    >
                        <div
                            class="flex flex-1 items-center justify-between gap-4 rounded-2xl border bg-secondary p-2"
                        >
                            <button
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-background shadow-sm active:scale-95 md:h-12 md:w-12"
                                @click="itemQty = Math.max(1, itemQty - 1)"
                            >
                                <Minus class="h-5 w-5" />
                            </button>
                            <span
                                class="w-12 text-center text-xl font-bold md:text-2xl"
                                >{{ itemQty }}</span
                            >
                            <button
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-background shadow-sm active:scale-95 md:h-12 md:w-12"
                                @click="itemQty++"
                            >
                                <Plus class="h-5 w-5" />
                            </button>
                        </div>
                        <Button
                            size="lg"
                            class="font-garamond h-14 w-[180px] flex-none rounded-2xl text-lg font-bold tracking-wide md:h-16 md:w-[240px] md:rounded-3xl md:text-2xl"
                            @click="addToCart"
                        >
                            Tambah
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auth Modal -->
        <Dialog v-model:open="isAuthModalOpen" v-if="isMounted">
            <DialogContent
                class="font-garamond w-[95vw] max-w-sm rounded-2xl p-6 sm:max-w-[425px]"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        authMode === 'login'
                            ? 'Masuk ke Akun'
                            : 'Daftar Akun Baru'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            authMode === 'login'
                                ? 'Masuk untuk melihat riwayat pesananmu.'
                                : 'Buat akun agar pemesanan lebih mudah.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="mb-4 flex rounded-lg bg-secondary p-1">
                    <button
                        class="flex-1 rounded-md py-1.5 text-sm font-medium transition-colors"
                        :class="
                            authMode === 'login'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="
                            authMode = 'login';
                            authError = '';
                        "
                    >
                        Masuk
                    </button>
                    <button
                        class="flex-1 rounded-md py-1.5 text-sm font-medium transition-colors"
                        :class="
                            authMode === 'register'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="
                            authMode = 'register';
                            authError = '';
                        "
                    >
                        Daftar
                    </button>
                </div>

                <div
                    v-if="authError"
                    class="mb-4 rounded-md border border-destructive/20 bg-destructive/15 p-3 text-sm text-destructive"
                >
                    {{ authError }}
                </div>

                <div class="space-y-4">
                    <template v-if="authMode === 'login'">
                        <div class="space-y-2">
                            <Label for="authLogin">Email / No. HP</Label>
                            <Input
                                id="authLogin"
                                v-model="authLogin"
                                placeholder="Masukkan email atau no. hp"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="authPassword">Password</Label>
                            <Input
                                id="authPassword"
                                v-model="authPassword"
                                type="password"
                                placeholder="••••••"
                            />
                        </div>
                    </template>
                    <template v-else>
                        <div class="space-y-2">
                            <Label for="authName">Nama Lengkap</Label>
                            <Input
                                id="authName"
                                v-model="authName"
                                placeholder="John Doe"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="authPhone"
                                >No. HP
                                <span class="font-normal text-muted-foreground"
                                    >(opsional)</span
                                ></Label
                            >
                            <Input
                                id="authPhone"
                                v-model="authPhone"
                                type="tel"
                                placeholder="08..."
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="authEmail"
                                >Email
                                <span class="font-normal text-muted-foreground"
                                    >(opsional)</span
                                ></Label
                            >
                            <Input
                                id="authEmail"
                                v-model="authEmail"
                                type="email"
                                placeholder="john@example.com"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="authPassword">Password</Label>
                            <Input
                                id="authPassword"
                                v-model="authPassword"
                                type="password"
                                placeholder="Minimal 6 karakter"
                            />
                        </div>
                    </template>
                </div>

                <DialogFooter class="mt-6 sm:justify-start">
                    <Button
                        class="w-full"
                        :disabled="authLoading"
                        @click="submitAuth"
                    >
                        {{
                            authLoading
                                ? 'Loading...'
                                : authMode === 'login'
                                  ? 'Masuk'
                                  : 'Buat Akun'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Promo Detail Modal -->
        <Dialog v-model:open="isPromoDetailOpen" v-if="isMounted">
            <DialogContent
                class="font-garamond w-[95vw] max-w-md rounded-2xl p-6 sm:max-w-[450px]"
            >
                <DialogHeader v-if="activeDetailPromo">
                    <DialogTitle class="text-xl font-bold">{{ activeDetailPromo.name }}</DialogTitle>
                    <DialogDescription class="text-sm">
                        Detail & Syarat Ketentuan Promo
                    </DialogDescription>
                </DialogHeader>

                <div v-if="activeDetailPromo" class="space-y-4 py-2">
                    <div v-if="activeDetailPromo.image" class="overflow-hidden rounded-xl bg-secondary">
                        <img
                            :src="getPromoImage(activeDetailPromo.image)"
                            class="aspect-[21/9] w-full object-cover"
                            :alt="activeDetailPromo.name"
                        />
                    </div>

                    <div class="space-y-3">
                        <div>
                            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Kode Promo</span>
                            <div class="mt-1 flex items-center justify-between rounded-lg border bg-secondary px-3 py-2">
                                <span class="font-mono text-base font-bold text-primary">{{ activeDetailPromo.code }}</span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 text-xs font-medium"
                                    @click="copyPromoCode(activeDetailPromo.code)"
                                >
                                    {{ promoCopied ? 'Tersalin!' : 'Salin Kode' }}
                                </Button>
                            </div>
                        </div>

                        <div>
                            <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Deskripsi</span>
                            <p class="mt-1 text-sm text-foreground leading-relaxed whitespace-pre-line">
                                {{ activeDetailPromo.description || 'Tidak ada deskripsi untuk promo ini.' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <div class="rounded-xl border p-3">
                                <span class="text-xs font-medium text-muted-foreground">Keuntungan</span>
                                <p class="mt-1 text-sm font-bold text-emerald-600">
                                    {{
                                        activeDetailPromo.reward_type === 'free_item'
                                            ? 'Gratis Item'
                                            : activeDetailPromo.reward_type === 'discount_percent'
                                              ? `Diskon ${Number(activeDetailPromo.reward_value)}%`
                                              : `Potongan ${formatRp(activeDetailPromo.reward_value)}`
                                    }}
                                </p>
                            </div>
                            <div class="rounded-xl border p-3">
                                <span class="text-xs font-medium text-muted-foreground">Syarat & Ketentuan</span>
                                <p class="mt-1 text-xs text-foreground font-medium">
                                    {{
                                        activeDetailPromo.condition_type === 'min_amount'
                                            ? `Min. belanja ${formatRp(activeDetailPromo.condition_value)}`
                                            : activeDetailPromo.condition_type === 'min_qty'
                                              ? `Min. pembelian ${Number(activeDetailPromo.condition_value)} item`
                                              : activeDetailPromo.condition_type === 'specific_item_qty'
                                                ? `Beli ${Number(activeDetailPromo.condition_value)}x ${activeDetailPromo.condition_menu_item?.name || 'item khusus'}`
                                                : 'Tidak ada syarat khusus'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="sm:justify-end">
                    <Button variant="outline" @click="isPromoDetailOpen = false">Tutup</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Guest History Full Page Overlay -->
        <div
            v-if="isMounted && isGuestHistoryOpen"
            class="fixed inset-0 z-50 flex justify-center bg-background/80 backdrop-blur-sm"
        >
            <div
                class="font-garamond flex h-full w-full max-w-md animate-in flex-col bg-background shadow-2xl duration-300 slide-in-from-right-8 md:max-w-4xl md:border-x lg:max-w-6xl xl:max-w-7xl"
            >
                <header
                    class="sticky top-0 z-20 flex flex-none items-center border-b bg-background/90 px-4 py-3 backdrop-blur-md md:px-6"
                >
                    <Button
                        variant="ghost"
                        size="sm"
                        class="font-garamond -ml-2 h-10 px-2 text-base text-muted-foreground"
                        @click="isGuestHistoryOpen = false"
                    >
                        <ChevronLeft class="mr-1 h-6 w-6" /> Kembali
                    </Button>
                    <h1
                        class="mx-auto pr-16 text-lg font-bold md:pr-20 md:text-xl"
                    >
                        Pesanan Saya
                    </h1>
                </header>

                <div class="flex-1 overflow-y-auto p-4 pb-12 md:p-6">
                    <div
                        v-if="isLoadingHistory"
                        class="flex justify-center py-12"
                    >
                        <span class="animate-pulse text-lg font-bold"
                            >Memuat...</span
                        >
                    </div>
                    <div
                        v-else-if="guestOrders.length === 0"
                        class="py-16 text-center text-muted-foreground md:py-24"
                    >
                        <UtensilsCrossed
                            class="mx-auto mb-4 h-12 w-12 opacity-20 md:h-16 md:w-16"
                        />
                        <p class="text-lg md:text-xl">Belum ada pesanan</p>
                    </div>
                    <div
                        v-else
                        class="mx-auto grid max-w-7xl grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <Card
                            v-for="order in guestOrders"
                            :key="order.id"
                            class="overflow-hidden shadow-sm"
                        >
                            <CardContent class="p-4 md:p-5">
                                <div
                                    class="mb-2 flex items-start justify-between md:mb-3"
                                >
                                    <div>
                                        <div
                                            class="text-base font-bold md:text-lg"
                                        >
                                            {{ order.order_number }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground md:text-sm"
                                        >
                                            {{
                                                new Date(
                                                    order.created_at,
                                                ).toLocaleString('id-ID', {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    year: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-md px-2 py-1 text-xs font-semibold md:px-3 md:py-1.5 md:text-sm"
                                        :class="
                                            statusMap[order.status]?.color ||
                                            'bg-secondary text-secondary-foreground'
                                        "
                                    >
                                        {{
                                            statusMap[order.status]?.label ??
                                            order.status
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="mb-4 flex flex-col gap-1 text-sm text-muted-foreground md:mb-5 md:gap-1.5 md:text-base"
                                >
                                    <span
                                        v-for="item in order.items"
                                        :key="item.id"
                                    >
                                        {{ item.qty }}× {{ item.name }}
                                    </span>
                                </div>

                                <!-- Stepper Progress for active order -->
                                <div
                                    v-if="getStepIndex(order.status) !== -1"
                                    class="mb-4 space-y-2 border-t pt-3 md:mb-5"
                                >
                                    <div
                                        class="relative flex items-center justify-between px-1"
                                    >
                                        <!-- Connecting Line -->
                                        <div
                                            class="absolute top-3 right-[12.5%] left-[12.5%] -z-10 h-0.5 bg-muted"
                                        >
                                            <div
                                                class="h-full bg-primary transition-all duration-500"
                                                :style="{
                                                    width:
                                                        (getStepIndex(
                                                            order.status,
                                                        ) /
                                                            3) *
                                                            100 +
                                                        '%',
                                                }"
                                            ></div>
                                        </div>

                                        <!-- Steps -->
                                        <div
                                            v-for="(step, idx) in [
                                                'pending',
                                                'preparing',
                                                'ready',
                                                'served',
                                            ]"
                                            :key="step"
                                            class="relative flex flex-1 flex-col items-center"
                                        >
                                            <div
                                                class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold transition-all duration-300"
                                                :class="
                                                    getStepIndex(
                                                        order.status,
                                                    ) >= idx
                                                        ? 'scale-110 bg-primary text-primary-foreground shadow-sm'
                                                        : 'bg-muted text-muted-foreground'
                                                "
                                            >
                                                <span class="relative z-10">{{
                                                    idx + 1
                                                }}</span>
                                            </div>
                                            <span
                                                class="mt-1 text-center text-[9px] font-semibold transition-colors md:text-[10px]"
                                                :class="
                                                    getStepIndex(
                                                        order.status,
                                                    ) >= idx
                                                        ? 'font-bold text-primary'
                                                        : 'text-muted-foreground'
                                                "
                                            >
                                                {{
                                                    step === 'pending'
                                                        ? 'Diterima'
                                                        : step === 'preparing'
                                                          ? 'Dimasak'
                                                          : step === 'ready'
                                                            ? 'Siap'
                                                            : 'Disajikan'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center justify-between border-t pt-3 md:pt-4"
                                >
                                    <span
                                        class="text-base font-bold md:text-lg"
                                        >{{
                                            formatRp(order.final_amount)
                                        }}</span
                                    >
                                    <div
                                        class="rounded-md px-2 py-1 text-xs font-medium md:px-3 md:py-1.5 md:text-sm"
                                        :class="
                                            order.payment_status === 'paid'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : 'bg-destructive/15 text-destructive'
                                        "
                                    >
                                        {{
                                            order.payment_status === 'paid'
                                                ? 'Lunas'
                                                : 'Belum bayar'
                                        }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
