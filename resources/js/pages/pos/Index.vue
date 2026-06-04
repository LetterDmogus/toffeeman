<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { 
    Search, ShoppingCart, User as UserIcon, Table as TableIcon, 
    ChevronRight, Plus, Minus, X, Check, Loader2, ArrowLeft,
    CreditCard, Banknote, Landmark, ShoppingBag, Utensils,
    Info, Flame, ShieldAlert, Tag, LayoutGrid, List, Receipt,
    ChefHat, Save, Barcode
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Kasir (POS)', href: '#' },
        ],
    },
});

// ─── Midtrans QRIS State ──────────────────────────────────────────────────────
const qrModalOpen = ref(false);
const qrUrl = ref('');
const vaNumber = ref('');
const vaBank = ref('');
const requestedBank = ref('');
const activeOrderNumber = ref('');
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
                headers: { Accept: 'application/json' }
            });
            if (res.ok) {
                const data = await res.json();
                if (data.payment_status === 'paid') {
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
            console.error('Error polling order status:', err);
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

// ─── Data Fetching ───────────────────────────────────────────────────────────
const menuItems = ref<any[]>([]);
const categories = ref<any[]>([]);
const customers = ref<any[]>([]);
const tables = ref<any[]>([]);
const packages = ref<any[]>([]);
const openBills = ref<any[]>([]);

const loading = ref(true);
const viewMode = ref<'grid' | 'list'>('grid');
const activeCategory = ref<number | 'all' | 'packages'>('all');
const searchQuery = ref('');

const fetchData = async () => {
    loading.value = true;
    try {
        const [menuRes, catRes, custRes, tableRes, pkgRes] = await Promise.all([
            fetch('/api/menu-items?per_page=100', { headers: { Accept: 'application/json' } }),
            fetch('/api/categories?all=true', { headers: { Accept: 'application/json' } }),
            fetch('/api/users?role=customer&all=true', { headers: { Accept: 'application/json' } }),
            fetch('/api/tables?all=true', { headers: { Accept: 'application/json' } }),
            fetch('/api/packages?all=true', { headers: { Accept: 'application/json' } })
        ]);

        if (menuRes.ok) menuItems.value = (await menuRes.json()).data;
        if (catRes.ok) categories.value = await catRes.json();
        if (custRes.ok) customers.value = await custRes.json();
        if (tableRes.ok) tables.value = await tableRes.json();
        if (pkgRes.ok) packages.value = await pkgRes.json();
        
        await fetchOpenBills();
    } catch (e) { console.error(e); }
    finally { loading.value = false; }
};

const fetchOpenBills = async () => {
    try {
        const res = await fetch('/api/orders?payment_status=unpaid&per_page=100', { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            openBills.value = data.data;
        }
    } catch (e) { console.error(e); }
};

onMounted(fetchData);

// ─── Filtering Logic ─────────────────────────────────────────────────────────
const filteredItems = computed(() => {
    let items = activeCategory.value === 'packages' ? packages.value : menuItems.value;
    
    if (activeCategory.value !== 'all' && activeCategory.value !== 'packages') {
        items = items.filter(i => i.category_id === activeCategory.value);
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        items = items.filter(i => i.name.toLowerCase().includes(q));
    }

    return items;
});

// ─── Cart Logic ──────────────────────────────────────────────────────────────
const cart = ref<any[]>([]);
const cartStep = ref<1 | 2 | 3 | 4>(1);
const selectedCustomer = ref<any | null>(null);
const orderType = ref<'dine_in' | 'take_away'>('dine_in');
const selectedTable = ref<number | null>(null);
const paymentMethod = ref<'cash' | 'qris' | 'transfer'>('cash');
const selectedBank = ref<string>('bca');
const banksList = [
    { id: 'bca', name: 'BCA VA' },
    { id: 'bri', name: 'BRI VA' },
    { id: 'bni', name: 'BNI VA' },
    { id: 'permata', name: 'Permata VA' },
    { id: 'cimb', name: 'CIMB VA' },
    { id: 'mandiri', name: 'Mandiri Bill' },
    { id: 'danamon', name: 'Danamon VA' },
    { id: 'bsi', name: 'BSI VA' },
    { id: 'seabank', name: 'SeaBank VA' },
    { id: 'saqu', name: 'Saqu VA' },
];
const amountPaid = ref<number>(0);
const activeOrderId = ref<number | null>(null);

const subtotal = computed(() => cart.value.reduce((acc, item) => acc + (item.price * item.qty), 0));
const tax = computed(() => Math.round(subtotal.value * 0.1)); // 10% tax
const discount = ref(0);
const total = computed(() => subtotal.value + tax.value - discount.value);
const change = computed(() => Math.max(0, amountPaid.value - total.value));

const customizingQty = ref(1);
const customizingNotes = ref('');

const addToCart = (item: any, isPackage = false) => {
    customizingItem.value = JSON.parse(JSON.stringify(item));
    customizingItem.value.isPackage = isPackage;
    selectedOptions.value = {};
    selectedAddOns.value = [];
    customizingQty.value = 1;
    customizingNotes.value = '';
    showCustomization.value = true;
    showDetail.value = false;
};

const customizingItemTotalPrice = computed(() => {
    if (!customizingItem.value) return 0;
    let price = Number(customizingItem.value.price);
    
    // Add variants
    Object.values(selectedOptions.value).forEach(val => {
        if (Array.isArray(val)) {
            val.forEach(v => {
                price += Number(v.additional_price || 0);
            });
        } else {
            price += Number(val.additional_price || 0);
        }
    });

    // Add add-ons
    selectedAddOns.value.forEach(id => {
        const ao = customizingItem.value.add_ons?.find((a: any) => a.id === id);
        if (ao) price += Number(ao.price);
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
    if (opt.type === 'single') {
        selectedOptions.value[opt.id] = val;
    } else {
        if (!selectedOptions.value[opt.id] || !Array.isArray(selectedOptions.value[opt.id])) {
            selectedOptions.value[opt.id] = [];
        }
        const index = (selectedOptions.value[opt.id] as any[]).findIndex((v: any) => v.id === val.id);
        if (index > -1) {
            (selectedOptions.value[opt.id] as any[]).splice(index, 1);
        } else {
            (selectedOptions.value[opt.id] as any[]).push(val);
        }
    }
};

const isOptionSelected = (opt: any, valId: number) => {
    const selected = selectedOptions.value[opt.id];
    if (!selected) return false;
    if (opt.type === 'single') return selected.id === valId;
    return (selected as any[]).some((v: any) => v.id === valId);
};

const toggleAddOn = (id: number) => {
    const idx = selectedAddOns.value.indexOf(id);
    if (idx > -1) selectedAddOns.value.splice(idx, 1);
    else selectedAddOns.value.push(id);
};

const openDetail = (item: any, isPackage = false) => {
    detailItem.value = { ...item, isPackage };
    showDetail.value = true;
    showCustomization.value = false;
};

const addCustomizedToCart = () => {
    if (!customizingItem.value) return;

    const missingRequired = customizingItem.value.options?.filter((opt: any) => {
        const selected = selectedOptions.value[opt.id];
        return opt.is_required && (!selected || (Array.isArray(selected) && selected.length === 0));
    });

    if (missingRequired?.length > 0) {
        alert(`Silakan pilih opsi wajib: ${missingRequired.map((o: any) => o.name).join(', ')}`);
        return;
    }

    let additionalPrice = 0;
    const variants: any[] = [];
    
    Object.entries(selectedOptions.value).forEach(([optId, val]) => {
        const opt = customizingItem.value.options?.find((o: any) => o.id === Number(optId));
        if (Array.isArray(val)) {
            val.forEach(v => {
                additionalPrice += Number(v.additional_price || 0);
                variants.push({ ...v, option_name: opt?.name || '' });
            });
        } else {
            additionalPrice += Number(val.additional_price || 0);
            variants.push({ ...val, option_name: opt?.name || '' });
        }
    });

    const addOns: any[] = [];
    selectedAddOns.value.forEach(id => {
        const ao = customizingItem.value.add_ons?.find((a: any) => a.id === id);
        if (ao) {
            additionalPrice += Number(ao.price);
            addOns.push(ao);
        }
    });

    const variantKey = variants.map(v => v.id).sort().join(',');
    const addOnKey = addOns.map(a => a.id).sort().join(',');
    const noteKey = customizingNotes.value.trim();
    const customizationHash = `${customizingItem.value.id}-${variantKey}-${addOnKey}-${noteKey}`;

    const existing = cart.value.find(i => i.customizationHash === customizationHash);

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
            cartId: Date.now() + Math.random()
        });
    }

    showCustomization.value = false;
    customizingItem.value = null;
};

const scanQuery = ref('');
const isScanning = ref(false);
const scanStatus = ref<{ message: string; type: 'success' | 'error' } | null>(null);
let scanStatusTimeout: any = null;

const playBeep = (type: 'success' | 'error' = 'success') => {
    try {
        const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        
        if (type === 'success') {
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
        console.error('Audio beep failed', e);
    }
};

const setScanStatus = (message: string, type: 'success' | 'error') => {
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
    if (!sku) return;
    
    isScanning.value = true;
    try {
        const res = await fetch(`/api/inventory-items?search=${encodeURIComponent(sku)}`, {
            headers: { Accept: 'application/json' }
        });
        
        if (res.ok) {
            const data = await res.json();
            const items = data.data || [];
            
            const invItem = items.find((i: any) => i.sku && i.sku.toLowerCase() === sku.toLowerCase());
            
            if (invItem) {
                let menuItem = menuItems.value.find((m: any) => m.inventory_item_id === invItem.id);
                
                if (!menuItem) {
                    menuItem = packages.value.find((p: any) => p.inventory_item_id === invItem.id);
                }
                
                if (menuItem) {
                    const isPkg = Boolean(menuItem.package_items);
                    const customizationHash = `${menuItem.id}---`;
                    const existing = cart.value.find(i => i.customizationHash === customizationHash);
                    
                    if (existing) {
                        existing.qty += 1;
                    } else {
                        cart.value.push({
                            ...menuItem,
                            isPackage: isPkg,
                            price: Number(menuItem.price),
                            originalPrice: Number(menuItem.price),
                            qty: 1,
                            notes: '',
                            customized: false,
                            customizationHash,
                            variants: [],
                            add_ons: [],
                            cartId: Date.now() + Math.random()
                        });
                    }
                    scanQuery.value = '';
                    setScanStatus(`Berhasil scan: ${menuItem.name}`, 'success');
                    playBeep('success');
                } else {
                    scanQuery.value = '';
                    setScanStatus(`Barang "${invItem.name}" belum terhubung ke Menu`, 'error');
                    playBeep('error');
                }
            } else {
                scanQuery.value = '';
                setScanStatus(`Barcode/SKU tidak terdaftar: ${sku}`, 'error');
                playBeep('error');
            }
        } else {
            scanQuery.value = '';
            setScanStatus('Gagal membaca data dari server', 'error');
            playBeep('error');
        }
    } catch (e) {
        console.error('Error scanning barcode', e);
        scanQuery.value = '';
        setScanStatus('Terjadi kesalahan koneksi scanner', 'error');
        playBeep('error');
    } finally {
        isScanning.value = false;
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
        status: item.status
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
    if (order.payment_method === 'cash' || order.payment_method === 'transfer') {
        paymentMethod.value = order.payment_method;
        cartStep.value = 3;
        return;
    }
    
    // If it is QRIS or Bank Transfer, call payment API to show popup directly
    isSubmitting.value = true;
    try {
        paymentMethod.value = order.payment_method;
        const res = await fetch(`/api/orders/${order.id}/pay`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            },
            body: JSON.stringify({
                payment_method: order.payment_method,
                discount: discount.value
            })
        });

        if (res.ok) {
            const data = await res.json();
            if (data.qr_url || data.va_number) {
                qrUrl.value = data.qr_url || '';
                vaNumber.value = data.va_number || '';
                vaBank.value = data.va_bank || '';
                requestedBank.value = data.requested_bank || data.order?.payment_metadata?.requested_bank || '';
                activeOrderNumber.value = data.order?.order_number || data.order_number || order.order_number || '';
                activeOrderFinalAmount.value = data.order?.final_amount || data.final_amount || order.final_amount || 0;
                qrModalOpen.value = true;
                startPolling(order.id);
            } else {
                alert('Pembayaran Berhasil!');
                resetPOS();
            }
        } else {
            const errData = await res.json();
            alert(errData.message || 'Gagal memproses pembayaran.');
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan koneksi');
    } finally {
        isSubmitting.value = false;
    }
};

const cancelExistingItem = async (item: any, idx: number) => {
    if (!confirm('Batalkan item ini?')) return;
    
    try {
        const res = await fetch(`/api/order-items/${item.id}/cancel`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            }
        });
        
        if (res.ok) {
            const data = await res.json();
            // Update cart items with new totals if needed
            item.status = 'cancelled';
            alert('Item berhasil dibatalkan');
        }
    } catch (e) { console.error(e); }
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
    qrUrl.value = '';
    vaNumber.value = '';
    vaBank.value = '';
    fetchOpenBills();
};

const holdOrder = async () => {
    if (cart.value.length === 0) return;
    if (orderType.value === 'dine_in' && !selectedTable.value) {
        alert('Pilih meja dulu');
        return;
    }

    isSubmitting.value = true;
    try {
        const url = activeOrderId.value ? `/api/orders/${activeOrderId.value}/items` : '/api/orders';
        const method = 'POST';
        
        // Only send NEW items if updating
        const itemsToSend = activeOrderId.value 
            ? cart.value.filter(i => !i.isExisting) 
            : cart.value;

        if (activeOrderId.value && itemsToSend.length === 0) {
            alert('Tidak ada item baru untuk disimpan');
            isSubmitting.value = false;
            return;
        }

        const body: any = {
            items: itemsToSend.map(i => ({
                menu_item_id: i.isPackage ? null : i.id,
                package_id: i.isPackage ? i.id : null,
                name: i.name,
                qty: i.qty,
                price: i.price,
                variants: i.variants,
                add_ons: i.add_ons,
                notes: i.notes
            }))
        };

        if (!activeOrderId.value) {
            body.customer_id = selectedCustomer.value?.id;
            body.table_id = selectedTable.value;
            body.type = orderType.value;
            body.payment_method = 'cash'; // placeholder
            body.payment_status = 'unpaid';
            body.discount = discount.value;
            body.tax = tax.value;
        }

        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            },
            body: JSON.stringify(body)
        });

        if (res.ok) {
            alert('Pesanan dikirim ke dapur!');
            resetPOS();
        }
    } catch (e) { console.error(e); }
    finally { isSubmitting.value = false; }
};

const handleCheckout = async () => {
    if (cart.value.length === 0) return;
    if (paymentMethod.value === 'cash' && amountPaid.value < total.value) {
        alert('Jumlah bayar kurang');
        return;
    }

    isSubmitting.value = true;
    try {
        // If it's a new order being paid immediately
        if (!activeOrderId.value) {
            const res = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
                },
                body: JSON.stringify({
                    customer_id: selectedCustomer.value?.id,
                    table_id: selectedTable.value,
                    type: orderType.value,
                    payment_method: paymentMethod.value,
                    payment_status: 'paid',
                    discount: discount.value,
                    tax: tax.value,
                    bank: paymentMethod.value === 'transfer' ? selectedBank.value : null,
                    items: cart.value.map(i => ({
                        menu_item_id: i.isPackage ? null : i.id,
                        package_id: i.isPackage ? i.id : null,
                        name: i.name,
                        qty: i.qty,
                        price: i.price,
                        variants: i.variants,
                        add_ons: i.add_ons,
                        notes: i.notes
                    }))
                })
            });

            if (res.ok) {
                const data = await res.json();
                if (data.qr_url || data.va_number) {
                    qrUrl.value = data.qr_url || '';
                    vaNumber.value = data.va_number || '';
                    vaBank.value = data.va_bank || '';
                    requestedBank.value = data.requested_bank || '';
                    activeOrderNumber.value = data.order_number;
                    activeOrderFinalAmount.value = data.final_amount;
                    qrModalOpen.value = true;
                    startPolling(data.id);
                } else {
                    alert('Pembayaran Berhasil!');
                    resetPOS();
                }
            }
        } else {
            // Processing payment for an EXISTING order
            const res = await fetch(`/api/orders/${activeOrderId.value}/pay`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
                },
                body: JSON.stringify({
                    payment_method: paymentMethod.value,
                    discount: discount.value,
                    bank: paymentMethod.value === 'transfer' ? selectedBank.value : null
                })
            });

            if (res.ok) {
                const data = await res.json();
                if (data.qr_url || data.va_number) {
                    qrUrl.value = data.qr_url || '';
                    vaNumber.value = data.va_number || '';
                    vaBank.value = data.va_bank || '';
                    requestedBank.value = data.requested_bank || data.order?.payment_metadata?.requested_bank || '';
                    activeOrderNumber.value = data.order?.order_number || data.order_number || '';
                    activeOrderFinalAmount.value = data.order?.final_amount || data.final_amount || 0;
                    qrModalOpen.value = true;
                    startPolling(activeOrderId.value || data.order?.id || data.id);
                } else {
                    alert('Pembayaran Berhasil!');
                    resetPOS();
                }
            }
        }
    } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan koneksi');
    } finally {
        isSubmitting.value = false;
    }
};

const formatIDR = (val: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};
</script>

<template>
    <div class="flex flex-col h-screen w-full bg-brand-50 overflow-hidden">
        <!--  HEADER -->
        <header class="h-16 bg-white border-b border-brand-100 flex items-center justify-between px-6 shrink-0 z-20">
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 rounded-xl bg-brand-600 flex items-center justify-center text-white shadow-lg shadow-brand-100">
                    <ShoppingBag class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-lg font-black text-slate-800 leading-none">Toffeeman Cashier</h1>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-brand-500">POS Terminal</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <Button @click="showBills = true" variant="outline" class="border-brand-200 text-brand-600 font-black hover:bg-brand-50 gap-2 rounded-xl h-10 px-6">
                    <Receipt class="h-4 w-4" />
                    Open Bills
                    <Badge v-if="openBills.length" class="ml-1 bg-brand-600 border-none h-5 w-5 p-0 flex items-center justify-center text-[10px]">{{ openBills.length }}</Badge>
                </Button>
                <div class="h-8 w-[1px] bg-slate-100 mx-2"></div>
                <Button @click="resetPOS" variant="ghost" class="text-slate-400 hover:text-red-500 font-bold">Reset</Button>
            </div>
        </header>

        <!-- MAIN PANELS -->
        <div class="flex flex-1 overflow-hidden">
            <!-- 🛒 LEFT PANEL: Product Selection -->
            <div class="flex-1 flex flex-col bg-white border-r border-brand-100 overflow-hidden">
                <div v-if="!showCustomization && !showDetail" class="flex flex-col h-full animate-in fade-in duration-300">
                    <!-- Catalog Toolbar -->
                    <div class="px-6 py-3 border-b border-brand-50 bg-white sticky top-0 z-10">
                        <div class="flex flex-col gap-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex p-0.5 bg-brand-50 rounded-lg w-fit">
                                        <button @click="activeCategory = 'all'" class="px-4 py-1.5 rounded-md text-[11px] font-black transition-all" :class="activeCategory !== 'packages' ? 'bg-brand-600 text-white shadow-sm' : 'text-brand-400 hover:text-brand-600'">MENU</button>
                                        <button @click="activeCategory = 'packages'" class="px-4 py-1.5 rounded-md text-[11px] font-black transition-all" :class="activeCategory === 'packages' ? 'bg-brand-600 text-white shadow-sm' : 'text-brand-400 hover:text-brand-600'">PAKET</button>
                                    </div>
                                    <div class="flex p-0.5 bg-slate-100 rounded-lg shrink-0">
                                        <button @click="viewMode = 'grid'" class="h-7 w-7 rounded-md flex items-center justify-center transition-all" :class="viewMode === 'grid' ? 'bg-white text-brand-600 shadow-sm' : 'text-slate-400 hover:text-slate-600'"><LayoutGrid class="h-3.5 w-3.5" /></button>
                                        <button @click="viewMode = 'list'" class="h-7 w-7 rounded-md flex items-center justify-center transition-all" :class="viewMode === 'list' ? 'bg-white text-brand-600 shadow-sm' : 'text-slate-400 hover:text-slate-600'"><List class="h-3.5 w-3.5" /></button>
                                    </div>
                                </div>
                                <div class="flex flex-1 sm:flex-initial items-center gap-2">
                                    <div class="relative flex-1 sm:w-48">
                                        <Barcode class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-brand-300" />
                                        <Input v-model="scanQuery" @keydown.enter="handleBarcodeScan" placeholder="Scan Barcode / SKU..." class="pl-8 rounded-lg border-brand-100 focus-visible:ring-brand-500 h-8 bg-white font-bold text-xs" :disabled="isScanning" />
                                        <div v-if="scanStatus" class="absolute left-0 top-[34px] z-50 text-[10px] font-bold px-1.5 py-0.5 rounded shadow-md leading-tight transition-all animate-in fade-in" :class="scanStatus.type === 'success' ? 'text-emerald-600 bg-emerald-50 border border-emerald-200' : 'text-red-600 bg-red-50 border border-red-200'">
                                            {{ scanStatus.message }}
                                        </div>
                                    </div>
                                    <div class="relative flex-1 sm:w-48">
                                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-brand-300" />
                                        <Input v-model="searchQuery" placeholder="Cari..." class="pl-8 rounded-lg border-brand-100 focus-visible:ring-brand-500 h-8 bg-white font-bold text-xs" />
                                    </div>
                                </div>
                            </div>
                            <div v-if="activeCategory !== 'packages'" class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar animate-in slide-in-from-top-2 duration-300">
                                <button @click="activeCategory = 'all'" class="px-3 py-1 rounded-full text-[9px] font-bold border transition-all shrink-0" :class="activeCategory === 'all' ? 'bg-brand-50 border-brand-600 text-brand-600' : 'border-slate-100 text-slate-400 hover:border-brand-200'">Semua Kategori</button>
                                <button v-for="cat in categories" :key="cat.id" @click="activeCategory = cat.id" class="px-3 py-1 rounded-full text-[9px] font-bold border transition-all shrink-0" :class="activeCategory === cat.id ? 'bg-brand-50 border-brand-600 text-brand-600' : 'border-slate-100 text-slate-400 hover:border-brand-200'">{{ cat.name }}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Menu Display -->
                    <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
                        <div v-if="loading" class="flex items-center justify-center h-full"><Loader2 class="h-8 w-8 animate-spin text-brand-600" /></div>
                        <div v-else-if="filteredItems.length === 0" class="flex flex-col items-center justify-center h-full text-brand-300"><ShoppingBag class="h-16 w-16 mb-4 opacity-20" /><p class="font-bold">Menu tidak ditemukan</p></div>
                        
                        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 animate-in fade-in duration-500">
                            <div v-for="item in filteredItems" :key="item.id" @click="addToCart(item, activeCategory === 'packages')" @contextmenu.prevent="openDetail(item, activeCategory === 'packages')" class="bg-white rounded-2xl border-2 border-brand-50 p-3 hover:shadow-xl hover:shadow-brand-100/50 hover:border-brand-300 cursor-pointer transition-all group active:scale-95 relative">
                                <button @click.stop="openDetail(item, activeCategory === 'packages')" class="absolute top-2 right-2 z-10 h-7 w-7 rounded-full bg-white/80 backdrop-blur-sm border border-slate-200 flex items-center justify-center text-slate-400 hover:text-brand-600 hover:border-brand-300 transition-all opacity-0 group-hover:opacity-100 shadow-sm"><Info class="h-4 w-4" /></button>
                                <div class="aspect-square rounded-xl bg-brand-50 overflow-hidden mb-3 relative">
                                    <img v-if="item.image_url" :src="item.image_url" class="h-full w-full object-cover" />
                                    <div v-else class="h-full w-full flex items-center justify-center text-brand-200"><Utensils class="h-10 w-10" /></div>
                                </div>
                                <div class="px-1">
                                    <p class="text-[9px] font-bold text-brand-400 uppercase tracking-tighter">{{ item.category?.name || 'Paket' }}</p>
                                    <h3 class="text-xs font-black text-slate-800 leading-tight mb-1 truncate uppercase">{{ item.name }}</h3>
                                    <p class="text-xs font-bold text-brand-600">{{ formatIDR(Number(item.price)) }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-2 animate-in fade-in slide-in-from-bottom-4 duration-500">
                            <div v-for="item in filteredItems" :key="item.id" @click="addToCart(item, activeCategory === 'packages')" class="bg-white rounded-xl border-2 border-brand-50 p-4 hover:border-brand-300 cursor-pointer transition-all flex items-center gap-4 group active:scale-[0.99] relative">
                                <div class="h-12 w-12 rounded-lg bg-brand-50 overflow-hidden shrink-0 border border-slate-100">
                                    <img v-if="item.image_url" :src="item.image_url" class="h-full w-full object-cover" />
                                    <div v-else class="h-full w-full flex items-center justify-center text-brand-200"><Utensils class="h-6 w-6" /></div>
                                </div>
                                <div class="flex-1 min-w-0"><h3 class="text-sm font-black text-slate-800 truncate uppercase">{{ item.name }}</h3><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ item.category?.name || 'Paket' }}</p></div>
                                <div class="text-right shrink-0 px-4 border-r border-slate-100"><p class="text-sm font-black text-brand-600">{{ formatIDR(Number(item.price)) }}</p></div>
                                <div class="flex items-center gap-2 pl-4">
                                    <button @click.stop="openDetail(item, activeCategory === 'packages')" class="h-10 w-10 rounded-xl bg-slate-50 text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-all flex items-center justify-center border border-slate-100"><Info class="h-5 w-5" /></button>
                                    <div class="h-10 w-10 rounded-xl bg-brand-600 text-white flex items-center justify-center shadow-lg shadow-brand-100 group-hover:scale-110 transition-all"><Plus class="h-5 w-5" /></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🎨 PRODUCT DETAIL / CUSTOMIZATION (Unified) -->
                <div v-else-if="showCustomization || showDetail" class="flex flex-col h-full animate-in slide-in-from-left-8 fade-in duration-300 p-8">
                    <div class="flex items-center justify-between mb-8 pb-6 border-b border-brand-100">
                        <div class="flex items-center gap-4">
                            <Button variant="ghost" size="icon" @click="showCustomization = false; showDetail = false" class="rounded-xl bg-brand-50 text-brand-600"><ArrowLeft class="h-5 w-5" /></Button>
                            <div>
                                <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">{{ (customizingItem || detailItem)?.name }}</h2>
                                <p class="text-xs font-bold text-brand-500 uppercase tracking-widest">{{ showCustomization ? 'Varian & Topping' : 'Informasi Produk' }}</p>
                            </div>
                        </div>

                        <!-- Action Bar in Header (for Customization) -->
                        <div v-if="showCustomization" class="flex items-center gap-8 animate-in fade-in zoom-in-95 duration-500">
                            <div class="text-right">
                                <p class="text-[10px] font-bold text-brand-400 uppercase tracking-widest">Total Harga Item</p>
                                <p class="text-2xl font-black text-slate-800">{{ formatIDR(customizingItemTotalPrice) }}</p>
                            </div>
                            <Button @click="addCustomizedToCart" class="rounded-xl h-14 px-10 bg-brand-600 hover:bg-brand-700 text-white font-black shadow-lg shadow-brand-100 uppercase tracking-wider">Tambah</Button>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto pr-4 custom-scrollbar">
                        <!-- Customization View (No Image/Desc for more space) -->
                        <div v-if="showCustomization" class="space-y-8 animate-in fade-in slide-in-from-right-4 duration-500">
                            <div class="grid lg:grid-cols-2 gap-8">
                                <div v-for="opt in customizingItem?.options" :key="opt.id" class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-black text-slate-700 flex items-center gap-2 uppercase tracking-wider text-xs">
                                            {{ opt.name }}<Badge v-if="opt.is_required" variant="outline" class="text-[9px] bg-brand-50 text-brand-600 border-brand-100">Wajib</Badge>
                                        </h3>
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">{{ opt.type === 'multiple' ? 'Pilih Banyak' : 'Pilih Satu' }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button v-for="val in opt.values" :key="val.id" @click="toggleOption(opt, val)" class="p-4 rounded-xl border-2 text-left transition-all relative overflow-hidden group" :class="isOptionSelected(opt, val.id) ? 'border-brand-600 bg-brand-50 shadow-sm' : 'border-slate-300 hover:border-brand-200 bg-white'">
                                            <div class="relative z-10">
                                                <p class="text-xs font-black text-slate-700">{{ val.name }}</p>
                                                <p class="text-[10px] font-bold text-brand-500" v-if="val.additional_price > 0">+ {{ formatIDR(val.additional_price) }}</p>
                                            </div>
                                            <Check v-if="isOptionSelected(opt, val.id)" class="absolute top-2 right-2 h-4 w-4 text-brand-600" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="customizingItem?.add_ons?.length" class="space-y-4">
                                    <h3 class="font-black text-slate-700 flex items-center gap-2 uppercase tracking-wider text-xs">
                                        Add-ons <Badge variant="outline" class="text-[9px] bg-blue-50 text-blue-600 border-blue-100">Opsional</Badge>
                                    </h3>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button v-for="ao in customizingItem.add_ons" :key="ao.id" @click="toggleAddOn(ao.id)" class="p-4 rounded-xl border-2 text-left transition-all relative overflow-hidden group" :class="selectedAddOns.includes(ao.id) ? 'border-brand-600 bg-brand-50 shadow-sm' : 'border-slate-300 hover:border-brand-200 bg-white'">
                                            <div class="relative z-10">
                                                <p class="text-xs font-black text-slate-700">{{ ao.name }}</p>
                                                <p class="text-[10px] font-bold text-brand-500">+ {{ formatIDR(ao.price) }}</p>
                                            </div>
                                            <Check v-if="selectedAddOns.includes(ao.id)" class="absolute top-2 right-2 h-4 w-4 text-brand-600" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4 bg-slate-50/50 p-8 rounded-3xl border border-slate-100">
                                <h3 class="font-black text-slate-700 uppercase tracking-wider text-xs">Jumlah & Catatan</h3>
                                <div class="flex items-center gap-8">
                                    <div class="flex items-center bg-white border-2 border-brand-100 rounded-2xl p-1 shadow-sm">
                                        <button @click="customizingQty > 1 ? customizingQty-- : null" class="h-12 w-12 rounded-xl flex items-center justify-center hover:bg-brand-50 text-brand-600"><Minus class="h-5 w-5" /></button>
                                        <span class="w-16 text-center text-xl font-black text-slate-800">{{ customizingQty }}</span>
                                        <button @click="customizingQty++" class="h-12 w-12 rounded-xl flex items-center justify-center hover:bg-brand-50 text-brand-600"><Plus class="h-5 w-5" /></button>
                                    </div>
                                    <textarea v-model="customizingNotes" placeholder="Catatan khusus (misal: kurangi gula, tanpa es)..." class="flex-1 h-14 rounded-2xl border-2 border-slate-100 bg-white px-6 py-4 text-sm font-medium focus:outline-none focus:border-brand-300 transition-all resize-none"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Product Detail View (Keep Image/Desc) -->
                        <div v-if="showDetail" class="grid lg:grid-cols-2 gap-12">
                            <!-- Image & Desc -->
                            <div class="space-y-6">
                                <div class="aspect-video rounded-3xl bg-brand-50 overflow-hidden border-2 border-slate-100 shadow-inner">
                                    <img v-if="detailItem?.image_url" :src="detailItem?.image_url" class="h-full w-full object-cover" />
                                    <div v-else class="h-full w-full flex items-center justify-center text-brand-200"><Utensils class="h-20 w-20" /></div>
                                </div>
                                <div class="space-y-4">
                                    <h3 class="font-black text-slate-700 uppercase tracking-wider text-xs">Deskripsi</h3>
                                    <p class="text-sm text-slate-500 leading-relaxed bg-slate-50 p-6 rounded-2xl border border-slate-100 italic">
                                        {{ detailItem?.description || 'Tidak ada deskripsi untuk produk ini.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Product Info UI -->
                            <div class="space-y-8 animate-in fade-in slide-in-from-right-4 duration-500">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-brand-50 p-4 rounded-2xl border border-brand-100 flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white flex items-center justify-center text-brand-600 shadow-sm"><Flame class="h-5 w-5" /></div>
                                        <div>
                                            <p class="text-[10px] font-bold text-brand-400 uppercase tracking-widest">Energi</p>
                                            <p class="text-sm font-black text-slate-700">{{ detailItem?.calories || 0 }} kcal</p>
                                        </div>
                                    </div>
                                    <div class="bg-brand-50 p-4 rounded-2xl border border-brand-100 flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white flex items-center justify-center text-brand-600 shadow-sm"><LayoutGrid class="h-5 w-5" /></div>
                                        <div>
                                            <p class="text-[10px] font-bold text-brand-400 uppercase tracking-widest">Kategori</p>
                                            <p class="text-sm font-black text-slate-700 uppercase">{{ detailItem?.category?.name || 'Paket' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="detailItem?.allergens?.length" class="space-y-3">
                                    <h3 class="font-black text-slate-700 uppercase tracking-wider text-xs">Allergen</h3>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge v-for="allergen in detailItem.allergens" :key="allergen" variant="outline" class="rounded-lg bg-red-50 text-red-600 border-red-100 px-3 py-1 font-bold text-[10px] uppercase">{{ allergen }}</Badge>
                                    </div>
                                </div>
                                
                                <div v-if="detailItem?.tags?.length" class="space-y-3">
                                    <h3 class="font-black text-slate-700 uppercase tracking-wider text-xs">Tags</h3>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge v-for="tag in detailItem.tags" :key="tag" variant="secondary" class="rounded-lg bg-slate-100 text-slate-600 border-none px-3 py-1 font-bold text-[10px] uppercase">{{ tag }}</Badge>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-6 pt-4">
                                    <div class="flex flex-col">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Harga Dasar</p>
                                        <p class="text-2xl font-black text-slate-800">{{ formatIDR(detailItem?.price || 0) }}</p>
                                    </div>
                                    <Button @click="addToCart(detailItem, detailItem?.isPackage)" class="flex-1 bg-brand-600 text-white hover:bg-brand-700 font-black rounded-xl h-14 shadow-lg shadow-brand-100 uppercase tracking-wider">Tambah</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🛒 RIGHT PANEL: Cart & Checkout -->
            <div class="w-[450px] bg-white flex flex-col overflow-hidden border-l border-brand-100 shadow-2xl relative z-10">
                <div class="p-8 flex-1 flex flex-col overflow-hidden">
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center gap-3">
                            <Button v-if="cartStep > 1" variant="ghost" size="icon" @click="cartStep--" class="h-8 w-8 rounded-full bg-brand-50 text-brand-600"><ArrowLeft class="h-4 w-4" /></Button>
                            <h2 class="text-lg font-black text-slate-800 flex items-center gap-3">
                                <ShoppingCart v-if="cartStep === 1" class="h-5 w-5 text-brand-600" />
                                <UserIcon v-else-if="cartStep === 2" class="h-5 w-5 text-brand-600" />
                                <CreditCard v-else-if="cartStep === 3" class="h-5 w-5 text-brand-600" />
                                <Check v-else class="h-5 w-5 text-brand-600" />
                                {{ cartStep === 1 ? 'Isi Keranjang' : (cartStep === 2 ? 'Data Order' : (cartStep === 3 ? 'Pembayaran' : 'Konfirmasi Akhir')) }}
                            </h2>
                        </div>
                        <Badge variant="outline" class="rounded-full px-3 py-0.5 bg-brand-50 text-brand-600 border-brand-100 text-[10px] font-black uppercase">{{ cart.length }} Item</Badge>
                    </div>

                    <!-- STEP 1: CART ITEMS -->
                    <div v-if="cartStep === 1" class="flex-1 flex flex-col overflow-hidden animate-in fade-in duration-300">
                        <div v-if="activeOrderId" class="mb-6 p-4 bg-brand-900 text-white rounded-2xl flex items-center justify-between shadow-lg">
                            <div class="flex items-center gap-3">
                                <Receipt class="h-5 w-5 text-brand-400" />
                                <div>
                                    <p class="text-[9px] font-bold text-brand-300 uppercase tracking-widest">Updating Order</p>
                                    <p class="text-xs font-black">#{{ openBills.find(b => b.id === activeOrderId)?.order_number.slice(-8) }}</p>
                                </div>
                            </div>
                            <Button @click="activeOrderId = null; cart = []" variant="ghost" size="sm" class="text-white/60 hover:text-white h-8"><X class="h-4 w-4" /></Button>
                        </div>

                        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar mb-8">
                            <div v-if="cart.length === 0" class="flex flex-col items-center justify-center h-full text-brand-200 opacity-40"><ShoppingCart class="h-16 w-16 mb-4" /><p class="font-bold text-slate-400 text-sm italic">Keranjang belanja kosong...</p></div>
                            <div v-else class="space-y-5">
                                <div v-for="(item, idx) in cart" :key="item.cartId || item.id" class="flex gap-4 group animate-in slide-in-from-right-4 duration-300">
                                    <div class="h-14 w-14 rounded-xl bg-brand-50 flex-shrink-0 overflow-hidden relative">
                                        <img v-if="item.image_url" :src="item.image_url" class="h-full w-full object-cover" />
                                        <div v-else class="h-full w-full flex items-center justify-center text-brand-200"><Utensils class="h-5 w-5" /></div>
                                        <div v-if="item.isExisting" class="absolute inset-0 bg-slate-900/40 flex items-center justify-center"><Check class="text-white h-6 w-6" /></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start mb-1">
                                            <h4 class="text-xs font-black text-slate-700 truncate pr-2 uppercase" :class="item.status === 'cancelled' ? 'line-through opacity-40' : ''">{{ item.name }}</h4>
                                            <button @click="item.isExisting ? cancelExistingItem(item, idx) : removeFromCart(idx)" class="text-brand-300 hover:text-red-500 transition-colors"><X class="h-3.5 w-3.5" /></button>
                                        </div>
                                        <p class="text-[11px] font-bold text-brand-600 mb-2">{{ formatIDR(item.price) }} <Badge v-if="item.isExisting" variant="outline" class="text-[8px] ml-2 border-brand-200 text-brand-400">Stored</Badge></p>
                                        <div class="flex items-center gap-3">
                                            <div v-if="!item.isExisting" class="flex items-center bg-brand-50 rounded-lg p-0.5">
                                                <button @click="item.qty > 1 ? item.qty-- : removeFromCart(idx)" class="h-6 w-6 rounded flex items-center justify-center hover:bg-white text-brand-600 transition-all shadow-sm"><Minus class="h-2.5 w-2.5" /></button>
                                                <span class="w-8 text-center text-xs font-black text-slate-700">{{ item.qty }}</span>
                                                <button @click="item.qty++" class="h-6 w-6 rounded flex items-center justify-center hover:bg-white text-brand-600 transition-all shadow-sm"><Plus class="h-2.5 w-2.5" /></button>
                                            </div>
                                            <span v-else class="text-xs font-black text-slate-400">Qty: {{ item.qty }}</span>
                                            <span class="text-xs font-black text-slate-800 ml-auto" :class="item.status === 'cancelled' ? 'line-through text-slate-300' : ''">{{ formatIDR(item.price * item.qty) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-6 border-t border-brand-50">
                            <div class="bg-slate-50 rounded-2xl p-5 space-y-2 mb-6">
                                <div class="flex justify-between text-[10px] font-bold text-slate-500"><span>Subtotal</span><span>{{ formatIDR(subtotal) }}</span></div>
                                <div class="flex justify-between text-base font-black text-slate-800"><span>Total Tagihan</span><span class="text-brand-600">{{ formatIDR(total) }}</span></div>
                            </div>
                            <Button @click="cartStep = 2" :disabled="cart.length === 0" class="w-full h-14 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-base font-black shadow-lg shadow-brand-100 active:scale-[0.98] transition-all flex items-center justify-between px-6">
                                <span>LANJUT DATA ORDER</span><ChevronRight class="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <!-- STEP 2: ORDER INFO -->
                    <div v-else-if="cartStep === 2" class="flex-1 flex flex-col overflow-hidden animate-in slide-in-from-right-8 fade-in duration-300">
                        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-6">
                            <div class="space-y-2">
                                <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5"><UserIcon class="h-3 w-3" /> Pilih Pembeli</Label>
                                <select v-model="selectedCustomer" :disabled="activeOrderId !== null" class="w-full h-12 rounded-xl border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 disabled:opacity-50">
                                    <option :value="null">Pembeli Umum</option>
                                    <option v-for="c in customers" :key="c?.id" :value="c">{{ c?.name }}</option>
                                </select>
                            </div>
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5"><ShoppingBag class="h-3 w-3" /> Metode Konsumsi</Label>
                                    <div class="flex p-0.5 bg-brand-50 rounded-xl h-12">
                                        <button @click="orderType = 'dine_in'" :disabled="activeOrderId !== null" class="flex-1 rounded-lg flex items-center justify-center gap-1.5 text-[10px] font-black transition-all" :class="orderType === 'dine_in' ? 'bg-white text-brand-600 shadow-sm' : 'text-brand-300'">Dine In</button>
                                        <button @click="orderType = 'take_away'" :disabled="activeOrderId !== null" class="flex-1 rounded-lg flex items-center justify-center gap-1.5 text-[10px] font-black transition-all" :class="orderType === 'take_away' ? 'bg-white text-brand-600 shadow-sm' : 'text-brand-300'">Take Away</button>
                                    </div>
                                </div>
                                <div v-if="orderType === 'dine_in'" class="space-y-2 animate-in fade-in zoom-in-95 duration-300">
                                    <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5"><TableIcon class="h-3 w-3" /> Meja</Label>
                                    <div class="grid grid-cols-4 gap-2">
                                        <button v-for="t in tables" :key="t.id" @click="selectedTable = t.id" :disabled="activeOrderId !== null" class="h-10 rounded-xl border-2 text-xs font-black transition-all flex items-center justify-center" :class="selectedTable === t.id ? 'border-brand-600 bg-brand-50 text-brand-600' : 'border-slate-100 text-slate-500 hover:border-brand-200 disabled:opacity-50'">{{ t.number }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-6 border-t border-brand-50 grid grid-cols-2 gap-3">
                            <Button @click="holdOrder" :disabled="isSubmitting" variant="outline" class="h-14 rounded-xl border-brand-200 text-brand-600 font-black gap-2 uppercase text-[10px] tracking-widest">
                                <ChefHat class="h-4 w-4" /> SIMPAN & DAPUR
                            </Button>
                            <Button @click="cartStep = 3" :disabled="orderType === 'dine_in' && !selectedTable" class="h-14 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-black flex items-center justify-between px-4 uppercase text-[10px] tracking-widest">
                                <span>BAYAR</span><ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- STEP 3: PAYMENT INPUT -->
                    <div v-else-if="cartStep === 3" class="flex-1 flex flex-col overflow-hidden animate-in slide-in-from-right-8 fade-in duration-300">
                        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-8">
                            <div class="bg-slate-900 text-white rounded-3xl p-8 space-y-2 shadow-xl"><p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total Tagihan</p><h3 class="text-4xl font-black">{{ formatIDR(total) }}</h3></div>
                            <div class="space-y-4">
                                <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Metode Pembayaran</Label>
                                <div class="flex gap-3">
                                    <button @click="paymentMethod = 'cash'" class="flex-1 flex flex-col items-center justify-center p-4 rounded-2xl border-2 transition-all" :class="paymentMethod === 'cash' ? 'border-brand-600 bg-brand-50 text-brand-600 shadow-sm' : 'border-slate-100 text-slate-400 hover:border-brand-100'"><Banknote class="h-6 w-6 mb-2" /><span class="text-[11px] font-black uppercase tracking-tighter">Tunai</span></button>
                                    <button @click="paymentMethod = 'qris'" class="flex-1 flex flex-col items-center justify-center p-4 rounded-2xl border-2 transition-all" :class="paymentMethod === 'qris' ? 'border-brand-600 bg-brand-50 text-brand-600 shadow-sm' : 'border-slate-100 text-slate-400 hover:border-brand-100'"><CreditCard class="h-6 w-6 mb-2" /><span class="text-[11px] font-black uppercase tracking-tighter">QRIS</span></button>
                                    <button @click="paymentMethod = 'transfer'" class="flex-1 flex flex-col items-center justify-center p-4 rounded-2xl border-2 transition-all" :class="paymentMethod === 'transfer' ? 'border-brand-600 bg-brand-50 text-brand-600 shadow-sm' : 'border-slate-100 text-slate-400 hover:border-brand-100'"><Landmark class="h-6 w-6 mb-2" /><span class="text-[11px] font-black uppercase tracking-tighter">Bank</span></button>
                                </div>
                                <div v-if="paymentMethod === 'cash'" class="space-y-4 bg-brand-50/50 p-6 rounded-3xl border border-brand-100 animate-in slide-in-from-bottom-2 duration-300">
                                    <div class="space-y-2"><Label class="text-[9px] font-black text-brand-400 uppercase tracking-widest">Uang Diterima</Label><Input type="number" v-model="amountPaid" class="h-14 rounded-2xl border-brand-100 focus-visible:ring-brand-500 font-black text-2xl bg-white px-6" placeholder="0" /></div>
                                    <div class="flex justify-between items-center bg-white p-4 rounded-2xl border border-slate-100 shadow-sm"><Label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Kembalian</Label><div class="font-black text-emerald-600 text-xl">{{ formatIDR(change) }}</div></div>
                                </div>
                                <div v-if="paymentMethod === 'transfer'" class="space-y-4 bg-brand-50/50 p-6 rounded-3xl border border-brand-100 animate-in slide-in-from-bottom-2 duration-300">
                                    <Label class="text-[9px] font-black text-brand-400 uppercase tracking-widest block mb-1">Pilih Bank Transfer VA</Label>
                                    <div class="grid grid-cols-2 gap-2 max-h-[180px] overflow-y-auto pr-1.5 custom-scrollbar">
                                        <button v-for="b in banksList" :key="b.id" @click="selectedBank = b.id" type="button" class="py-2.5 px-3 rounded-xl border text-[11px] font-black transition-all text-left flex items-center justify-between" :class="selectedBank === b.id ? 'border-brand-600 bg-brand-100/50 text-brand-700 font-extrabold shadow-sm' : 'border-slate-200 bg-white text-slate-500 hover:border-brand-200 hover:bg-slate-50'">
                                            <span>{{ b.name }}</span>
                                            <span v-if="selectedBank === b.id" class="w-1.5 h-1.5 rounded-full bg-brand-600"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-6 border-t border-brand-50"><Button @click="cartStep = 4" :disabled="paymentMethod === 'cash' && amountPaid < total" class="w-full h-14 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-base font-black shadow-lg shadow-brand-100 active:scale-[0.98] transition-all flex items-center justify-between px-6 uppercase text-xs tracking-widest"><span>REVIEW AKHIR</span><ChevronRight class="h-5 w-5" /></Button></div>
                    </div>

                    <!-- STEP 4: FINAL CONFIRMATION -->
                    <div v-else class="flex-1 flex flex-col overflow-hidden animate-in slide-in-from-right-8 fade-in duration-300">
                        <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar space-y-6">
                            <div class="bg-brand-50 p-6 rounded-3xl border border-brand-100 space-y-4">
                                <h3 class="font-black text-brand-800 text-center uppercase tracking-widest text-sm">Konfirmasi Pembayaran</h3>
                                <div class="space-y-2 border-y border-brand-200/50 py-4">
                                    <div class="flex justify-between text-xs font-bold text-brand-600"><span>Pembeli</span><span>{{ selectedCustomer?.name || 'Umum' }}</span></div>
                                    <div class="flex justify-between text-xs font-bold text-brand-600"><span>Meja</span><span>{{ selectedTable ? tables.find(t => t.id === selectedTable)?.number : 'Take Away' }}</span></div>
                                    <div class="flex justify-between text-xs font-bold text-brand-600"><span>Pembayaran</span><span class="uppercase">{{ paymentMethod }}</span></div>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs font-bold text-slate-400"><span>Total Belanja</span><span>{{ formatIDR(total) }}</span></div>
                                    <div v-if="paymentMethod === 'cash'" class="flex justify-between text-xs font-bold text-slate-400"><span>Dibayar</span><span>{{ formatIDR(amountPaid) }}</span></div>
                                    <div v-if="paymentMethod === 'cash'" class="flex justify-between text-lg font-black text-emerald-600 pt-2 border-t border-brand-200/50"><span>Kembalian</span><span>{{ formatIDR(change) }}</span></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <Label class="text-[9px] font-black uppercase tracking-widest text-slate-400">Ringkasan Pesanan</Label>
                                <div class="space-y-2">
                                    <div v-for="item in cart" :key="item.cartId || item.id" class="flex justify-between text-[11px] font-bold text-slate-600 bg-white border border-slate-100 p-3 rounded-xl shadow-sm" :class="item.status === 'cancelled' ? 'opacity-30' : ''">
                                        <span>{{ item.qty }}x {{ item.name }}</span>
                                        <span>{{ formatIDR(item.price * item.qty) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-6 border-t border-brand-50">
                            <Button @click="handleCheckout" :disabled="isSubmitting" class="w-full h-16 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-base font-black shadow-lg shadow-brand-100 active:scale-[0.98] transition-all uppercase tracking-widest">
                                <Loader2 v-if="isSubmitting" class="mr-2 h-6 w-6 animate-spin" />
                                <Check v-else class="mr-2 h-6 w-6" />
                                SELESAIKAN PESANAN
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🧾 MODAL: Open Bills -->
        <div v-if="showBills" class="fixed inset-0 z-50 flex items-center justify-center p-8">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showBills = false"></div>
            <div class="relative bg-white w-full max-w-4xl h-[70vh] rounded-[40px] shadow-2xl flex flex-col overflow-hidden animate-in zoom-in-95 duration-300">
                <div class="p-8 bg-slate-900 text-white flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-black uppercase tracking-tight">Open Bills</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Tagihan yang sedang berjalan</p>
                    </div>
                    <Button variant="ghost" @click="showBills = false" class="text-white hover:bg-white/10 h-12 w-12 rounded-full"><X class="h-6 w-6" /></Button>
                </div>
                <div class="flex-1 overflow-y-auto p-8 custom-scrollbar bg-slate-50">
                    <div v-if="openBills.length === 0" class="h-full flex flex-col items-center justify-center text-slate-300">
                        <Receipt class="h-20 w-20 mb-4 opacity-20" />
                        <p class="font-black uppercase tracking-widest">Tidak ada tagihan aktif</p>
                    </div>
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="bill in openBills" :key="bill.id" @click="selectBill(bill)" class="bg-white p-6 rounded-3xl border-2 border-slate-100 shadow-sm hover:border-brand-600 cursor-pointer transition-all group">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <Badge class="bg-brand-50 text-brand-600 border-brand-100 font-black text-[10px] mb-2">{{ bill.order_number }}</Badge>
                                    <h4 class="text-lg font-black text-slate-800 uppercase leading-none">{{ bill.table ? 'Meja ' + bill.table.number : 'Take Away' }}</h4>
                                </div>
                                <p class="text-lg font-black text-brand-600">{{ formatIDR(bill.final_amount) }}</p>
                            </div>
                            <Separator class="bg-slate-50 my-4" />
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                    <ShoppingCart class="h-3 w-3" /> {{ bill.items.length }} Item
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button @click.stop="payBillDirectly(bill)" size="sm" class="bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-[10px] font-black uppercase h-8 px-3">Bayar</Button>
                                    <Button variant="ghost" class="h-8 text-[10px] font-black text-brand-600 uppercase group-hover:bg-brand-50 rounded-lg">Edit &rarr;</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📱 DIALOG: Midtrans QRIS Sandbox Code -->
        <Dialog :open="qrModalOpen" @update:open="val => { qrModalOpen = val; if (!val) { stopPolling(); qrUrl = ''; vaNumber = ''; vaBank = ''; resetPOS(); } }">
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
                        <span class="text-3xl font-black text-brand-600 dark:text-brand-400">{{ formatIDR(activeOrderFinalAmount) }}</span>
                    </div>


                </div>

                <DialogFooter class="border-t pt-4">
                    <Button 
                        @click="() => { qrModalOpen = false; stopPolling(); qrUrl = ''; vaNumber = ''; vaBank = ''; resetPOS(); }" 
                        class="w-full bg-brand-600 hover:bg-brand-700 text-white h-12 rounded-xl font-bold uppercase tracking-wider"
                    >
                        Selesai / Tutup
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
  background: #B45309;
  border-radius: 10px;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
