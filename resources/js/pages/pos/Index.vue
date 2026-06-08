<script setup lang="ts">
import {
	ArrowLeft,
	Banknote,
	Barcode,
	Check,
	ChefHat,
	ChevronRight,
	CreditCard,
	Flame,
	Info,
	Landmark,
	LayoutGrid,
	List,
	Loader2,
	Minus,
	Plus,
	Receipt,
	Save,
	Search,
	ShieldAlert,
	ShoppingBag,
	ShoppingCart,
	Table as TableIcon,
	Tag,
	User as UserIcon,
	Utensils,
	X,
	Smartphone,
	QrCode,
} from "lucide-vue-next";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
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
import { Separator } from "@/components/ui/separator";
import ModalOpenBills from "./ModalOpenBills.vue";
import ModalPaymentQris from "./ModalPaymentQris.vue";

defineOptions({
	layout: {
		breadcrumbs: [
			{ title: "Dashboard", href: "/dashboard" },
			{ title: "Kasir (POS)", href: "#" },
		],
	},
});

// ─── Midtrans QRIS State ──────────────────────────────────────────────────────
const qrModalOpen = ref(false);
const qrUrl = ref("");
const vaNumber = ref("");
const vaBank = ref("");
const requestedBank = ref("");
const activeOrderNumber = ref("");
const activeOrderFinalAmount = ref(0);

// Mobile Scanner State
const isPairingModalOpen = ref(false);
const scannerSessionId = ref(Math.random().toString(36).substring(2, 10));
const isScannerPollingActive = ref(false);
let scannerPollingInterval: any = null;

const pairingUrl = computed(() => {
	if (!scannerSessionId.value) return "";
	return `${window.location.origin}/scanner?session=${scannerSessionId.value}`;
});

const startScannerPolling = () => {
	isScannerPollingActive.value = true;
	if (scannerPollingInterval) {
		clearInterval(scannerPollingInterval);
	}

	scannerPollingInterval = setInterval(async () => {
		try {
			const res = await fetch(`/api/scanner/poll/${scannerSessionId.value}`);
			if (res.ok) {
				const data = await res.json();
				if (data.success && data.sku) {
					scanQuery.value = data.sku;
					await handleBarcodeScan();
				}
			}
		} catch (e) {
			console.error("Error polling scanner session:", e);
		}
	}, 1500);
};

const stopScannerPolling = () => {
	isScannerPollingActive.value = false;
	if (scannerPollingInterval) {
		clearInterval(scannerPollingInterval);
		scannerPollingInterval = null;
	}
};

const toggleScannerPolling = () => {
	if (isScannerPollingActive.value) {
		stopScannerPolling();
		setScanStatus("Scanner HP dinonaktifkan", "error");
	} else {
		startScannerPolling();
		setScanStatus("Scanner HP aktif & siap menerima data", "success");
	}
	focusBarcodeInput();
};

const openPairingModal = () => {
	isPairingModalOpen.value = true;
};

const closePairingModal = () => {
	isPairingModalOpen.value = false;
	focusBarcodeInput();
};

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
						resetPOS();
						paymentSuccess.value = false;
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
	stopScannerPolling();
	document.removeEventListener("click", handleGlobalClick);
});

// ─── Data Fetching ───────────────────────────────────────────────────────────
const menuItems = ref<any[]>([]);
const categories = ref<any[]>([]);
const customers = ref<any[]>([]);
const tables = ref<any[]>([]);
const packages = ref<any[]>([]);
const openBills = ref<any[]>([]);

const loading = ref(true);
const viewMode = ref<"grid" | "list">("grid");
const activeCategory = ref<number | "all" | "packages">("all");
const searchQuery = ref("");

const fetchData = async () => {
	loading.value = true;

	try {
		const [menuRes, catRes, custRes, tableRes, pkgRes] = await Promise.all([
			fetch("/api/menu-items?per_page=100", {
				headers: { Accept: "application/json" },
			}),
			fetch("/api/categories?all=true", {
				headers: { Accept: "application/json" },
			}),
			fetch("/api/users?role=customer&all=true", {
				headers: { Accept: "application/json" },
			}),
			fetch("/api/tables?all=true", {
				headers: { Accept: "application/json" },
			}),
			fetch("/api/packages?all=true", {
				headers: { Accept: "application/json" },
			}),
		]);

		if (menuRes.ok) {
			menuItems.value = (await menuRes.json()).data;
		}

		if (catRes.ok) {
			categories.value = await catRes.json();
		}

		if (custRes.ok) {
			customers.value = await custRes.json();
		}

		if (tableRes.ok) {
			tables.value = await tableRes.json();
		}

		if (pkgRes.ok) {
			packages.value = await pkgRes.json();
		}

		await fetchOpenBills();
	} catch (e) {
		console.error(e);
	} finally {
		loading.value = false;
	}
};

const fetchOpenBills = async () => {
	try {
		const res = await fetch("/api/orders?payment_status=unpaid&per_page=100", {
			headers: { Accept: "application/json" },
		});

		if (res.ok) {
			const data = await res.json();
			openBills.value = data.data;
		}
	} catch (e) {
		console.error(e);
	}
};

onMounted(() => {
	fetchData();
	focusBarcodeInput();
	document.addEventListener("click", handleGlobalClick);
});

// ─── Filtering Logic ─────────────────────────────────────────────────────────
const filteredItems = computed(() => {
	let items =
		activeCategory.value === "packages" ? packages.value : menuItems.value;

	if (activeCategory.value !== "all" && activeCategory.value !== "packages") {
		items = items.filter((i) => i.category_id === activeCategory.value);
	}

	if (searchQuery.value) {
		const q = searchQuery.value.toLowerCase();
		items = items.filter((i) => i.name.toLowerCase().includes(q));
	}

	return items;
});

// ─── Cart Logic ──────────────────────────────────────────────────────────────
const cart = ref<any[]>([]);
const cartStep = ref<1 | 2 | 3 | 4>(1);
const selectedCustomer = ref<any | null>(null);
const orderType = ref<"dine_in" | "take_away">("dine_in");
const selectedTable = ref<number | null>(null);
const paymentMethod = ref<"cash" | "qris" | "transfer">("cash");
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
const amountPaid = ref<number>(0);
const activeOrderId = ref<number | null>(null);

const subtotal = computed(() =>
	cart.value.reduce((acc, item) => acc + item.price * item.qty, 0),
);
const tax = computed(() => Math.round(subtotal.value * 0.1)); // 10% tax
const discount = ref(0);
const total = computed(() => subtotal.value + tax.value - discount.value);
const change = computed(() => Math.max(0, amountPaid.value - total.value));

const customizingQty = ref(1);
const customizingNotes = ref("");

const addToCart = (item: any, isPackage = false) => {
	customizingItem.value = JSON.parse(JSON.stringify(item));
	customizingItem.value.isPackage = isPackage;
	selectedOptions.value = {};
	selectedAddOns.value = [];
	customizingQty.value = 1;
	customizingNotes.value = "";
	showCustomization.value = true;
	showDetail.value = false;
};

const customizingItemTotalPrice = computed(() => {
	if (!customizingItem.value) {
		return 0;
	}

	let price = Number(customizingItem.value.price);

	// Add variants
	Object.values(selectedOptions.value).forEach((val) => {
		if (Array.isArray(val)) {
			val.forEach((v) => {
				price += Number(v.additional_price || 0);
			});
		} else {
			price += Number(val.additional_price || 0);
		}
	});

	// Add add-ons
	selectedAddOns.value.forEach((id) => {
		const ao = customizingItem.value.add_ons?.find((a: any) => a.id === id);

		if (ao) {
			price += Number(ao.price);
		}
	});

	return price * customizingQty.value;
});

const removeFromCart = (index: number) => {
	cart.value.splice(index, 1);
};

// ─── Customization Logic ─────────────────────────────────────────────────────
const showCustomization = ref(false);
const showDetail = ref(false);
const showBills = ref(false);
const detailItem = ref<any | null>(null);
const customizingItem = ref<any | null>(null);
const selectedOptions = ref<Record<number, any>>({});
const selectedAddOns = ref<number[]>([]);

const toggleOption = (opt: any, val: any) => {
	if (opt.type === "single") {
		selectedOptions.value[opt.id] = val;
	} else {
		if (
			!selectedOptions.value[opt.id] ||
			!Array.isArray(selectedOptions.value[opt.id])
		) {
			selectedOptions.value[opt.id] = [];
		}

		const index = (selectedOptions.value[opt.id] as any[]).findIndex(
			(v: any) => v.id === val.id,
		);

		if (index > -1) {
			(selectedOptions.value[opt.id] as any[]).splice(index, 1);
		} else {
			(selectedOptions.value[opt.id] as any[]).push(val);
		}
	}
};

const isOptionSelected = (opt: any, valId: number) => {
	const selected = selectedOptions.value[opt.id];

	if (!selected) {
		return false;
	}

	if (opt.type === "single") {
		return selected.id === valId;
	}

	return (selected as any[]).some((v: any) => v.id === valId);
};

const toggleAddOn = (id: number) => {
	const idx = selectedAddOns.value.indexOf(id);

	if (idx > -1) {
		selectedAddOns.value.splice(idx, 1);
	} else {
		selectedAddOns.value.push(id);
	}
};

const openDetail = (item: any, isPackage = false) => {
	detailItem.value = { ...item, isPackage };
	showDetail.value = true;
	showCustomization.value = false;
};

const addCustomizedToCart = () => {
	if (!customizingItem.value) {
		return;
	}

	const missingRequired = customizingItem.value.options?.filter((opt: any) => {
		const selected = selectedOptions.value[opt.id];

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

	let additionalPrice = 0;
	const variants: any[] = [];

	Object.entries(selectedOptions.value).forEach(([optId, val]) => {
		const opt = customizingItem.value.options?.find(
			(o: any) => o.id === Number(optId),
		);

		if (Array.isArray(val)) {
			val.forEach((v) => {
				additionalPrice += Number(v.additional_price || 0);
				variants.push({ ...v, option_name: opt?.name || "" });
			});
		} else {
			additionalPrice += Number(val.additional_price || 0);
			variants.push({ ...val, option_name: opt?.name || "" });
		}
	});

	const addOns: any[] = [];
	selectedAddOns.value.forEach((id) => {
		const ao = customizingItem.value.add_ons?.find((a: any) => a.id === id);

		if (ao) {
			additionalPrice += Number(ao.price);
			addOns.push(ao);
		}
	});

	const variantKey = variants
		.map((v) => v.id)
		.sort()
		.join(",");
	const addOnKey = addOns
		.map((a) => a.id)
		.sort()
		.join(",");
	const noteKey = customizingNotes.value.trim();
	const customizationHash = `${customizingItem.value.id}-${variantKey}-${addOnKey}-${noteKey}`;

	const existing = cart.value.find(
		(i) => i.customizationHash === customizationHash,
	);

	if (existing) {
		existing.qty += customizingQty.value;
	} else {
		cart.value.push({
			...customizingItem.value,
			price: Number(customizingItem.value.price) + additionalPrice,
			originalPrice: Number(customizingItem.value.price),
			qty: customizingQty.value,
			notes: customizingNotes.value,
			customized: true,
			customizationHash,
			variants,
			add_ons: addOns,
			cartId: Date.now() + Math.random(),
		});
	}

	showCustomization.value = false;
	customizingItem.value = null;
};

const scanQuery = ref("");
const barcodeInputRef = ref<any>(null);

const focusBarcodeInput = () => {
	setTimeout(() => {
		const input = document.querySelector('input[placeholder="Scan Barcode / SKU..."]') as HTMLInputElement | null;
		if (input) {
			input.focus();
		}
	}, 50);
};

// Watch changes to layout views (e.g. customization closing) and restore focus
watch([showCustomization, showDetail], ([newShowCust, newShowDet]) => {
	if (!newShowCust && !newShowDet) {
		focusBarcodeInput();
	}
});

// Watch category switches to restore focus
watch(activeCategory, () => {
	focusBarcodeInput();
});

const handleGlobalClick = (event: MouseEvent) => {
	const target = event.target as HTMLElement;
	if (!target) return;

	// Check if the clicked target or any of its parents is an input field
	const isInput = target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.tagName === "SELECT";

	// Also check for combobox, dialogs, dropdown options, and buttons
	const isModalOrDropdown = target.closest('[role="dialog"]') ||
	                          target.closest('[role="listbox"]') ||
	                          target.closest('[role="combobox"]') ||
	                          target.closest('.modal-content') ||
	                          target.closest('button') ||
	                          target.tagName === "BUTTON";

	// If they didn't click an input, button, select, or modal element, redirect focus to barcode input
	if (!isInput && !isModalOrDropdown) {
		focusBarcodeInput();
	}
};

const isScanning = ref(false);
const scanStatus = ref<{ message: string; type: "success" | "error" } | null>(
	null,
);
let scanStatusTimeout: any = null;

const playBeep = (type: "success" | "error" = "success") => {
	try {
		const ctx = new (
			window.AudioContext || (window as any).webkitAudioContext
		)();
		const osc = ctx.createOscillator();
		const gain = ctx.createGain();
		osc.connect(gain);
		gain.connect(ctx.destination);

		if (type === "success") {
			osc.frequency.setValueAtTime(880, ctx.currentTime); // high pitch beep
			gain.gain.setValueAtTime(0.1, ctx.currentTime);
			osc.start();
			osc.stop(ctx.currentTime + 0.12);
		} else {
			osc.frequency.setValueAtTime(220, ctx.currentTime); // low pitch buzzer
			gain.gain.setValueAtTime(0.15, ctx.currentTime);
			osc.start();
			osc.stop(ctx.currentTime + 0.3);
		}
	} catch (e) {
		console.error("Audio beep failed", e);
	}
};

const setScanStatus = (message: string, type: "success" | "error") => {
	scanStatus.value = { message, type };

	if (scanStatusTimeout) {
		clearTimeout(scanStatusTimeout);
	}

	scanStatusTimeout = setTimeout(() => {
		scanStatus.value = null;
	}, 4000);
};

const handleBarcodeScan = async () => {
	const sku = scanQuery.value.trim();

	if (!sku) {
		return;
	}

	isScanning.value = true;

	try {
		const res = await fetch(
			`/api/inventory-items?search=${encodeURIComponent(sku)}`,
			{
				headers: { Accept: "application/json" },
			},
		);

		if (res.ok) {
			const data = await res.json();
			const items = data.data || [];

			const invItem = items.find(
				(i: any) => i.sku && i.sku.toLowerCase() === sku.toLowerCase(),
			);

			if (invItem) {
				let menuItem = menuItems.value.find(
					(m: any) => m.inventory_item_id === invItem.id,
				);

				if (!menuItem) {
					menuItem = packages.value.find(
						(p: any) => p.inventory_item_id === invItem.id,
					);
				}

				if (menuItem) {
					const isPkg = Boolean(menuItem.package_items);
					const customizationHash = `${menuItem.id}---`;
					const existing = cart.value.find(
						(i) => i.customizationHash === customizationHash,
					);

					if (existing) {
						existing.qty += 1;
					} else {
						cart.value.push({
							...menuItem,
							isPackage: isPkg,
							price: Number(menuItem.price),
							originalPrice: Number(menuItem.price),
							qty: 1,
							notes: "",
							customized: false,
							customizationHash,
							variants: [],
							add_ons: [],
							cartId: Date.now() + Math.random(),
						});
					}

					scanQuery.value = "";
					const stockText = `${invItem.qty ?? 0} ${invItem.unit || 'pcs'}`;
					setScanStatus(`Berhasil: ${menuItem.name} (SKU: ${invItem.sku || '—'}) | Stok: ${stockText}`, "success");
					playBeep("success");
				} else {
					scanQuery.value = "";
					setScanStatus(
						`Barang "${invItem.name}" belum terhubung ke Menu`,
						"error",
					);
					playBeep("error");
				}
			} else {
				scanQuery.value = "";
				setScanStatus(`Barcode/SKU tidak terdaftar: ${sku}`, "error");
				playBeep("error");
			}
		} else {
			scanQuery.value = "";
			setScanStatus("Gagal membaca data dari server", "error");
			playBeep("error");
		}
	} catch (e) {
		console.error("Error scanning barcode", e);
		scanQuery.value = "";
		setScanStatus("Terjadi kesalahan koneksi scanner", "error");
		playBeep("error");
	} finally {
		isScanning.value = false;
		focusBarcodeInput();
	}
};

// ─── Bill Management ─────────────────────────────────────────────────────────
const selectBill = (order: any) => {
	activeOrderId.value = order.id;
	cart.value = order.items.map((item: any) => ({
		...item,
		price: Number(item.price),
		qty: item.qty,
		customized: true,
		isExisting: true, // Mark as already in DB
		status: item.status,
	}));
	selectedTable.value = order.table_id;
	selectedCustomer.value = order.customer;
	orderType.value = order.order_type;
	discount.value = Number(order.discount_amount);
	showBills.value = false;
};

const payBillDirectly = async (order: any) => {
	selectBill(order);

	// If it's Cash or Transfer, go to step 3 to input amount/choose bank
	if (order.payment_method === "cash" || order.payment_method === "transfer") {
		paymentMethod.value = order.payment_method;
		cartStep.value = 3;

		return;
	}

	// If it is QRIS or Bank Transfer, call payment API to show popup directly
	isSubmitting.value = true;

	try {
		paymentMethod.value = order.payment_method;
		const res = await fetch(`/api/orders/${order.id}/pay`, {
			method: "PATCH",
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
			body: JSON.stringify({
				payment_method: order.payment_method,
				discount: discount.value,
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
					order.order_number ||
					"";
				activeOrderFinalAmount.value =
					data.order?.final_amount ||
					data.final_amount ||
					order.final_amount ||
					0;
				qrModalOpen.value = true;
				startPolling(order.id);
			} else {
				alert("Pembayaran Berhasil!");
				resetPOS();
			}
		} else {
			const errData = await res.json();
			alert(errData.message || "Gagal memproses pembayaran.");
		}
	} catch (e) {
		console.error(e);
		alert("Terjadi kesalahan koneksi");
	} finally {
		isSubmitting.value = false;
	}
};

const cancelExistingItem = async (item: any, idx: number) => {
	if (!confirm("Batalkan item ini?")) {
		return;
	}

	try {
		const res = await fetch(`/api/order-items/${item.id}/cancel`, {
			method: "PATCH",
			headers: {
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
		});

		if (res.ok) {
			const data = await res.json();
			// Update cart items with new totals if needed
			item.status = "cancelled";
			alert("Item berhasil dibatalkan");
		}
	} catch (e) {
		console.error(e);
	}
};

// ─── Checkout Logic ──────────────────────────────────────────────────────────
const isSubmitting = ref(false);

const resetPOS = () => {
	cart.value = [];
	amountPaid.value = 0;
	selectedTable.value = null;
	selectedCustomer.value = null;
	activeOrderId.value = null;
	cartStep.value = 1;
	qrUrl.value = "";
	vaNumber.value = "";
	vaBank.value = "";
	fetchOpenBills();
};

const holdOrder = async () => {
	if (cart.value.length === 0) {
		return;
	}

	if (orderType.value === "dine_in" && !selectedTable.value) {
		alert("Pilih meja dulu");

		return;
	}

	isSubmitting.value = true;

	try {
		const url = activeOrderId.value
			? `/api/orders/${activeOrderId.value}/items`
			: "/api/orders";
		const method = "POST";

		// Only send NEW items if updating
		const itemsToSend = activeOrderId.value
			? cart.value.filter((i) => !i.isExisting)
			: cart.value;

		if (activeOrderId.value && itemsToSend.length === 0) {
			alert("Tidak ada item baru untuk disimpan");
			isSubmitting.value = false;

			return;
		}

		const body: any = {
			items: itemsToSend.map((i) => ({
				menu_item_id: i.isPackage ? null : i.id,
				package_id: i.isPackage ? i.id : null,
				name: i.name,
				qty: i.qty,
				price: i.price,
				variants: i.variants,
				add_ons: i.add_ons,
				notes: i.notes,
			})),
		};

		if (!activeOrderId.value) {
			body.customer_id = selectedCustomer.value?.id;
			body.table_id = selectedTable.value;
			body.type = orderType.value;
			body.payment_method = "cash"; // placeholder
			body.payment_status = "unpaid";
			body.discount = discount.value;
			body.tax = tax.value;
		}

		const res = await fetch(url, {
			method,
			headers: {
				"Content-Type": "application/json",
				Accept: "application/json",
				"X-CSRF-TOKEN":
					(document.querySelector('meta[name="csrf-token"]') as any)?.content ||
					"",
			},
			body: JSON.stringify(body),
		});

		if (res.ok) {
			alert("Pesanan dikirim ke dapur!");
			resetPOS();
		}
	} catch (e) {
		console.error(e);
	} finally {
		isSubmitting.value = false;
	}
};

const handleCheckout = async () => {
	if (cart.value.length === 0) {
		return;
	}

	if (paymentMethod.value === "cash" && amountPaid.value < total.value) {
		alert("Jumlah bayar kurang");

		return;
	}

	isSubmitting.value = true;

	try {
		// If it's a new order being paid immediately
		if (!activeOrderId.value) {
			const res = await fetch("/api/orders", {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
					Accept: "application/json",
					"X-CSRF-TOKEN":
						(document.querySelector('meta[name="csrf-token"]') as any)
							?.content || "",
				},
				body: JSON.stringify({
					customer_id: selectedCustomer.value?.id,
					table_id: selectedTable.value,
					type: orderType.value,
					payment_method: paymentMethod.value,
					payment_status: "paid",
					discount: discount.value,
					tax: tax.value,
					bank: paymentMethod.value === "transfer" ? selectedBank.value : null,
					items: cart.value.map((i) => ({
						menu_item_id: i.isPackage ? null : i.id,
						package_id: i.isPackage ? i.id : null,
						name: i.name,
						qty: i.qty,
						price: i.price,
						variants: i.variants,
						add_ons: i.add_ons,
						notes: i.notes,
					})),
				}),
			});

			if (res.ok) {
				const data = await res.json();

				if (data.qr_url || data.va_number) {
					qrUrl.value = data.qr_url || "";
					vaNumber.value = data.va_number || "";
					vaBank.value = data.va_bank || "";
					requestedBank.value = data.requested_bank || "";
					activeOrderNumber.value = data.order_number;
					activeOrderFinalAmount.value = data.final_amount;
					qrModalOpen.value = true;
					startPolling(data.id);
				} else {
					alert("Pembayaran Berhasil!");
					resetPOS();
				}
			}
		} else {
			// Processing payment for an EXISTING order
			const res = await fetch(`/api/orders/${activeOrderId.value}/pay`, {
				method: "PATCH",
				headers: {
					"Content-Type": "application/json",
					Accept: "application/json",
					"X-CSRF-TOKEN":
						(document.querySelector('meta[name="csrf-token"]') as any)
							?.content || "",
				},
				body: JSON.stringify({
					payment_method: paymentMethod.value,
					discount: discount.value,
					bank: paymentMethod.value === "transfer" ? selectedBank.value : null,
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
						data.order?.order_number || data.order_number || "";
					activeOrderFinalAmount.value =
						data.order?.final_amount || data.final_amount || 0;
					qrModalOpen.value = true;
					startPolling(activeOrderId.value || data.order?.id || data.id);
				} else {
					alert("Pembayaran Berhasil!");
					resetPOS();
				}
			}
		}
	} catch (e) {
		console.error(e);
		alert("Terjadi kesalahan koneksi");
	} finally {
		isSubmitting.value = false;
	}
};

const formatIDR = (val: number) => {
	return new Intl.NumberFormat("id-ID", {
		style: "currency",
		currency: "IDR",
		minimumFractionDigits: 0,
	}).format(val);
};
</script>

<template>
    <div class="flex h-screen w-full flex-col overflow-hidden bg-brand-50">
        <!--  HEADER -->
        <header
            class="z-20 flex h-16 shrink-0 items-center justify-between border-b border-brand-100 bg-white px-6"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-lg shadow-brand-100"
                >
                    <ShoppingBag class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-lg leading-none font-black text-slate-800">
                        Toffeeman Cashier
                    </h1>
                    <p
                        class="text-[10px] font-bold tracking-wider text-brand-500 uppercase"
                    >
                        POS Terminal
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    @click="showBills = true"
                    variant="outline"
                    class="h-10 gap-2 rounded-xl border-brand-200 px-6 font-black text-brand-600 hover:bg-brand-50"
                >
                    <Receipt class="h-4 w-4" />
                    Open Bills
                    <Badge
                        v-if="openBills.length"
                        class="ml-1 flex h-5 w-5 items-center justify-center border-none bg-brand-600 p-0 text-[10px]"
                        >{{ openBills.length }}</Badge
                    >
                </Button>
                <div class="mx-2 h-8 w-[1px] bg-slate-100"></div>
                <Button
                    @click="resetPOS"
                    variant="ghost"
                    class="font-bold text-slate-400 hover:text-red-500"
                    >Reset</Button
                >
            </div>
        </header>

        <!-- MAIN PANELS -->
        <div class="flex flex-1 overflow-hidden">
            <!-- 🛒 LEFT PANEL: Product Selection -->
            <div
                class="flex flex-1 flex-col overflow-hidden border-r border-brand-100 bg-white"
            >
                <div
                    v-if="!showCustomization && !showDetail"
                    class="flex h-full animate-in flex-col duration-300 fade-in"
                >
                    <!-- Catalog Toolbar -->
                    <div
                        class="sticky top-0 z-10 border-b border-brand-50 bg-white px-6 py-3"
                    >
                        <div class="flex flex-col gap-2.5">
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex w-fit rounded-lg bg-brand-50 p-0.5"
                                    >
                                        <button
                                            @click="activeCategory = 'all'"
                                            class="rounded-md px-4 py-1.5 text-[11px] font-black transition-all"
                                            :class="
                                                activeCategory !== 'packages'
                                                    ? 'bg-brand-600 text-white shadow-sm'
                                                    : 'text-brand-400 hover:text-brand-600'
                                            "
                                        >
                                            MENU
                                        </button>
                                        <button
                                            @click="activeCategory = 'packages'"
                                            class="rounded-md px-4 py-1.5 text-[11px] font-black transition-all"
                                            :class="
                                                activeCategory === 'packages'
                                                    ? 'bg-brand-600 text-white shadow-sm'
                                                    : 'text-brand-400 hover:text-brand-600'
                                            "
                                        >
                                            PAKET
                                        </button>
                                    </div>
                                    <div
                                        class="flex shrink-0 rounded-lg bg-slate-100 p-0.5"
                                    >
                                        <button
                                            @click="viewMode = 'grid'"
                                            class="flex h-7 w-7 items-center justify-center rounded-md transition-all"
                                            :class="
                                                viewMode === 'grid'
                                                    ? 'bg-white text-brand-600 shadow-sm'
                                                    : 'text-slate-400 hover:text-slate-600'
                                            "
                                        >
                                            <LayoutGrid class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            @click="viewMode = 'list'"
                                            class="flex h-7 w-7 items-center justify-center rounded-md transition-all"
                                            :class="
                                                viewMode === 'list'
                                                    ? 'bg-white text-brand-600 shadow-sm'
                                                    : 'text-slate-400 hover:text-slate-600'
                                            "
                                        >
                                            <List class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Category list pills (only shown if not packages) -->
                            <div
                                v-if="activeCategory !== 'packages'"
                                class="no-scrollbar flex animate-in items-center gap-1.5 overflow-x-auto pb-1 duration-300 slide-in-from-top-2"
                            >
                                <button
                                    @click="activeCategory = 'all'"
                                    class="shrink-0 rounded-full border px-3 py-1 text-[9px] font-bold transition-all"
                                    :class="
                                        activeCategory === 'all'
                                            ? 'border-brand-600 bg-brand-50 text-brand-600'
                                            : 'border-slate-100 text-slate-400 hover:border-brand-200'
                                    "
                                >
                                    Semua Kategori
                                </button>
                                <button
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    @click="activeCategory = cat.id"
                                    class="shrink-0 rounded-full border px-3 py-1 text-[9px] font-bold transition-all"
                                    :class="
                                        activeCategory === cat.id
                                            ? 'border-brand-600 bg-brand-50 text-brand-600'
                                            : 'border-slate-100 text-slate-400 hover:border-brand-200'
                                    "
                                >
                                    {{ cat.name }}
                                </button>
                            </div>

                            <!-- Barcode Scanner Input Row -->
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1 sm:w-48">
                                    <Barcode
                                        class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-brand-300"
                                    />
                                    <Input
                                        ref="barcodeInputRef"
                                        v-model="scanQuery"
                                        @keydown.enter="handleBarcodeScan"
                                        placeholder="Scan Barcode / SKU..."
                                        class="h-8 rounded-lg border-brand-100 bg-white pl-8 text-xs font-bold focus-visible:ring-brand-500"
                                        :disabled="isScanning"
                                    />
                                    <div
                                        v-if="scanStatus"
                                        class="absolute top-[34px] left-0 z-50 animate-in rounded px-1.5 py-0.5 text-[10px] leading-tight font-bold shadow-md transition-all fade-in"
                                        :class="
                                            scanStatus.type === 'success'
                                                ? 'border border-emerald-200 bg-emerald-50 text-emerald-600'
                                                : 'border border-red-200 bg-red-50 text-red-600'
                                        "
                                    >
                                        {{ scanStatus.message }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <!-- ON/OFF TOGGLE -->
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 px-2.5 text-xs font-bold transition-all border-brand-100 flex items-center gap-1.5 shrink-0"
                                        :class="
                                            isScannerPollingActive
                                                ? 'bg-emerald-50 text-emerald-600 border-emerald-200 hover:bg-emerald-100 hover:text-emerald-700'
                                                : 'text-slate-400 hover:bg-slate-50'
                                        "
                                        @click="toggleScannerPolling"
                                        title="Aktifkan/Matikan Scanner HP"
                                    >
                                    <Smartphone class="w-5 h-5"/> Barcode
                                    </Button>

                                    <!-- VIEW QR BUTTON -->
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8 border-brand-100 text-brand-600 hover:bg-brand-50 shrink-0"
                                        @click="openPairingModal"
                                        title="Lihat QR Code Pairing HP"
                                    >
                                        <QrCode class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Menu Display -->
                    <div class="custom-scrollbar flex-1 overflow-y-auto p-6">
                        <!-- Search Bar -->
                        <div class="relative w-full mb-4 animate-in slide-in-from-top-1 duration-300">
                            <Search
                                class="absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-brand-300"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Cari menu / paket..."
                                class="h-9 rounded-lg border-brand-100 bg-white pl-9 text-xs font-bold focus-visible:ring-brand-500 w-full"
                            />
                        </div>
                        <div
                            v-if="loading"
                            class="flex h-full items-center justify-center"
                        >
                            <Loader2
                                class="h-8 w-8 animate-spin text-brand-600"
                            />
                        </div>
                        <div
                            v-else-if="filteredItems.length === 0"
                            class="flex h-full flex-col items-center justify-center text-brand-300"
                        >
                            <ShoppingBag class="mb-4 h-16 w-16 opacity-20" />
                            <p class="font-bold">Menu tidak ditemukan</p>
                        </div>

                        <div
                            v-else-if="viewMode === 'grid'"
                            class="grid animate-in grid-cols-2 gap-4 duration-500 fade-in md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
                        >
                            <div
                                v-for="item in filteredItems"
                                :key="item.id"
                                @click="
                                    addToCart(
                                        item,
                                        activeCategory === 'packages',
                                    )
                                "
                                @contextmenu.prevent="
                                    openDetail(
                                        item,
                                        activeCategory === 'packages',
                                    )
                                "
                                class="group relative cursor-pointer rounded-2xl border-2 border-brand-50 bg-white p-3 transition-all hover:border-brand-300 hover:shadow-xl hover:shadow-brand-100/50 active:scale-95"
                            >
                                <button
                                    @click.stop="
                                        openDetail(
                                            item,
                                            activeCategory === 'packages',
                                        )
                                    "
                                    class="absolute top-2 right-2 z-10 flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-white/80 text-slate-400 opacity-0 shadow-sm backdrop-blur-sm transition-all group-hover:opacity-100 hover:border-brand-300 hover:text-brand-600"
                                >
                                    <Info class="h-4 w-4" />
                                </button>
                                <div
                                    class="relative mb-3 aspect-square overflow-hidden rounded-xl bg-brand-50"
                                >
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-brand-200"
                                    >
                                        <Utensils class="h-10 w-10" />
                                    </div>
                                </div>
                                <div class="px-1">
                                    <p
                                        class="text-[9px] font-bold tracking-tighter text-brand-400 uppercase"
                                    >
                                        {{ item.category?.name || 'Paket' }}
                                    </p>
                                    <h3
                                        class="mb-1 truncate text-xs leading-tight font-black text-slate-800 uppercase"
                                    >
                                        {{ item.name }}
                                    </h3>
                                    <p class="text-xs font-bold text-brand-600">
                                        {{ formatIDR(Number(item.price)) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="animate-in space-y-2 duration-500 fade-in slide-in-from-bottom-4"
                        >
                            <div
                                v-for="item in filteredItems"
                                :key="item.id"
                                @click="
                                    addToCart(
                                        item,
                                        activeCategory === 'packages',
                                    )
                                "
                                class="group relative flex cursor-pointer items-center gap-4 rounded-xl border-2 border-brand-50 bg-white p-4 transition-all hover:border-brand-300 active:scale-[0.99]"
                            >
                                <div
                                    class="h-12 w-12 shrink-0 overflow-hidden rounded-lg border border-slate-100 bg-brand-50"
                                >
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-brand-200"
                                    >
                                        <Utensils class="h-6 w-6" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3
                                        class="truncate text-sm font-black text-slate-800 uppercase"
                                    >
                                        {{ item.name }}
                                    </h3>
                                    <p
                                        class="text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                                    >
                                        {{ item.category?.name || 'Paket' }}
                                    </p>
                                </div>
                                <div
                                    class="shrink-0 border-r border-slate-100 px-4 text-right"
                                >
                                    <p
                                        class="text-sm font-black text-brand-600"
                                    >
                                        {{ formatIDR(Number(item.price)) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2 pl-4">
                                    <button
                                        @click.stop="
                                            openDetail(
                                                item,
                                                activeCategory === 'packages',
                                            )
                                        "
                                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-100 bg-slate-50 text-slate-400 transition-all hover:bg-brand-50 hover:text-brand-600"
                                    >
                                        <Info class="h-5 w-5" />
                                    </button>
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-lg shadow-brand-100 transition-all group-hover:scale-110"
                                    >
                                        <Plus class="h-5 w-5" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🎨 PRODUCT DETAIL / CUSTOMIZATION (Unified) -->
                <div
                    v-else-if="showCustomization || showDetail"
                    class="flex h-full animate-in flex-col p-8 duration-300 fade-in slide-in-from-left-8"
                >
                    <div
                        class="mb-8 flex items-center justify-between border-b border-brand-100 pb-6"
                    >
                        <div class="flex items-center gap-4">
                            <Button
                                variant="ghost"
                                size="icon"
                                @click="
                                    showCustomization = false;
                                    showDetail = false;
                                "
                                class="rounded-xl bg-brand-50 text-brand-600"
                                ><ArrowLeft class="h-5 w-5"
                            /></Button>
                            <div>
                                <h2
                                    class="text-xl font-black tracking-tight text-slate-800 uppercase"
                                >
                                    {{ (customizingItem || detailItem)?.name }}
                                </h2>
                                <p
                                    class="text-xs font-bold tracking-widest text-brand-500 uppercase"
                                >
                                    {{
                                        showCustomization
                                            ? 'Varian & Topping'
                                            : 'Informasi Produk'
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Action Bar in Header (for Customization) -->
                        <div
                            v-if="showCustomization"
                            class="flex animate-in items-center gap-8 duration-500 zoom-in-95 fade-in"
                        >
                            <div class="text-right">
                                <p
                                    class="text-[10px] font-bold tracking-widest text-brand-400 uppercase"
                                >
                                    Total Harga Item
                                </p>
                                <p class="text-2xl font-black text-slate-800">
                                    {{ formatIDR(customizingItemTotalPrice) }}
                                </p>
                            </div>
                            <Button
                                @click="addCustomizedToCart"
                                class="h-14 rounded-xl bg-brand-600 px-10 font-black tracking-wider text-white uppercase shadow-lg shadow-brand-100 hover:bg-brand-700"
                                >Tambah</Button
                            >
                        </div>
                    </div>

                    <div class="custom-scrollbar flex-1 overflow-y-auto pr-4">
                        <!-- Customization View (No Image/Desc for more space) -->
                        <div
                            v-if="showCustomization"
                            class="animate-in space-y-8 duration-500 fade-in slide-in-from-right-4"
                        >
                            <div class="grid gap-8 lg:grid-cols-2">
                                <div
                                    v-for="opt in customizingItem?.options"
                                    :key="opt.id"
                                    class="space-y-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <h3
                                            class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-700 uppercase"
                                        >
                                            {{ opt.name
                                            }}<Badge
                                                v-if="opt.is_required"
                                                variant="outline"
                                                class="border-brand-100 bg-brand-50 text-[9px] text-brand-600"
                                                >Wajib</Badge
                                            >
                                        </h3>
                                        <span
                                            class="text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                                            >{{
                                                opt.type === 'multiple'
                                                    ? 'Pilih Banyak'
                                                    : 'Pilih Satu'
                                            }}</span
                                        >
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button
                                            v-for="val in opt.values"
                                            :key="val.id"
                                            @click="toggleOption(opt, val)"
                                            class="group relative overflow-hidden rounded-xl border-2 p-4 text-left transition-all"
                                            :class="
                                                isOptionSelected(opt, val.id)
                                                    ? 'border-brand-600 bg-brand-50 shadow-sm'
                                                    : 'border-slate-300 bg-white hover:border-brand-200'
                                            "
                                        >
                                            <div class="relative z-10">
                                                <p
                                                    class="text-xs font-black text-slate-700"
                                                >
                                                    {{ val.name }}
                                                </p>
                                                <p
                                                    class="text-[10px] font-bold text-brand-500"
                                                    v-if="
                                                        val.additional_price > 0
                                                    "
                                                >
                                                    +
                                                    {{
                                                        formatIDR(
                                                            val.additional_price,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <Check
                                                v-if="
                                                    isOptionSelected(
                                                        opt,
                                                        val.id,
                                                    )
                                                "
                                                class="absolute top-2 right-2 h-4 w-4 text-brand-600"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="customizingItem?.add_ons?.length"
                                    class="space-y-4"
                                >
                                    <h3
                                        class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-700 uppercase"
                                    >
                                        Add-ons
                                        <Badge
                                            variant="outline"
                                            class="border-blue-100 bg-blue-50 text-[9px] text-blue-600"
                                            >Opsional</Badge
                                        >
                                    </h3>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button
                                            v-for="ao in customizingItem.add_ons"
                                            :key="ao.id"
                                            @click="toggleAddOn(ao.id)"
                                            class="group relative overflow-hidden rounded-xl border-2 p-4 text-left transition-all"
                                            :class="
                                                selectedAddOns.includes(ao.id)
                                                    ? 'border-brand-600 bg-brand-50 shadow-sm'
                                                    : 'border-slate-300 bg-white hover:border-brand-200'
                                            "
                                        >
                                            <div class="relative z-10">
                                                <p
                                                    class="text-xs font-black text-slate-700"
                                                >
                                                    {{ ao.name }}
                                                </p>
                                                <p
                                                    class="text-[10px] font-bold text-brand-500"
                                                >
                                                    + {{ formatIDR(ao.price) }}
                                                </p>
                                            </div>
                                            <Check
                                                v-if="
                                                    selectedAddOns.includes(
                                                        ao.id,
                                                    )
                                                "
                                                class="absolute top-2 right-2 h-4 w-4 text-brand-600"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="space-y-4 rounded-3xl border border-slate-100 bg-slate-50/50 p-8"
                            >
                                <h3
                                    class="text-xs font-black tracking-wider text-slate-700 uppercase"
                                >
                                    Jumlah & Catatan
                                </h3>
                                <div class="flex items-center gap-8">
                                    <div
                                        class="flex items-center rounded-2xl border-2 border-brand-100 bg-white p-1 shadow-sm"
                                    >
                                        <button
                                            @click="
                                                customizingQty > 1
                                                    ? customizingQty--
                                                    : null
                                            "
                                            class="flex h-12 w-12 items-center justify-center rounded-xl text-brand-600 hover:bg-brand-50"
                                        >
                                            <Minus class="h-5 w-5" />
                                        </button>
                                        <span
                                            class="w-16 text-center text-xl font-black text-slate-800"
                                            >{{ customizingQty }}</span
                                        >
                                        <button
                                            @click="customizingQty++"
                                            class="flex h-12 w-12 items-center justify-center rounded-xl text-brand-600 hover:bg-brand-50"
                                        >
                                            <Plus class="h-5 w-5" />
                                        </button>
                                    </div>
                                    <textarea
                                        v-model="customizingNotes"
                                        placeholder="Catatan khusus (misal: kurangi gula, tanpa es)..."
                                        class="h-14 flex-1 resize-none rounded-2xl border-2 border-slate-100 bg-white px-6 py-4 text-sm font-medium transition-all focus:border-brand-300 focus:outline-none"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Product Detail View (Keep Image/Desc) -->
                        <div
                            v-if="showDetail"
                            class="grid gap-12 lg:grid-cols-2"
                        >
                            <!-- Image & Desc -->
                            <div class="space-y-6">
                                <div
                                    class="aspect-video overflow-hidden rounded-3xl border-2 border-slate-100 bg-brand-50 shadow-inner"
                                >
                                    <img
                                        v-if="detailItem?.image_url"
                                        :src="detailItem?.image_url"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-brand-200"
                                    >
                                        <Utensils class="h-20 w-20" />
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <h3
                                        class="text-xs font-black tracking-wider text-slate-700 uppercase"
                                    >
                                        Deskripsi
                                    </h3>
                                    <p
                                        class="rounded-2xl border border-slate-100 bg-slate-50 p-6 text-sm leading-relaxed text-slate-500 italic"
                                    >
                                        {{
                                            detailItem?.description ||
                                            'Tidak ada deskripsi untuk produk ini.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- Product Info UI -->
                            <div
                                class="animate-in space-y-8 duration-500 fade-in slide-in-from-right-4"
                            >
                                <div class="grid grid-cols-2 gap-4">
                                    <div
                                        class="flex items-center gap-3 rounded-2xl border border-brand-100 bg-brand-50 p-4"
                                    >
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm"
                                        >
                                            <Flame class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <p
                                                class="text-[10px] font-bold tracking-widest text-brand-400 uppercase"
                                            >
                                                Energi
                                            </p>
                                            <p
                                                class="text-sm font-black text-slate-700"
                                            >
                                                {{ detailItem?.calories || 0 }}
                                                kcal
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center gap-3 rounded-2xl border border-brand-100 bg-brand-50 p-4"
                                    >
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm"
                                        >
                                            <LayoutGrid class="h-5 w-5" />
                                        </div>
                                        <div>
                                            <p
                                                class="text-[10px] font-bold tracking-widest text-brand-400 uppercase"
                                            >
                                                Kategori
                                            </p>
                                            <p
                                                class="text-sm font-black text-slate-700 uppercase"
                                            >
                                                {{
                                                    detailItem?.category
                                                        ?.name || 'Paket'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="detailItem?.allergens?.length"
                                    class="space-y-3"
                                >
                                    <div class="item-center relative flex">
                                        <ShieldAlert class="h-4 w-4" />
                                        <h3
                                            class="ml-1 text-xs font-black tracking-wider text-slate-700 uppercase"
                                        >
                                            Allergen
                                        </h3>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="allergen in detailItem.allergens"
                                            :key="allergen"
                                            variant="outline"
                                            class="rounded-lg border-red-100 bg-red-50 px-3 py-1 text-[10px] font-bold text-red-600 uppercase"
                                            >{{ allergen }}</Badge
                                        >
                                    </div>
                                </div>

                                <div
                                    v-if="detailItem?.tags?.length"
                                    class="space-y-3"
                                >
                                    <div class="relative flex items-center">
                                        <Tag class="h-4 w-4" />
                                        <h3
                                            class="ml-1 text-xs font-black tracking-wider text-slate-700 uppercase"
                                        >
                                            Tags
                                        </h3>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="tag in detailItem.tags"
                                            :key="tag"
                                            variant="secondary"
                                            class="rounded-lg border-none bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-600 uppercase"
                                            >{{ tag }}</Badge
                                        >
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between gap-6 pt-4"
                                >
                                    <div class="flex flex-col">
                                        <p
                                            class="text-[10px] font-bold tracking-widest text-slate-400 uppercase"
                                        >
                                            Harga Dasar
                                        </p>
                                        <p
                                            class="text-2xl font-black text-slate-800"
                                        >
                                            {{
                                                formatIDR(
                                                    detailItem?.price || 0,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <Button
                                        @click="
                                            addToCart(
                                                detailItem,
                                                detailItem?.isPackage,
                                            )
                                        "
                                        class="h-14 flex-1 rounded-xl bg-brand-600 font-black tracking-wider text-white uppercase shadow-lg shadow-brand-100 hover:bg-brand-700"
                                        >Tambah</Button
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🛒 RIGHT PANEL: Cart & Checkout -->
            <div
                class="relative z-10 flex w-[450px] flex-col overflow-hidden border-l border-brand-100 bg-white shadow-2xl"
            >
                <div class="flex flex-1 flex-col overflow-hidden p-8">
                    <div class="mb-8 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <Button
                                v-if="cartStep > 1"
                                variant="ghost"
                                size="icon"
                                @click="cartStep--"
                                class="h-8 w-8 rounded-full bg-brand-50 text-brand-600"
                                ><ArrowLeft class="h-4 w-4"
                            /></Button>
                            <h2
                                class="flex items-center gap-3 text-lg font-black text-slate-800"
                            >
                                <ShoppingCart
                                    v-if="cartStep === 1"
                                    class="h-5 w-5 text-brand-600"
                                />
                                <UserIcon
                                    v-else-if="cartStep === 2"
                                    class="h-5 w-5 text-brand-600"
                                />
                                <CreditCard
                                    v-else-if="cartStep === 3"
                                    class="h-5 w-5 text-brand-600"
                                />
                                <Check v-else class="h-5 w-5 text-brand-600" />
                                {{
                                    cartStep === 1
                                        ? 'Isi Keranjang'
                                        : cartStep === 2
                                          ? 'Data Order'
                                          : cartStep === 3
                                            ? 'Pembayaran'
                                            : 'Konfirmasi Akhir'
                                }}
                            </h2>
                        </div>
                        <Badge
                            variant="outline"
                            class="rounded-full border-brand-100 bg-brand-50 px-3 py-0.5 text-[10px] font-black text-brand-600 uppercase"
                            >{{ cart.length }} Item</Badge
                        >
                    </div>

                    <!-- STEP 1: CART ITEMS -->
                    <div
                        v-if="cartStep === 1"
                        class="flex flex-1 animate-in flex-col overflow-hidden duration-300 fade-in"
                    >
                        <div
                            v-if="activeOrderId"
                            class="mb-6 flex items-center justify-between p-4 text-brand-600 border-b border-solid border-brand-900"
                        >
                            <div class="flex items-center gap-3">
                                <Receipt class="h-5 w-5 text-brand-400" />
                                <div>
                                    <p
                                        class="text-[9px] font-bold tracking-widest text-brand-900 uppercase"
                                    >
                                        Updating Order
                                    </p>
                                    <p class="text-xs font-black">
                                        #{{
                                            openBills
                                                .find(
                                                    (b) =>
                                                        b.id === activeOrderId,
                                                )
                                                ?.order_number.slice(-8)
                                        }}
                                    </p>
                                </div>
                            </div>
                            <Button
                                @click="
                                    activeOrderId = null;
                                    cart = [];
                                "
                                variant="ghost"
                                size="sm"
                                class="h-8 text-white/60 hover:text-white"
                                ><X class="h-4 w-4"
                            /></Button>
                        </div>

                        <div
                            class="custom-scrollbar mb-8 flex-1 overflow-y-auto pr-2"
                        >
                            <div
                                v-if="cart.length === 0"
                                class="flex h-full flex-col items-center justify-center text-brand-200 opacity-40"
                            >
                                <ShoppingCart class="mb-4 h-16 w-16" />
                                <p
                                    class="text-sm font-bold text-slate-400 italic"
                                >
                                    Keranjang belanja kosong...
                                </p>
                            </div>
                            <div v-else class="space-y-5">
                                <div
                                    v-for="(item, idx) in cart"
                                    :key="item.cartId || item.id"
                                    class="group flex animate-in gap-4 duration-300 slide-in-from-right-4"
                                >
                                    <div
                                        class="relative h-14 w-14 flex-shrink-0 overflow-hidden rounded-xl bg-brand-50"
                                    >
                                        <img
                                            v-if="item.image_url"
                                            :src="item.image_url"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center text-brand-200"
                                        >
                                            <Utensils class="h-5 w-5" />
                                        </div>
                                        <div
                                            v-if="item.isExisting"
                                            class="absolute inset-0 flex items-center justify-center bg-slate-900/40"
                                        >
                                            <Check class="h-6 w-6 text-white" />
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="mb-1 flex items-start justify-between"
                                        >
                                            <h4
                                                class="truncate pr-2 text-xs font-black text-slate-700 uppercase"
                                                :class="
                                                    item.status === 'cancelled'
                                                        ? 'line-through opacity-40'
                                                        : ''
                                                "
                                            >
                                                {{ item.name }}
                                            </h4>
                                            <button
                                                @click="
                                                    item.isExisting
                                                        ? cancelExistingItem(
                                                              item,
                                                              idx,
                                                          )
                                                        : removeFromCart(idx)
                                                "
                                                class="text-brand-300 transition-colors hover:text-red-500"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <p
                                            class="mb-2 text-[11px] font-bold text-brand-600"
                                        >
                                            {{ formatIDR(item.price) }}
                                            <Badge
                                                v-if="item.isExisting"
                                                variant="outline"
                                                class="ml-2 border-brand-200 text-[8px] text-brand-400"
                                                >Stored</Badge
                                            >
                                        </p>
                                        <div class="flex items-center gap-3">
                                            <div
                                                v-if="!item.isExisting"
                                                class="flex items-center rounded-lg bg-brand-50 p-0.5"
                                            >
                                                <button
                                                    @click="
                                                        item.qty > 1
                                                            ? item.qty--
                                                            : removeFromCart(
                                                                  idx,
                                                              )
                                                    "
                                                    class="flex h-6 w-6 items-center justify-center rounded text-brand-600 shadow-sm transition-all hover:bg-white"
                                                >
                                                    <Minus
                                                        class="h-2.5 w-2.5"
                                                    />
                                                </button>
                                                <span
                                                    class="w-8 text-center text-xs font-black text-slate-700"
                                                    >{{ item.qty }}</span
                                                >
                                                <button
                                                    @click="item.qty++"
                                                    class="flex h-6 w-6 items-center justify-center rounded text-brand-600 shadow-sm transition-all hover:bg-white"
                                                >
                                                    <Plus class="h-2.5 w-2.5" />
                                                </button>
                                            </div>
                                            <span
                                                v-else
                                                class="text-xs font-black text-slate-400"
                                                >Qty: {{ item.qty }}</span
                                            >
                                            <span
                                                class="ml-auto text-xs font-black text-slate-800"
                                                :class="
                                                    item.status === 'cancelled'
                                                        ? 'text-slate-300 line-through'
                                                        : ''
                                                "
                                                >{{
                                                    formatIDR(
                                                        item.price * item.qty,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-brand-50 pt-6">
                            <div
                                class="mb-6 space-y-2 rounded-2xl bg-slate-50 p-5"
                            >
                                <div
                                    class="flex justify-between text-[10px] font-bold text-slate-500"
                                >
                                    <span>Subtotal</span
                                    ><span>{{ formatIDR(subtotal) }}</span>
                                </div>
                                <div
                                    class="flex justify-between text-base font-black text-slate-800"
                                >
                                    <span>Total Tagihan</span
                                    ><span class="text-brand-600">{{
                                        formatIDR(total)
                                    }}</span>
                                </div>
                            </div>
                            <Button
                                @click="cartStep = 2"
                                :disabled="cart.length === 0"
                                class="flex h-14 w-full items-center justify-between rounded-xl bg-brand-600 px-6 text-base font-black text-white shadow-lg shadow-brand-100 transition-all hover:bg-brand-700 active:scale-[0.98]"
                            >
                                <span>LANJUT DATA ORDER</span
                                ><ChevronRight class="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <!-- STEP 2: ORDER INFO -->
                    <div
                        v-else-if="cartStep === 2"
                        class="flex flex-1 animate-in flex-col overflow-hidden duration-300 fade-in slide-in-from-right-8"
                    >
                        <div
                            class="custom-scrollbar flex-1 space-y-6 overflow-y-auto pr-2"
                        >
                            <div class="space-y-2">
                                <Label
                                    class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    ><UserIcon class="h-3 w-3" /> Pilih
                                    Pembeli</Label
                                >
                                <select
                                    v-model="selectedCustomer"
                                    :disabled="activeOrderId !== null"
                                    class="h-12 w-full rounded-xl border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-brand-500 focus:outline-none disabled:opacity-50"
                                >
                                    <option :value="null">Pembeli Umum</option>
                                    <option
                                        v-for="c in customers"
                                        :key="c?.id"
                                        :value="c"
                                    >
                                        {{ c?.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <Label
                                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        ><ShoppingBag class="h-3 w-3" /> Metode
                                        Konsumsi</Label
                                    >
                                    <div
                                        class="flex h-12 rounded-xl bg-brand-50 p-0.5"
                                    >
                                        <button
                                            @click="orderType = 'dine_in'"
                                            :disabled="activeOrderId !== null"
                                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg text-[10px] font-black transition-all"
                                            :class="
                                                orderType === 'dine_in'
                                                    ? 'bg-white text-brand-600 shadow-sm'
                                                    : 'text-brand-300'
                                            "
                                        >
                                            Dine In
                                        </button>
                                        <button
                                            @click="orderType = 'take_away'"
                                            :disabled="activeOrderId !== null"
                                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg text-[10px] font-black transition-all"
                                            :class="
                                                orderType === 'take_away'
                                                    ? 'bg-white text-brand-600 shadow-sm'
                                                    : 'text-brand-300'
                                            "
                                        >
                                            Take Away
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-if="orderType === 'dine_in'"
                                    class="animate-in space-y-2 duration-300 zoom-in-95 fade-in"
                                >
                                    <Label
                                        class="flex items-center gap-1.5 text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                        ><TableIcon class="h-3 w-3" />
                                        Meja</Label
                                    >
                                    <div class="grid grid-cols-4 gap-2">
                                        <button
                                            v-for="t in tables"
                                            :key="t.id"
                                            @click="selectedTable = t.id"
                                            :disabled="activeOrderId !== null"
                                            class="flex h-10 items-center justify-center rounded-xl border-2 text-xs font-black transition-all"
                                            :class="
                                                selectedTable === t.id
                                                    ? 'border-brand-600 bg-brand-50 text-brand-600'
                                                    : 'border-slate-100 text-slate-500 hover:border-brand-200 disabled:opacity-50'
                                            "
                                        >
                                            {{ t.number }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-2 gap-3 border-t border-brand-50 pt-6"
                        >
                            <Button
                                @click="holdOrder"
                                :disabled="isSubmitting"
                                variant="outline"
                                class="h-14 gap-2 rounded-xl border-brand-200 text-[10px] font-black tracking-widest text-brand-600 uppercase"
                            >
                                <ChefHat class="h-4 w-4" /> SIMPAN & DAPUR
                            </Button>
                            <Button
                                @click="cartStep = 3"
                                :disabled="
                                    orderType === 'dine_in' && !selectedTable
                                "
                                class="flex h-14 items-center justify-between rounded-xl bg-brand-600 px-4 text-[10px] font-black tracking-widest text-white uppercase hover:bg-brand-700"
                            >
                                <span>BAYAR</span
                                ><ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- STEP 3: PAYMENT INPUT -->
                    <div
                        v-else-if="cartStep === 3"
                        class="flex flex-1 animate-in flex-col overflow-hidden duration-300 fade-in slide-in-from-right-8"
                    >
                        <div
                            class="custom-scrollbar flex-1 space-y-8 overflow-y-auto pr-2"
                        >
                            <div
                                class="space-y-2 rounded-3xl bg-slate-900 p-8 text-white shadow-xl"
                            >
                                <p
                                    class="text-[10px] font-black tracking-widest text-slate-500 uppercase"
                                >
                                    Total Tagihan
                                </p>
                                <h3 class="text-4xl font-black">
                                    {{ formatIDR(total) }}
                                </h3>
                            </div>
                            <div class="space-y-4">
                                <Label
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >Metode Pembayaran</Label
                                >
                                <div class="flex gap-3">
                                    <button
                                        @click="paymentMethod = 'cash'"
                                        class="flex flex-1 flex-col items-center justify-center rounded-2xl border-2 p-4 transition-all"
                                        :class="
                                            paymentMethod === 'cash'
                                                ? 'border-brand-600 bg-brand-50 text-brand-600 shadow-sm'
                                                : 'border-slate-100 text-slate-400 hover:border-brand-100'
                                        "
                                    >
                                        <Banknote class="mb-2 h-6 w-6" /><span
                                            class="text-[11px] font-black tracking-tighter uppercase"
                                            >Tunai</span
                                        >
                                    </button>
                                    <button
                                        @click="paymentMethod = 'qris'"
                                        class="flex flex-1 flex-col items-center justify-center rounded-2xl border-2 p-4 transition-all"
                                        :class="
                                            paymentMethod === 'qris'
                                                ? 'border-brand-600 bg-brand-50 text-brand-600 shadow-sm'
                                                : 'border-slate-100 text-slate-400 hover:border-brand-100'
                                        "
                                    >
                                        <CreditCard class="mb-2 h-6 w-6" /><span
                                            class="text-[11px] font-black tracking-tighter uppercase"
                                            >QRIS</span
                                        >
                                    </button>
                                    <button
                                        @click="paymentMethod = 'transfer'"
                                        class="flex flex-1 flex-col items-center justify-center rounded-2xl border-2 p-4 transition-all"
                                        :class="
                                            paymentMethod === 'transfer'
                                                ? 'border-brand-600 bg-brand-50 text-brand-600 shadow-sm'
                                                : 'border-slate-100 text-slate-400 hover:border-brand-100'
                                        "
                                    >
                                        <Landmark class="mb-2 h-6 w-6" /><span
                                            class="text-[11px] font-black tracking-tighter uppercase"
                                            >Bank</span
                                        >
                                    </button>
                                </div>
                                <div
                                    v-if="paymentMethod === 'cash'"
                                    class="animate-in space-y-4 rounded-3xl border border-brand-100 bg-brand-50/50 p-6 duration-300 slide-in-from-bottom-2"
                                >
                                    <div class="space-y-2">
                                        <Label
                                            class="text-[9px] font-black tracking-widest text-brand-400 uppercase"
                                            >Uang Diterima</Label
                                        ><Input
                                            type="number"
                                            v-model="amountPaid"
                                            class="h-14 rounded-2xl border-brand-100 bg-white px-6 text-2xl font-black focus-visible:ring-brand-500"
                                            placeholder="0"
                                        />
                                    </div>
                                    <div
                                        class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
                                    >
                                        <Label
                                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase"
                                            >Kembalian</Label
                                        >
                                        <div
                                            class="text-xl font-black text-emerald-600"
                                        >
                                            {{ formatIDR(change) }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="paymentMethod === 'transfer'"
                                    class="animate-in space-y-4 rounded-3xl border border-brand-100 bg-brand-50/50 p-6 duration-300 slide-in-from-bottom-2"
                                >
                                    <Label
                                        class="mb-1 block text-[9px] font-black tracking-widest text-brand-400 uppercase"
                                        >Pilih Bank Transfer VA</Label
                                    >
                                    <div
                                        class="custom-scrollbar grid max-h-[180px] grid-cols-2 gap-2 overflow-y-auto pr-1.5"
                                    >
                                        <button
                                            v-for="b in banksList"
                                            :key="b.id"
                                            @click="selectedBank = b.id"
                                            type="button"
                                            class="flex items-center justify-between rounded-xl border px-3 py-2.5 text-left text-[11px] font-black transition-all"
                                            :class="
                                                selectedBank === b.id
                                                    ? 'border-brand-600 bg-brand-100/50 font-extrabold text-brand-700 shadow-sm'
                                                    : 'border-slate-200 bg-white text-slate-500 hover:border-brand-200 hover:bg-slate-50'
                                            "
                                        >
                                            <span>{{ b.name }}</span>
                                            <span
                                                v-if="selectedBank === b.id"
                                                class="h-1.5 w-1.5 rounded-full bg-brand-600"
                                            ></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-brand-50 pt-6">
                            <Button
                                @click="cartStep = 4"
                                :disabled="
                                    paymentMethod === 'cash' &&
                                    amountPaid < total
                                "
                                class="flex h-14 w-full items-center justify-between rounded-xl bg-brand-600 px-6 text-base text-xs font-black tracking-widest text-white uppercase shadow-lg shadow-brand-100 transition-all hover:bg-brand-700 active:scale-[0.98]"
                                ><span>REVIEW AKHIR</span
                                ><ChevronRight class="h-5 w-5"
                            /></Button>
                        </div>
                    </div>

                    <!-- STEP 4: FINAL CONFIRMATION -->
                    <div
                        v-else
                        class="flex flex-1 animate-in flex-col overflow-hidden duration-300 fade-in slide-in-from-right-8"
                    >
                        <div
                            class="custom-scrollbar flex-1 space-y-6 overflow-y-auto pr-2"
                        >
                            <div
                                class="space-y-4 rounded-3xl border border-brand-100 bg-brand-50 p-6"
                            >
                                <h3
                                    class="text-center text-sm font-black tracking-widest text-brand-800 uppercase"
                                >
                                    Konfirmasi Pembayaran
                                </h3>
                                <div
                                    class="space-y-2 border-y border-brand-200/50 py-4"
                                >
                                    <div
                                        class="flex justify-between text-xs font-bold text-brand-600"
                                    >
                                        <span>Pembeli</span
                                        ><span>{{
                                            selectedCustomer?.name || 'Umum'
                                        }}</span>
                                    </div>
                                    <div
                                        class="flex justify-between text-xs font-bold text-brand-600"
                                    >
                                        <span>Meja</span
                                        ><span>{{
                                            selectedTable
                                                ? tables.find(
                                                      (t) =>
                                                          t.id ===
                                                          selectedTable,
                                                  )?.number
                                                : 'Take Away'
                                        }}</span>
                                    </div>
                                    <div
                                        class="flex justify-between text-xs font-bold text-brand-600"
                                    >
                                        <span>Pembayaran</span
                                        ><span class="uppercase">{{
                                            paymentMethod
                                        }}</span>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div
                                        class="flex justify-between text-xs font-bold text-slate-400"
                                    >
                                        <span>Total Belanja</span
                                        ><span>{{ formatIDR(total) }}</span>
                                    </div>
                                    <div
                                        v-if="paymentMethod === 'cash'"
                                        class="flex justify-between text-xs font-bold text-slate-400"
                                    >
                                        <span>Dibayar</span
                                        ><span>{{
                                            formatIDR(amountPaid)
                                        }}</span>
                                    </div>
                                    <div
                                        v-if="paymentMethod === 'cash'"
                                        class="flex justify-between border-t border-brand-200/50 pt-2 text-lg font-black text-emerald-600"
                                    >
                                        <span>Kembalian</span
                                        ><span>{{ formatIDR(change) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <Label
                                    class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                                    >Ringkasan Pesanan</Label
                                >
                                <div class="space-y-2">
                                    <div
                                        v-for="item in cart"
                                        :key="item.cartId || item.id"
                                        class="flex justify-between rounded-xl border border-slate-100 bg-white p-3 text-[11px] font-bold text-slate-600 shadow-sm"
                                        :class="
                                            item.status === 'cancelled'
                                                ? 'opacity-30'
                                                : ''
                                        "
                                    >
                                        <span
                                            >{{ item.qty }}x
                                            {{ item.name }}</span
                                        >
                                        <span>{{
                                            formatIDR(item.price * item.qty)
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-brand-50 pt-6">
                            <Button
                                @click="handleCheckout"
                                :disabled="isSubmitting"
                                class="h-16 w-full rounded-xl bg-brand-600 text-base font-black tracking-widest text-white uppercase shadow-lg shadow-brand-100 transition-all hover:bg-brand-700 active:scale-[0.98]"
                            >
                                <Loader2
                                    v-if="isSubmitting"
                                    class="mr-2 h-6 w-6 animate-spin"
                                />
                                <Check v-else class="mr-2 h-6 w-6" />
                                SELESAIKAN PESANAN
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🧾 MODAL: Open Bills -->
        <ModalOpenBills
            :is-open="showBills"
            :open-bills="openBills"
            @close="showBills = false"
            @select="selectBill"
            @pay-directly="payBillDirectly"
        />

        <!-- 📱 DIALOG: Midtrans QRIS Sandbox Code -->
        <ModalPaymentQris
            :is-open="qrModalOpen"
            :active-order-number="activeOrderNumber"
            :active-order-final-amount="activeOrderFinalAmount"
            :payment-success="paymentSuccess"
            :va-number="vaNumber"
            :va-bank="vaBank"
            :requested-bank="requestedBank"
            :qr-url="qrUrl"
            @close="
                () => {
                    qrModalOpen = false;
                    stopPolling();
                    qrUrl = '';
                    vaNumber = '';
                    vaBank = '';
                    resetPOS();
                }
            "
        />

        <!-- 📱 DIALOG: Mobile Scanner Pairing -->
        <Dialog :open="isPairingModalOpen" @update:open="closePairingModal">
            <DialogContent class="sm:max-w-md bg-slate-100 text-slate-100 border-slate-800">
                <DialogHeader>
                    <DialogTitle class="text-slate-900 flex items-center gap-2">
                        <Smartphone class="h-5 w-5" />
                        Hubungkan Kamera HP sebagai Scanner
                    </DialogTitle>
                    <DialogDescription class="text-slate-600">
                        Scan QR Code di bawah menggunakan HP Anda untuk mulai memindai barcode.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col items-center justify-center">
                    <div class="bg-white p-3 rounded-2xl shadow-xl gap-2">
                        <!-- Free QR code service -->
                        <img
                            v-if="pairingUrl"
                            :src="'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(pairingUrl)"
                            alt="QR Code Pairing"
                            class="w-48 h-48"
                        />
                    </div>
                    <div class="text-center w-full mt-5">
                        <p class="text-xs text-slate-600">Atau buka URL ini di HP Anda:</p>
                        <a
                            :href="pairingUrl"
                                target="_blank"
                                class="text-xs font-mono text-amber-900 underline hover:text-amber-800 break-all select-all block mt-1"
                        >
                            {{ pairingUrl }}
                        </a>
                    </div>
                </div>
                <DialogFooter class="sm:justify-center">
                    <Button type="button" variant="secondary" @click="closePairingModal" class="bg-slate-800 hover:bg-slate-700 text-slate-200">
                        Tutup
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #b45309;
    border-radius: 10px;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
