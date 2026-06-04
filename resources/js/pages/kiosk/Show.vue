<script setup lang="ts">
import { Head, usePoll, router, Link } from '@inertiajs/vue3'
import { Plus, Minus, Trash2, CheckCircle2, History, LogOut, UtensilsCrossed, ShoppingBag, ChevronLeft, Flame, ShieldAlert, Tag, Info, List } from 'lucide-vue-next'
import { ref, computed, onMounted, onUnmounted } from 'vue'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogScrollContent, DialogFooter } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'


const props = defineProps({
    token: String,
    table: Object,
    categories: Array,
    menuItems: Array,
    packages: Array,
    customer: Object,
})

const isMounted = ref(false)
let orderPollInterval: any = null

const guestOrders = ref<any[]>([])
const isGuestHistoryOpen = ref(false)
const isLoadingHistory = ref(false)

onMounted(() => {
    isMounted.value = true
    
    // Check if there is an active order in local storage
    const savedOrder = localStorage.getItem(`kiosk_active_order_${props.token}`)
    if (savedOrder) {
        try {
            const parsedOrder = JSON.parse(savedOrder)
            // Cek apakah order belum selesai/batal, kalau sudah selesai, tak perlu diload lagi sebagai active order
            if (['pending', 'preparing', 'ready', 'served'].includes(parsedOrder.status)) {
                submittedOrder.value = parsedOrder
                startOrderPolling()
            } else {
                localStorage.removeItem(`kiosk_active_order_${props.token}`)
            }
        } catch (e) {
            localStorage.removeItem(`kiosk_active_order_${props.token}`)
        }
    }

    // Load guest order history
    const history = localStorage.getItem(`kiosk_guest_orders_${props.token}`)
    if (history) {
        try {
            guestOrders.value = JSON.parse(history)
        } catch(e) {
            localStorage.removeItem(`kiosk_guest_orders_${props.token}`)
        }
    }
})

onUnmounted(() => {
    if (orderPollInterval) clearInterval(orderPollInterval)
})

function startOrderPolling() {
    if (orderPollInterval) clearInterval(orderPollInterval)
    orderPollInterval = setInterval(async () => {
        if (!submittedOrder.value) {
            clearInterval(orderPollInterval)
            return
        }
        try {
            const res = await fetch(`/kiosk/${props.token}/api/orders/${submittedOrder.value.id}`, {
                headers: { 'Accept': 'application/json' }
            })
            if (res.ok) {
                const data = await res.json()
                submittedOrder.value = data
                localStorage.setItem(`kiosk_active_order_${props.token}`, JSON.stringify(data))
                
                // Sync with guest history
                const idx = guestOrders.value.findIndex(o => o.id === data.id)
                if (idx !== -1) {
                    guestOrders.value[idx] = data
                    localStorage.setItem(`kiosk_guest_orders_${props.token}`, JSON.stringify(guestOrders.value))
                }
                
                // Stop polling if completed or cancelled to save resources
                if (data.status === 'completed' || data.status === 'cancelled') {
                    clearInterval(orderPollInterval)
                }
            }
        } catch (e) {
            console.error('Error polling order status')
        }
    }, 5000)
}

function clearActiveOrder() {
    submittedOrder.value = null
    localStorage.removeItem(`kiosk_active_order_${props.token}`)
    if (orderPollInterval) clearInterval(orderPollInterval)
}

// ── State ─────────────────────────────────────────────────────────────────
const activeCategory = ref(null)
const cart = ref([])
const isCartOpen = ref(false)
const isItemModalOpen = ref(false)
const selectedItem = ref(null)
const itemQty = ref(1)
const itemNotes = ref('')
const selectedVariants = ref({})
const selectedAddOns = ref([])
const guestName = ref(props.customer?.name ?? '')
const orderNotes = ref('')
const isSubmitting = ref(false)
const submittedOrder = ref(null)

const isAuthModalOpen = ref(false)
const authMode = ref('login') // 'login' | 'register'
const authLogin = ref('')
const authPassword = ref('')
const authName = ref('')
const authPhone = ref('')
const authEmail = ref('')
const authError = ref('')
const authLoading = ref(false)

// Poll order status after submission
// usePoll(5000, { only: [] }) // We now use custom polling with startOrderPolling

// ── Computed ──────────────────────────────────────────────────────────────
const filteredItems = computed(() => {
    const pkgs = props.packages?.map(p => ({ ...p, isPackage: true })) || []
    if (activeCategory.value === 'paket') {
        return pkgs
    }
    if (!activeCategory.value) {
        return [...(props.menuItems || []), ...pkgs]
    }
    return (props.menuItems || []).filter(i => i.category_id === activeCategory.value)
})

const groupedItems = computed(() => {
    const items = filteredItems.value
    const groups = {}
    
    items.forEach(item => {
        let key = ''
        if (item.isPackage) {
            key = 'Paket Spesial'
        } else {
            key = item.category?.name || 'Lain-lain'
        }
        
        if (!groups[key]) {
            groups[key] = []
        }
        groups[key].push(item)
    })
    
    return Object.entries(groups).map(([name, list]) => ({
        name,
        items: list
    }))
})

const cartCount = computed(() => cart.value.reduce((s, i) => s + i.qty, 0))
const cartTotal = computed(() => cart.value.reduce((s, i) => s + i.subtotal, 0))
const taxAmount = computed(() => Math.round(cartTotal.value * 0.1))
const finalTotal = computed(() => cartTotal.value + taxAmount.value)

function formatRp(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)
}

// ── Item Modal ────────────────────────────────────────────────────────────
function openItem(item: any, isPackage = false) {
    selectedItem.value = { ...item, isPackage }
    itemQty.value = 1
    itemNotes.value = ''
    selectedVariants.value = {}
    selectedAddOns.value = []
    isItemModalOpen.value = true
}

function closeItemModal() {
    isItemModalOpen.value = false
}

function computedItemPrice() {
    if (!selectedItem.value) return 0

    let price = Number(selectedItem.value.price)

    Object.entries(selectedVariants.value).forEach(([optId, val]) => {
        if (Array.isArray(val)) {
            val.forEach(vId => {
                const opt = selectedItem.value.options?.find((o: any) => o.id === Number(optId))
                const optionVal = opt?.values?.find((v: any) => v.id === Number(vId))
                if (optionVal) price += Number(optionVal.additional_price || 0)
            })
        } else {
            const opt = selectedItem.value.options?.find((o: any) => o.id === Number(optId))
            const optionVal = opt?.values?.find((v: any) => v.id === Number(val))
            if (optionVal) price += Number(optionVal.additional_price || 0)
        }
    })

    for (const addOnId of selectedAddOns.value) {
        const addOn = selectedItem.value.add_ons?.find((a: any) => a.id === addOnId)
        if (addOn) price += Number(addOn.price ?? 0)
    }

    return price
}

const toggleOption = (opt: any, valId: number) => {
    if (opt.type === 'single') {
        selectedVariants.value[opt.id] = valId
    } else {
        if (!selectedVariants.value[opt.id] || !Array.isArray(selectedVariants.value[opt.id])) {
            selectedVariants.value[opt.id] = []
        }
        const index = selectedVariants.value[opt.id].indexOf(valId)
        if (index > -1) {
            selectedVariants.value[opt.id].splice(index, 1)
        } else {
            selectedVariants.value[opt.id].push(valId)
        }
    }
}

const isOptionSelected = (opt: any, valId: number) => {
    const selected = selectedVariants.value[opt.id]
    if (!selected) return false
    if (opt.type === 'single') return selected === valId
    return Array.isArray(selected) && selected.includes(valId)
}

function addToCart() {
    if (!selectedItem.value) return

    const missingRequired = selectedItem.value.options?.filter((opt: any) => {
        const selected = selectedVariants.value[opt.id]
        return opt.is_required && (!selected || (Array.isArray(selected) && selected.length === 0))
    })

    if (missingRequired?.length > 0) {
        alert(`Silakan pilih opsi wajib: ${missingRequired.map((o: any) => o.name).join(', ')}`)
        return
    }

    const price = computedItemPrice()
    const item = selectedItem.value
    const isPackage = item.isPackage

    const variantLabels: string[] = []
    Object.entries(selectedVariants.value).forEach(([optId, val]) => {
        const opt = item.options?.find((o: any) => o.id === Number(optId))
        if (Array.isArray(val)) {
            val.forEach(vId => {
                const optionVal = opt?.values?.find((v: any) => v.id === Number(vId))
                if (optionVal) variantLabels.push(`${opt.name}: ${optionVal.name}`)
            })
        } else {
            const optionVal = opt?.values?.find((v: any) => v.id === Number(val))
            if (optionVal) variantLabels.push(`${opt.name}: ${optionVal.name}`)
        }
    })

    const addOnLabels = selectedAddOns.value.map(id => {
        return item.add_ons?.find((a: any) => a.id === id)?.name
    }).filter(Boolean)

    const variantKey = Object.entries(selectedVariants.value).map(([k, v]) => {
        if (Array.isArray(v)) {
            return `${k}:${[...v].sort().join('-')}`
        }
        return `${k}:${v}`
    }).sort().join(',')
    
    const addOnKey = [...selectedAddOns.value].sort().join(',')
    const noteKey = (itemNotes.value || '').trim()
    const customizationHash = `${isPackage ? 'pkg-' : 'itm-'}${item.id}-${variantKey}-${addOnKey}-${noteKey}`

    const existing = cart.value.find((i: any) => i.customizationHash === customizationHash)

    if (existing) {
        existing.qty += itemQty.value
        existing.subtotal = existing.price * existing.qty
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
        })
    }

    isItemModalOpen.value = false
}

function removeFromCart(id: number) {
    cart.value = cart.value.filter((i: any) => i.id !== id)
}

function updateQty(id: number, delta: number) {
    const item: any = cart.value.find((i: any) => i.id === id)

    if (!item) {
return
}

    item.qty = Math.max(1, item.qty + delta)
    item.subtotal = item.price * item.qty
}

// ── Submit Order ──────────────────────────────────────────────────────────
async function submitOrder() {
    if (cart.value.length === 0) {
return
}

    isSubmitting.value = true

    const payload = {
        guest_name: guestName.value || null,
        notes: orderNotes.value || null,
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
    }

    try {
        const res = await fetch(`/kiosk/${props.token}/api/orders`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })

        if (!res.ok) {
            if (res.status === 422) {
                const errData = await res.json()
                throw new Error(errData.message || 'Stok tidak mencukupi untuk pesanan Anda.')
            }
            throw new Error('Gagal mengirim pesanan. Silakan coba lagi.')
        }

        const data = await res.json()
        submittedOrder.value = data
        localStorage.setItem(`kiosk_active_order_${props.token}`, JSON.stringify(data))
        
        // Append to guest history
        guestOrders.value.unshift(data)
        if (guestOrders.value.length > 10) guestOrders.value.pop() // Keep last 10 max
        localStorage.setItem(`kiosk_guest_orders_${props.token}`, JSON.stringify(guestOrders.value))

        cart.value = []
        isCartOpen.value = false
        startOrderPolling()
    } catch (e: any) {
        alert(e.message || 'Gagal mengirim pesanan. Silakan coba lagi.')
    } finally {
        isSubmitting.value = false
    }
}

async function openGuestHistory() {
    isGuestHistoryOpen.value = true
    isLoadingHistory.value = true
    
    try {
        // Fetch latest status for all orders in guestOrders
        const promises = guestOrders.value.map(order => 
            fetch(`/kiosk/${props.token}/api/orders/${order.id}`, { headers: { 'Accept': 'application/json' } }).then(r => r.json())
        )
        const updatedOrders = await Promise.all(promises)
        guestOrders.value = updatedOrders
        localStorage.setItem(`kiosk_guest_orders_${props.token}`, JSON.stringify(guestOrders.value))
    } catch (e) {
        console.error('Failed to sync guest history', e)
    } finally {
        isLoadingHistory.value = false
    }
}

// ── Auth ──────────────────────────────────────────────────────────────────
async function submitAuth() {
    authError.value = ''
    authLoading.value = true
    const url = authMode.value === 'login'
        ? `/kiosk/${props.token}/api/auth/login`
        : `/kiosk/${props.token}/api/auth/register`

    const body = authMode.value === 'login'
        ? { login: authLogin.value, password: authPassword.value }
        : { name: authName.value, email: authEmail.value || undefined, phone: authPhone.value || undefined, password: authPassword.value }

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        })
        const data = await res.json()

        if (!res.ok) {
            authError.value = data.message ?? 'Terjadi kesalahan.'
        } else {
            router.reload({ only: ['customer'] })
            isAuthModalOpen.value = false
        }
    } catch {
        authError.value = 'Koneksi gagal. Coba lagi.'
    } finally {
        authLoading.value = false
    }
}

async function logout() {
    await fetch(`/kiosk/${props.token}/api/auth/logout`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    })
    router.reload({ only: ['customer'] })
}

const statusMap = {
    pending: { label: 'Menunggu konfirmasi', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
    preparing: { label: 'Sedang dimasak', color: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400' },
    ready: { label: 'Siap disajikan', color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    served: { label: 'Sudah disajikan', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' }
}

function getStepIndex(status: string) {
    return ['pending', 'preparing', 'ready', 'served'].indexOf(status)
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
    <div class="flex flex-col min-h-[100dvh] bg-background text-foreground max-w-md md:max-w-4xl lg:max-w-6xl xl:max-w-7xl mx-auto relative md:border-x font-garamond shadow-2xl">
        
        <!-- Header -->
        <header class="sticky top-0 z-40 flex flex-col md:flex-row md:items-center justify-between px-4 md:px-6 py-3 bg-background/90 backdrop-blur-md border-b gap-3 md:gap-0">
            <div class="flex items-center justify-between md:justify-start gap-3 w-full md:w-auto">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                        <UtensilsCrossed class="w-5 h-5" />
                    </div>
                    <div>
                        <h1 class="font-bold text-base md:text-xl tracking-tight">Self Order</h1>
                        <div class="text-xs md:text-sm text-muted-foreground font-medium">Meja {{ table.number }}<span v-if="table.name"> · {{ table.name }}</span></div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 w-full md:w-auto border-t pt-2 md:border-t-0 md:pt-0">
                <template v-if="customer">
                    <Button variant="ghost" size="sm" class="h-8 md:h-10 px-3 font-garamond font-semibold text-sm md:text-base flex-1 md:flex-none justify-center" @click="router.visit(`/kiosk/${token}/orders`)">
                        <History class="w-4 h-4 md:w-5 md:h-5 mr-1" /> Riwayat
                    </Button>
                    <Button variant="ghost" size="sm" class="h-8 md:h-10 px-3 text-destructive hover:text-destructive hover:bg-destructive/10 shrink-0" @click="logout">
                        <LogOut class="w-4 h-4 md:w-5 md:h-5" />
                    </Button>
                </template>
                <template v-else>
                    <Button v-if="guestOrders.length > 0" variant="ghost" size="sm" class="h-8 md:h-10 px-3 font-garamond font-semibold text-sm md:text-base flex-1 md:flex-none justify-center" @click="openGuestHistory">
                        <History class="w-4 h-4 md:w-5 md:h-5 mr-1" /> Pesanan
                    </Button>
                    <Button variant="outline" size="sm" class="h-8 md:h-10 px-4 font-garamond font-semibold text-sm md:text-base flex-1 md:flex-none justify-center" @click="isAuthModalOpen = true; authMode = 'login'">
                        Masuk / Daftar
                    </Button>
                </template>
            </div>
        </header>

        <!-- Order Confirmation -->
        <div v-if="submittedOrder" class="flex-1 flex items-center justify-center p-6">
            <div class="text-center max-w-xs md:max-w-md mx-auto animate-in fade-in zoom-in-95 duration-300">
                <div class="w-20 h-20 md:w-28 md:h-28 bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-500 rounded-full flex items-center justify-center mx-auto mb-6">
                    <CheckCircle2 class="w-10 h-10 md:w-14 md:h-14" />
                </div>
                <h2 class="text-2xl md:text-3xl font-bold mb-2">Pesanan Dikirim!</h2>
                <p class="text-xl md:text-2xl text-primary font-bold tracking-widest mb-2">{{ submittedOrder.order_number }}</p>
                <p class="text-base md:text-lg text-muted-foreground mb-6">Silakan tunggu, pesananmu sedang diproses dapur.</p>
                
                <div class="inline-flex px-4 py-2 md:px-6 md:py-3 rounded-full text-sm md:text-base font-semibold mb-6" 
                     :class="statusMap[submittedOrder.status]?.color || 'bg-secondary text-secondary-foreground'">
                    {{ statusMap[submittedOrder.status]?.label ?? submittedOrder.status }}
                </div>

                <!-- Stepper Progress -->
                <div v-if="getStepIndex(submittedOrder.status) !== -1" class="w-full max-w-sm mx-auto mb-8 bg-secondary/30 border rounded-2xl p-5 space-y-4">
                    <div class="relative flex items-center justify-between">
                        <!-- Connecting Line -->
                        <div class="absolute left-[12.5%] right-[12.5%] top-3.5 h-0.5 bg-muted -z-10">
                            <div 
                                class="h-full bg-primary transition-all duration-500" 
                                :style="{ width: (getStepIndex(submittedOrder.status) / 3 * 100) + '%' }"
                            ></div>
                        </div>
                        
                        <!-- Steps -->
                        <div 
                            v-for="(step, idx) in ['pending', 'preparing', 'ready', 'served']" 
                            :key="step"
                            class="flex flex-col items-center flex-1 relative"
                        >
                            <div 
                                class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                                :class="getStepIndex(submittedOrder.status) >= idx 
                                    ? 'bg-primary text-primary-foreground shadow-sm scale-110' 
                                    : 'bg-muted text-muted-foreground'"
                            >
                                <span class="relative z-10">{{ idx + 1 }}</span>
                            </div>
                            <span 
                                class="text-[10px] md:text-xs mt-2 font-semibold text-center transition-colors"
                                :class="getStepIndex(submittedOrder.status) >= idx ? 'text-primary font-bold' : 'text-muted-foreground'"
                            >
                                {{ step === 'pending' ? 'Diterima' : step === 'preparing' ? 'Dimasak' : step === 'ready' ? 'Siap' : 'Disajikan' }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <Button class="w-full h-12 md:h-14 rounded-xl text-lg md:text-xl font-garamond font-bold tracking-wide" @click="clearActiveOrder">
                    <Plus class="w-5 h-5 md:w-6 md:h-6 mr-2" /> Pesan Lagi
                </Button>
            </div>
        </div>

        <!-- Main Content -->
        <main v-else class="flex-1 pb-32">
            <!-- Promo Banner -->
            <div class="px-4 md:px-6 pt-4 pb-2">
                <div class="w-full aspect-[21/9] md:aspect-[5/1] bg-secondary rounded-2xl md:rounded-3xl overflow-hidden relative flex items-center justify-center shadow-sm">
                    <div class="absolute inset-0 bg-gradient-to-r from-primary/80 to-primary/30 mix-blend-multiply"></div>
                    <img src="https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&q=80&w=1200" class="absolute inset-0 w-full h-full object-cover opacity-60" alt="Promo Kiosk" />
                    <div class="relative z-10 w-full h-full flex flex-col justify-center p-4 md:p-6 text-white drop-shadow-md">
                        <Badge class="self-start mb-1 md:mb-2 bg-white/20 text-white hover:bg-white/30 backdrop-blur-md border-none font-garamond uppercase tracking-widest text-[10px]">Promo Kiosk</Badge>
                        <h2 class="text-2xl md:text-3xl font-bold mb-1">Diskon Khusus Self-Order!</h2>
                        <p class="text-white/95 text-sm md:text-base font-medium">Nikmati kemudahan pesan langsung dari meja Anda.</p>
                    </div>
                </div>
            </div>

            <!-- Category Tabs -->
            <div class="flex gap-2 p-4 md:px-6 overflow-x-auto scrollbar-none [&::-webkit-scrollbar]:hidden sticky top-[112px] md:top-[64px] bg-background/95 backdrop-blur-sm z-30 border-b">
                <button
                    class="shrink-0 px-5 py-2 md:px-6 md:py-2.5 rounded-full text-base md:text-lg font-semibold transition-colors border-2"
                    :class="activeCategory === null ? 'bg-primary/10 text-primary border-primary' : 'bg-background text-muted-foreground border-border hover:bg-secondary'"
                    @click="activeCategory = null"
                >
                    Semua
                </button>
                <button
                    class="shrink-0 px-5 py-2 md:px-6 md:py-2.5 rounded-full text-base md:text-lg font-semibold transition-colors border-2"
                    :class="activeCategory === 'paket' ? 'bg-primary/10 text-primary border-primary' : 'bg-background text-muted-foreground border-border hover:bg-secondary'"
                    @click="activeCategory = 'paket'"
                >
                    Paket
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    class="shrink-0 px-5 py-2 md:px-6 md:py-2.5 rounded-full text-base md:text-lg font-semibold transition-colors border-2"
                    :class="activeCategory === cat.id ? 'bg-primary/10 text-primary border-primary' : 'bg-background text-muted-foreground border-border hover:bg-secondary'"
                    @click="activeCategory = cat.id"
                >
                    {{ cat.name }}
                </button>
            </div>

            <!-- Menu List / Grid Grouped by Category -->
            <div class="space-y-8 px-4 md:px-6 pt-6">
                <div v-for="group in groupedItems" :key="group.name" class="space-y-4">
                    <h2 class="font-bold text-xl md:text-2xl border-b pb-2 text-primary font-garamond">{{ group.name }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <div 
                            v-for="item in group.items" 
                            :key="item.id" 
                            class="flex flex-row md:flex-col items-stretch gap-4 md:gap-0 py-4 md:py-0 border-b md:border md:rounded-2xl md:overflow-hidden md:bg-card md:shadow-sm cursor-pointer group hover:border-primary/40 transition-colors"
                            @click="openItem(item, item.isPackage)"
                        >
                            <div class="relative w-24 h-24 md:w-full md:h-48 md:aspect-[4/3] shrink-0 rounded-2xl md:rounded-none bg-secondary/50 overflow-hidden shadow-sm md:shadow-none">
                                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                                <UtensilsCrossed v-else class="w-8 h-8 md:w-12 md:h-12 text-muted-foreground/30 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" />
                                <Badge v-if="item.isPackage" class="absolute top-2 right-2 bg-primary text-primary-foreground font-garamond uppercase tracking-wider text-[9px] px-2 py-0.5 shadow-sm">
                                    Paket
                                </Badge>
                            </div>
                            
                            <div class="flex-1 flex flex-col justify-between py-1 md:p-4">
                                <div>
                                    <h3 class="font-bold text-lg md:text-xl leading-tight mb-1 group-hover:text-primary transition-colors">{{ item.name }}</h3>
                                    <p v-if="!item.isPackage" class="text-sm md:text-base text-muted-foreground line-clamp-2 md:line-clamp-3 leading-snug mb-2 font-medium">{{ item.description }}</p>
                                    <div v-else-if="item.package_items && item.package_items.length" class="text-xs md:text-sm text-muted-foreground mb-3 space-y-0.5 font-medium">
                                        <div v-for="pkgItem in item.package_items" :key="pkgItem.id" class="flex items-center gap-1">
                                            <span class="font-semibold text-primary/80">{{ pkgItem.qty }}x</span>
                                            <span>{{ pkgItem.menu_item?.name || pkgItem.inventory_item?.name }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between mt-auto md:mt-4">
                                    <div class="font-bold text-primary text-base md:text-xl">{{ formatRp(item.price) }}</div>
                                    <Button size="sm" variant="outline" class="h-8 md:h-10 rounded-full px-4 border-primary text-primary group-hover:bg-primary group-hover:text-primary-foreground font-garamond font-bold md:text-base transition-colors">
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
        <div v-if="cartCount > 0 && !submittedOrder && isMounted" class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[calc(100vw-2rem)] max-w-md md:max-w-lg lg:max-w-2xl z-50 animate-in slide-in-from-bottom-8 font-garamond">
            <Button size="lg" class="w-full h-14 md:h-16 rounded-2xl md:rounded-3xl shadow-xl shadow-primary/25 text-lg md:text-xl px-5 md:px-6 flex justify-between items-center font-bold tracking-wide" @click="isCartOpen = true">
                <div class="flex items-center gap-3 md:gap-4">
                    <div class="bg-primary-foreground/20 text-primary-foreground rounded-lg md:rounded-xl px-3 py-1 md:px-4 md:py-1.5 text-base md:text-lg">
                        {{ cartCount }}
                    </div>
                    <span>Total Harga</span>
                </div>
                <span>{{ formatRp(finalTotal) }}</span>
            </Button>
        </div>

        <!-- Cart Full Page Overlay -->
        <div v-if="isMounted && isCartOpen" class="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm flex justify-center">
            <div class="w-full h-full max-w-md md:max-w-4xl lg:max-w-6xl xl:max-w-7xl bg-background flex flex-col shadow-2xl md:border-x animate-in slide-in-from-right-8 duration-300 font-garamond">
                <header class="flex-none sticky top-0 z-10 flex items-center px-4 md:px-6 py-3 border-b bg-background">
                    <Button variant="ghost" size="sm" class="h-10 px-2 -ml-2 text-muted-foreground font-garamond text-base" @click="isCartOpen = false">
                        <ChevronLeft class="w-6 h-6 mr-1" /> Kembali
                    </Button>
                    <h1 class="font-bold text-lg md:text-xl mx-auto pr-16 md:pr-20">Pesananmu</h1>
                </header>
                
                <div class="flex-1 overflow-y-auto">
                    <div class="px-6 py-4 flex flex-col gap-6 max-w-3xl mx-auto w-full">
                        <div class="flex flex-col gap-4">
                            <div v-for="item in cart" :key="item.id" class="flex gap-3 pb-4 border-b last:border-0 last:pb-0">
                                <div class="flex-1">
                                    <h4 class="font-bold text-base md:text-lg">{{ item.name }}</h4>
                                    <p v-if="item.variants.length" class="text-sm md:text-base text-muted-foreground mt-0.5">{{ item.variants.join(' · ') }}</p>
                                    <p v-if="item.add_ons.length" class="text-sm md:text-base text-muted-foreground mt-0.5">+ {{ item.add_ons.join(', ') }}</p>
                                    <p v-if="item.notes" class="text-sm md:text-base text-muted-foreground italic mt-0.5">"{{ item.notes }}"</p>
                                    <div class="font-bold text-primary text-base md:text-lg mt-1">{{ formatRp(item.price) }}</div>
                                </div>
                                <div class="flex flex-col items-end justify-between">
                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-muted-foreground hover:text-destructive" @click="removeFromCart(item.id)">
                                        <Trash2 class="w-5 h-5" />
                                    </Button>
                                    <div class="flex items-center gap-2 bg-secondary rounded-lg p-1.5 border">
                                        <button class="w-7 h-7 flex items-center justify-center bg-background rounded shadow-sm text-foreground active:scale-95" @click="updateQty(item.id, -1)">
                                            <Minus class="w-3.5 h-3.5" />
                                        </button>
                                        <span class="text-base font-bold w-5 text-center">{{ item.qty }}</span>
                                        <button class="w-7 h-7 flex items-center justify-center bg-background rounded shadow-sm text-foreground active:scale-95" @click="updateQty(item.id, 1)">
                                            <Plus class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 pt-4 border-t">
                            <div class="space-y-2">
                                <Label for="guestName" class="text-base">Nama Pemesan (opsional)</Label>
                                <Input id="guestName" v-model="guestName" placeholder="Nama kamu..." class="font-garamond text-base h-11" />
                            </div>
                            <div class="space-y-2">
                                <Label for="orderNotes" class="text-base">Catatan untuk Dapur (opsional)</Label>
                                <textarea 
                                    id="orderNotes" 
                                    v-model="orderNotes" 
                                    placeholder="Contoh: jangan terlalu pedas..." 
                                    class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-base font-garamond ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                ></textarea>
                            </div>
                        </div>

                        <div class="space-y-3 pt-6 border-t mt-4 mb-8">
                            <div class="flex justify-between text-base md:text-lg text-muted-foreground">
                                <span>Subtotal</span>
                                <span class="font-semibold">{{ formatRp(cartTotal) }}</span>
                            </div>
                            <div class="flex justify-between text-base md:text-lg text-muted-foreground">
                                <span>Pajak (10%)</span>
                                <span class="font-semibold">{{ formatRp(taxAmount) }}</span>
                            </div>
                            <div class="flex justify-between font-bold text-xl md:text-2xl pt-3 border-t">
                                <span>Total Tagihan</span>
                                <span class="text-primary">{{ formatRp(finalTotal) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-none p-4 md:p-6 bg-background border-t">
                    <div class="max-w-3xl mx-auto w-full">
                        <Button size="lg" class="w-full h-14 md:h-16 rounded-2xl md:rounded-3xl text-xl font-garamond font-bold tracking-wide" :disabled="isSubmitting" @click="submitOrder">
                            {{ isSubmitting ? 'Mengirim...' : '🚀 Kirim Pesanan' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Item Details Full Page Overlay -->
        <div v-if="isMounted && isItemModalOpen" class="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm flex justify-center">
            <div class="w-full h-full max-w-md md:max-w-4xl lg:max-w-6xl xl:max-w-7xl bg-background flex flex-col shadow-2xl md:border-x animate-in slide-in-from-right-8 duration-300 font-garamond">
                <header class="flex-none sticky top-0 z-20 flex items-center px-4 md:px-6 py-3 border-b bg-background/90 backdrop-blur-md">
                    <Button variant="ghost" size="sm" class="h-10 px-2 -ml-2 text-muted-foreground font-garamond text-base" @click="closeItemModal">
                        <ChevronLeft class="w-6 h-6 mr-1" /> Kembali
                    </Button>
                    <h1 class="font-bold text-lg md:text-xl mx-auto pr-16 md:pr-20">Detail Menu</h1>
                </header>
                
                <div class="flex-1 overflow-y-auto">
                    <div class="relative w-full aspect-[4/3] md:aspect-[21/9] bg-secondary/50 flex items-center justify-center shrink-0">
                        <img v-if="selectedItem?.image_url" :src="selectedItem.image_url" :alt="selectedItem?.name" class="w-full h-full object-cover" />
                        <UtensilsCrossed v-else class="w-16 h-16 text-muted-foreground/30" />
                    </div>
                    
                    <div class="p-5 md:p-8 space-y-6 md:space-y-8 max-w-3xl mx-auto">
                        <div>
                            <h2 class="text-2xl md:text-3xl mb-2 font-bold">{{ selectedItem?.name }}</h2>
                            <p class="text-base md:text-lg font-medium text-muted-foreground mb-4">{{ selectedItem?.description }}</p>
                            
                            <!-- Detailed Information -->
                            <div class="flex flex-wrap gap-2 mb-6">
                                <Badge variant="secondary" class="font-garamond text-sm px-2.5 py-1" v-if="selectedItem?.category">
                                    <List class="w-3.5 h-3.5 mr-1" /> {{ selectedItem.category.name }}
                                </Badge>
                                <Badge variant="outline" class="font-garamond text-sm px-2.5 py-1 text-orange-600 border-orange-200 bg-orange-50 dark:bg-orange-950/30" v-if="selectedItem?.calories">
                                    <Flame class="w-3.5 h-3.5 mr-1" /> {{ selectedItem.calories }} kkal
                                </Badge>
                                <Badge variant="outline" class="font-garamond text-sm px-2.5 py-1 text-red-600 border-red-200 bg-red-50 dark:bg-red-950/30" v-if="selectedItem?.allergens?.length">
                                    <ShieldAlert class="w-3.5 h-3.5 mr-1" /> Alergen: {{ selectedItem.allergens.join(', ') }}
                                </Badge>
                                <Badge variant="outline" class="font-garamond text-sm px-2.5 py-1 text-blue-600 border-blue-200 bg-blue-50 dark:bg-blue-950/30" v-if="selectedItem?.tags?.length">
                                    <Tag class="w-3.5 h-3.5 mr-1" /> {{ selectedItem.tags.join(', ') }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Package Items -->
                        <div v-if="selectedItem?.isPackage && selectedItem?.package_items?.length" class="space-y-3 md:space-y-4">
                            <Label class="text-lg md:text-xl font-bold">Isi Paket</Label>
                            <div class="border rounded-2xl divide-y overflow-hidden bg-muted/20">
                                <div
                                    v-for="item in selectedItem.package_items"
                                    :key="item.id"
                                    class="flex items-center justify-between px-5 py-4 font-garamond"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                            <UtensilsCrossed v-if="item.menu_item" class="w-5 h-5 text-muted-foreground/60" />
                                            <ShoppingBag v-else class="w-5 h-5 text-muted-foreground/60" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-bold text-base md:text-lg">
                                                {{ item.menu_item?.name || item.inventory_item?.name }}
                                            </span>
                                            <span v-if="item.notes" class="text-xs md:text-sm text-muted-foreground italic">
                                                * {{ item.notes }}
                                            </span>
                                        </div>
                                    </div>
                                    <span class="font-bold text-base md:text-lg bg-primary/10 text-primary px-3 py-1 rounded-full">
                                        {{ item.qty }}x
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Options -->
                        <div v-for="option in selectedItem?.options" :key="option.id" class="space-y-3 md:space-y-4">
                            <div class="flex items-center gap-2">
                                <Label class="text-lg md:text-xl font-bold">{{ option.name }}</Label>
                                <Badge v-if="option.is_required" variant="destructive" class="px-2 py-0.5 text-[11px] uppercase">Wajib</Badge>
                            </div>
                            <div class="flex flex-col gap-2 md:gap-3">
                                <button
                                    v-for="val in option.values"
                                    :key="val.id"
                                    class="flex items-center justify-between px-5 py-3 border-2 rounded-xl text-base font-semibold transition-colors font-garamond text-left"
                                    :class="isOptionSelected(option, val.id) ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background hover:bg-secondary'"
                                    @click="toggleOption(option, val.id)"
                                >
                                    <span>{{ val.name }}</span>
                                    <span v-if="val.additional_price > 0" class="opacity-70">+{{ formatRp(val.additional_price) }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Add-ons -->
                        <div v-if="selectedItem?.add_ons?.length" class="space-y-3 md:space-y-4">
                            <Label class="text-lg md:text-xl font-bold">Tambahan</Label>
                            <div class="flex flex-col gap-2 md:gap-3">
                                <button
                                    v-for="addOn in selectedItem.add_ons"
                                    :key="addOn.id"
                                    class="flex items-center justify-between px-5 py-3 border-2 rounded-xl text-base font-semibold transition-colors font-garamond text-left"
                                    :class="selectedAddOns.includes(addOn.id) ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background hover:bg-secondary'"
                                    @click="selectedAddOns.includes(addOn.id) ? selectedAddOns.splice(selectedAddOns.indexOf(addOn.id), 1) : selectedAddOns.push(addOn.id)"
                                >
                                    <span>{{ addOn.name }}</span>
                                    <span v-if="addOn.price > 0" class="opacity-70">+{{ formatRp(addOn.price) }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="itemNotes" class="text-lg md:text-xl font-bold">Catatan Khusus</Label>
                            <Input id="itemNotes" v-model="itemNotes" placeholder="Contoh: tanpa bawang..." class="font-garamond text-base md:text-lg h-12 md:h-14" />
                        </div>
                    </div>
                </div>

                <div class="flex-none p-4 md:p-6 border-t bg-background">
                    <div class="flex flex-row items-center gap-4 max-w-3xl mx-auto w-full">
                        <div class="flex-1 flex items-center justify-between gap-4 bg-secondary rounded-2xl p-2 border">
                            <button class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-background rounded-xl shadow-sm active:scale-95 shrink-0" @click="itemQty = Math.max(1, itemQty - 1)">
                                <Minus class="w-5 h-5" />
                            </button>
                            <span class="text-xl md:text-2xl font-bold w-12 text-center">{{ itemQty }}</span>
                            <button class="w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-background rounded-xl shadow-sm active:scale-95 shrink-0" @click="itemQty++">
                                <Plus class="w-5 h-5" />
                            </button>
                        </div>
                        <Button size="lg" class="flex-none w-[180px] md:w-[240px] h-14 md:h-16 rounded-2xl md:rounded-3xl text-lg md:text-2xl font-bold font-garamond tracking-wide" @click="addToCart">
                            Tambah
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auth Modal -->
        <Dialog v-model:open="isAuthModalOpen" v-if="isMounted">
            <DialogContent class="max-w-sm w-[95vw] rounded-2xl p-6 sm:max-w-[425px] font-garamond">
                <DialogHeader>
                    <DialogTitle>{{ authMode === 'login' ? 'Masuk ke Akun' : 'Daftar Akun Baru' }}</DialogTitle>
                    <DialogDescription>
                        {{ authMode === 'login' ? 'Masuk untuk melihat riwayat pesananmu.' : 'Buat akun agar pemesanan lebih mudah.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="flex bg-secondary p-1 rounded-lg mb-4">
                    <button class="flex-1 py-1.5 text-sm font-medium rounded-md transition-colors" :class="authMode === 'login' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground'" @click="authMode = 'login'; authError = ''">Masuk</button>
                    <button class="flex-1 py-1.5 text-sm font-medium rounded-md transition-colors" :class="authMode === 'register' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground'" @click="authMode = 'register'; authError = ''">Daftar</button>
                </div>

                <div v-if="authError" class="bg-destructive/15 text-destructive text-sm p-3 rounded-md mb-4 border border-destructive/20">
                    {{ authError }}
                </div>

                <div class="space-y-4">
                    <template v-if="authMode === 'login'">
                        <div class="space-y-2">
                            <Label for="authLogin">Email / No. HP</Label>
                            <Input id="authLogin" v-model="authLogin" placeholder="Masukkan email atau no. hp" />
                        </div>
                        <div class="space-y-2">
                            <Label for="authPassword">Password</Label>
                            <Input id="authPassword" v-model="authPassword" type="password" placeholder="••••••" />
                        </div>
                    </template>
                    <template v-else>
                        <div class="space-y-2">
                            <Label for="authName">Nama Lengkap</Label>
                            <Input id="authName" v-model="authName" placeholder="John Doe" />
                        </div>
                        <div class="space-y-2">
                            <Label for="authPhone">No. HP <span class="text-muted-foreground font-normal">(opsional)</span></Label>
                            <Input id="authPhone" v-model="authPhone" type="tel" placeholder="08..." />
                        </div>
                        <div class="space-y-2">
                            <Label for="authEmail">Email <span class="text-muted-foreground font-normal">(opsional)</span></Label>
                            <Input id="authEmail" v-model="authEmail" type="email" placeholder="john@example.com" />
                        </div>
                        <div class="space-y-2">
                            <Label for="authPassword">Password</Label>
                            <Input id="authPassword" v-model="authPassword" type="password" placeholder="Minimal 6 karakter" />
                        </div>
                    </template>
                </div>

                <DialogFooter class="mt-6 sm:justify-start">
                    <Button class="w-full" :disabled="authLoading" @click="submitAuth">
                        {{ authLoading ? 'Loading...' : (authMode === 'login' ? 'Masuk' : 'Buat Akun') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <!-- Guest History Full Page Overlay -->
        <div v-if="isMounted && isGuestHistoryOpen" class="fixed inset-0 z-50 bg-background/80 backdrop-blur-sm flex justify-center">
            <div class="w-full h-full max-w-md md:max-w-4xl lg:max-w-6xl xl:max-w-7xl bg-background flex flex-col shadow-2xl md:border-x animate-in slide-in-from-right-8 duration-300 font-garamond">
                <header class="flex-none sticky top-0 z-20 flex items-center px-4 md:px-6 py-3 border-b bg-background/90 backdrop-blur-md">
                    <Button variant="ghost" size="sm" class="h-10 px-2 -ml-2 text-muted-foreground font-garamond text-base" @click="isGuestHistoryOpen = false">
                        <ChevronLeft class="w-6 h-6 mr-1" /> Kembali
                    </Button>
                    <h1 class="font-bold text-lg md:text-xl mx-auto pr-16 md:pr-20">Pesanan Saya</h1>
                </header>
                
                <div class="flex-1 overflow-y-auto p-4 md:p-6 pb-12">
                    <div v-if="isLoadingHistory" class="flex justify-center py-12">
                        <span class="animate-pulse font-bold text-lg">Memuat...</span>
                    </div>
                    <div v-else-if="guestOrders.length === 0" class="text-center py-16 md:py-24 text-muted-foreground">
                        <UtensilsCrossed class="w-12 h-12 md:w-16 md:h-16 mx-auto mb-4 opacity-20" />
                        <p class="text-lg md:text-xl">Belum ada pesanan</p>
                    </div>
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 max-w-7xl mx-auto">
                        <Card v-for="order in guestOrders" :key="order.id" class="overflow-hidden shadow-sm">
                            <CardContent class="p-4 md:p-5">
                                <div class="flex justify-between items-start mb-2 md:mb-3">
                                    <div>
                                        <div class="font-bold text-base md:text-lg">{{ order.order_number }}</div>
                                        <div class="text-xs md:text-sm text-muted-foreground">{{ new Date(order.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</div>
                                    </div>
                                    <div class="text-xs md:text-sm font-semibold px-2 md:px-3 py-1 md:py-1.5 rounded-md" :class="statusMap[order.status]?.color || 'bg-secondary text-secondary-foreground'">
                                        {{ statusMap[order.status]?.label ?? order.status }}
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1 md:gap-1.5 text-sm md:text-base text-muted-foreground mb-4 md:mb-5">
                                    <span v-for="item in order.items" :key="item.id">
                                        {{ item.qty }}× {{ item.name }}
                                    </span>
                                </div>
                                
                                <!-- Stepper Progress for active order -->
                                <div v-if="getStepIndex(order.status) !== -1" class="mb-4 md:mb-5 pt-3 border-t space-y-2">
                                    <div class="relative flex items-center justify-between px-1">
                                        <!-- Connecting Line -->
                                        <div class="absolute left-[12.5%] right-[12.5%] top-3 h-0.5 bg-muted -z-10">
                                            <div 
                                                class="h-full bg-primary transition-all duration-500" 
                                                :style="{ width: (getStepIndex(order.status) / 3 * 100) + '%' }"
                                            ></div>
                                        </div>
                                        
                                        <!-- Steps -->
                                        <div 
                                            v-for="(step, idx) in ['pending', 'preparing', 'ready', 'served']" 
                                            :key="step"
                                            class="flex flex-col items-center flex-1 relative"
                                        >
                                            <div 
                                                class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-all duration-300"
                                                :class="getStepIndex(order.status) >= idx 
                                                    ? 'bg-primary text-primary-foreground shadow-sm scale-110' 
                                                    : 'bg-muted text-muted-foreground'"
                                            >
                                                <span class="relative z-10">{{ idx + 1 }}</span>
                                            </div>
                                            <span 
                                                class="text-[9px] md:text-[10px] mt-1 font-semibold text-center transition-colors"
                                                :class="getStepIndex(order.status) >= idx ? 'text-primary font-bold' : 'text-muted-foreground'"
                                            >
                                                {{ step === 'pending' ? 'Diterima' : step === 'preparing' ? 'Dimasak' : step === 'ready' ? 'Siap' : 'Disajikan' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center pt-3 md:pt-4 border-t">
                                    <span class="font-bold text-base md:text-lg">{{ formatRp(order.final_amount) }}</span>
                                    <div class="text-xs md:text-sm font-medium px-2 md:px-3 py-1 md:py-1.5 rounded-md" :class="order.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-destructive/15 text-destructive'">
                                        {{ order.payment_status === 'paid' ? 'Lunas' : 'Belum bayar' }}
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
