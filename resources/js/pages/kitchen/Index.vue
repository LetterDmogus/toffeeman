<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { 
    Clock, 
    ChefHat, 
    CheckCircle2, 
    AlertCircle, 
    Utensils,
    MoveRight,
    Loader2,
    Check,
    RotateCcw,
    History,
    ChevronLeft,
    ChevronRight,
    X,
    LayoutDashboard,
    ArrowLeft
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Dapur (KDS)', href: '#' },
        ],
    },
});

const orders = ref<any[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const viewMode = ref<'active' | 'history'>('active');
const historyOrders = ref<any[]>([]);

const fetchOrders = async (silent = false) => {
    if (!silent) loading.value = true;
    else refreshing.value = true;
    
    try {
        const res = await fetch('/api/kitchen/orders', {
            headers: { Accept: 'application/json' }
        });
        if (res.ok) {
            orders.value = await res.json();
        }
    } catch (e) {
        console.error('Gagal mengambil data pesanan:', e);
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const toggleHistory = async () => {
    if (viewMode.value === 'active') {
        loading.value = true;
        try {
            const res = await fetch('/api/orders?status=served&per_page=20', {
                headers: { Accept: 'application/json' }
            });
            if (res.ok) {
                const data = await res.json();
                historyOrders.value = data.data;
                viewMode.value = 'history';
            }
        } catch (e) { console.error(e); }
        finally { loading.value = false; }
    } else {
        viewMode.value = 'active';
        fetchOrders();
    }
};

const updateStatus = async (orderId: number, status: string) => {
    try {
        const res = await fetch(`/api/kitchen/orders/${orderId}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            },
            body: JSON.stringify({ status })
        });
        
        if (res.ok) {
            if (viewMode.value === 'history') {
                await toggleHistory(); // Refresh history
            } else {
                await fetchOrders(true);
            }
        } else {
            const data = await res.json();
            alert(data.message || 'Gagal memperbarui status');
        }
    } catch (e) {
        console.error('Terjadi kesalahan:', e);
        alert('Kesalahan koneksi');
    }
};

const toggleItemStatus = async (item: any) => {
    const newStatus = item.status === 'done' ? 'cooking' : 'done';
    await updateItemStatus(item, newStatus);
};

const updateItemStatus = async (item: any, status: string) => {
    try {
        const res = await fetch(`/api/kitchen/items/${item.id}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || ''
            },
            body: JSON.stringify({ status })
        });
        
        if (res.ok) {
            item.status = status;
        }
    } catch (e) { console.error(e); }
};

const isAllItemsDone = (order: any) => {
    return order.items.every((item: any) => item.status === 'done' || item.status === 'cancelled');
};

// ─── Summary Calculation (Grouped by Category) ───────────────────────────────
const productSummary = computed(() => {
    const summary: Record<string, { category: string, items: Record<string, number> }> = {};
    
    orders.value.forEach(order => {
        if (order.status !== 'ready') {
            order.items.forEach((item: any) => {
                if (item.status !== 'done' && item.status !== 'cancelled') {
                    const catName = item.menu_item?.category?.name || (item.package_id ? 'Paket' : 'Lainnya');
                    
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
    
    return Object.values(summary).sort((a, b) => a.category.localeCompare(b.category));
});

// Polling for new orders every 15 seconds
let pollInterval: any;
onMounted(() => {
    fetchOrders();
    pollInterval = setInterval(() => {
        if (viewMode.value === 'active') fetchOrders(true);
    }, 15000);
});

onUnmounted(() => {
    clearInterval(pollInterval);
});

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

const getWaitTime = (dateString: string) => {
    const start = new Date(dateString).getTime();
    const now = new Date().getTime();
    const diff = Math.floor((now - start) / 60000); // minutes
    return diff;
};

const getHeaderColor = (order: any) => {
    const wait = getWaitTime(order.created_at);
    if (order.status === 'preparing') return 'bg-brand-600 text-white';
    if (order.status === 'ready') return 'bg-emerald-500 text-white';
    if (wait > 20) return 'bg-red-600 text-white';
    if (wait > 10) return 'bg-amber-500 text-white';
    return 'bg-slate-800 text-white';
};
</script>

<template>
    <div class="flex h-[calc(100vh-64px)] bg-slate-100 overflow-hidden">
        <!-- 🛒 MAIN AREA: Order Cards -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Scrollable Grid of Cards -->
            <div class="flex-1 overflow-x-auto p-6 custom-scrollbar bg-[#e2e8f0]/40">
                <div v-if="loading" class="h-full flex items-center justify-center">
                    <Loader2 class="h-12 w-12 animate-spin text-brand-600" />
                </div>

                <!-- HISTORY VIEW -->
                <div v-else-if="viewMode === 'history'" class="h-full flex flex-col animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Recall Orders</h2>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Daftar pesanan yang baru saja diselesaikan</p>
                        </div>
                        <Button @click="viewMode = 'active'" variant="outline" class="bg-white border-slate-200 text-slate-600 h-12 rounded-xl gap-2 font-black uppercase text-[10px] tracking-widest px-6 shadow-sm">
                            <ArrowLeft class="h-4 w-4" /> Kembali ke Pesanan Aktif
                        </Button>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                        <div v-if="historyOrders.length === 0" class="h-full flex flex-col items-center justify-center text-slate-300">
                            <History class="h-20 w-20 mb-4 opacity-20" />
                            <p class="font-black uppercase tracking-widest">Belum ada riwayat pesanan</p>
                        </div>
                        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 pb-8">
                            <div v-for="h in historyOrders" :key="h.id" class="bg-white border-2 border-slate-100 shadow-sm flex flex-col overflow-hidden">
                                <div class="p-6 flex flex-col gap-4 flex-1">
                                    <div class="flex justify-between items-start">
                                        <div class="flex gap-4 items-center">
                                            <Badge class="h-12 w-20 bg-slate-900 text-white font-black text-sm flex items-center justify-center border-none rounded-none">#{{ h.order_number.slice(-4) }}</Badge>
                                            <div>
                                                <h4 class="text-lg font-black text-slate-800 uppercase leading-none">{{ h.table ? 'Table ' + h.table.number : 'Take Away' }}</h4>
                                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">{{ formatTime(h.updated_at) }} • {{ h.customer?.name || 'Walk-in' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <Separator class="bg-slate-50" />
                                    <div class="space-y-2">
                                        <div v-for="item in h.items" :key="item.id" class="flex justify-between text-[11px] font-bold text-slate-500 uppercase">
                                            <span>{{ item.qty }}x {{ item.name }}</span>
                                            <span v-if="item.status === 'cancelled'" class="text-red-500">CANCELLED</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Footer Recall -->
                                <button @click="updateStatus(h.id, 'preparing')" class="h-12 w-full bg-slate-50 hover:bg-brand-50 border-t border-slate-100 text-brand-600 font-black text-[10px] tracking-widest uppercase transition-colors">
                                    Recall Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ACTIVE ORDERS VIEW -->
                <template v-else>
                    <div v-if="orders.length === 0" class="h-full flex flex-col items-center justify-center bg-white border-2 border-dashed border-slate-200 shadow-inner">
                        <CheckCircle2 class="h-24 w-24 text-emerald-100 mb-4" />
                        <h2 class="text-2xl font-black text-slate-300 uppercase">Dapur Bersih!</h2>
                        <p class="text-sm font-bold text-slate-300 italic mt-2">Menunggu pesanan baru masuk...</p>
                    </div>

                    <div v-else class="flex gap-6 h-full min-w-max">
                        <div v-for="order in orders" :key="order.id" 
                            class="w-[340px] bg-white border-2 flex flex-col shadow-2xl transition-all relative overflow-hidden group"
                            :class="[
                                order.status === 'preparing' ? 'border-brand-500 ring-8 ring-brand-500/10 scale-[1.02] z-10' : 'border-slate-100',
                                order.status === 'ready' ? 'opacity-40 grayscale-[0.5]' : ''
                            ]"
                        >
                            <!-- Order Header -->
                            <div class="p-4 shrink-0" :class="getHeaderColor(order)">
                                <div class="flex justify-between items-center mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-black">#{{ order.order_number.slice(-4) }}</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest opacity-70">{{ order.order_type }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs font-black">
                                        <Clock class="h-3 w-3" />
                                        {{ getWaitTime(order.created_at) }}'
                                    </div>
                                </div>
                                <h3 class="text-xl font-black uppercase tracking-tight truncate leading-none">
                                    {{ order.table ? 'Table ' + order.table.number : 'Take Away' }}
                                </h3>
                                <div class="mt-2 flex items-center justify-between opacity-80">
                                    <span class="text-[9px] font-black uppercase tracking-widest">{{ order.customer?.name || 'Walk-in' }}</span>
                                    <span class="text-[9px] font-bold uppercase tracking-widest">{{ formatTime(order.created_at) }}</span>
                                </div>
                            </div>

                            <!-- Items List -->
                            <div class="flex-1 overflow-y-auto p-5 space-y-4 custom-scrollbar bg-slate-50/50">
                                <div v-for="item in order.items" :key="item.id" 
                                    @click="toggleItemStatus(item)"
                                    class="cursor-pointer transition-all active:scale-[0.98]"
                                >
                                    <div class="flex gap-3 items-start">
                                        <div class="h-7 w-7 rounded-lg flex items-center justify-center text-xs font-black shrink-0 border-2"
                                            :class="item.status === 'done' ? 'bg-emerald-500 border-emerald-500 text-white' : (item.status === 'cancelled' ? 'bg-red-500 border-red-500 text-white' : 'bg-white border-slate-200 text-slate-700')"
                                        >
                                            <Check v-if="item.status === 'done'" class="h-4 w-4" />
                                            <X v-else-if="item.status === 'cancelled'" class="h-4 w-4" />
                                            <span v-else>{{ item.qty }}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start">
                                                <p class="text-sm font-black uppercase leading-tight" :class="item.status === 'done' ? 'line-through text-slate-300' : (item.status === 'cancelled' ? 'line-through text-red-300' : 'text-slate-800')">
                                                    {{ item.name }}
                                                </p>
                                                <button 
                                                    v-if="item.status !== 'cancelled' && order.status !== 'ready'"
                                                    @click.stop="updateItemStatus(item, 'cancelled')" 
                                                    class="text-slate-300 hover:text-red-500 transition-colors p-1"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </div>
                                            
                                            <!-- Customizations -->
                                            <div v-if="item.variants?.length || item.add_ons?.length" class="flex flex-wrap gap-1 mt-1">
                                                <span v-for="v in item.variants" :key="v.id" class="text-[9px] font-black text-slate-500 bg-white border border-slate-200 px-1.5 py-0.5 rounded italic">
                                                    {{ v.option_name ? v.option_name + ': ' : '' }}{{ v.name || v.value_name }}
                                                </span>
                                                <span v-for="a in item.add_ons" :key="a.name" class="text-[9px] font-black text-brand-600 bg-brand-50 border border-brand-100 px-1.5 py-0.5 rounded uppercase">
                                                    +{{ a.name }}
                                                </span>
                                            </div>

                                            <p v-if="item.notes" class="mt-2 p-2 bg-amber-50 rounded-xl border border-amber-100 text-[10px] font-bold text-amber-700 italic">
                                                "{{ item.notes }}"
                                            </p>
                                            
                                            <Badge v-if="item.status === 'cancelled'" variant="outline" class="mt-2 bg-red-50 text-red-600 border-red-100 text-[8px] font-black uppercase">CANCELLED</Badge>
                                        </div>
                                    </div>
                                    <Separator class="bg-slate-200/50 mt-4" />
                                </div>

                                <!-- Order Level Notes -->
                                <div v-if="order.notes" class="p-4 bg-red-50 rounded-2xl border border-red-100 flex items-start gap-3">
                                    <AlertCircle class="h-4 w-4 text-red-600 shrink-0 mt-0.5" />
                                    <p class="text-[10px] font-bold text-red-800 italic leading-relaxed uppercase tracking-tight">{{ order.notes }}</p>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="p-5 bg-white border-t border-slate-100 shrink-0">
                                <!-- ACTION: Pending -> Cooking -->
                                <Button 
                                    v-if="order.status === 'pending'" 
                                    @click="updateStatus(order.id, 'preparing')"
                                    class="w-full h-14 rounded-2xl bg-brand-600 hover:bg-brand-700 text-white font-black uppercase tracking-widest shadow-xl shadow-brand-100 flex items-center justify-between px-6 transition-all active:scale-[0.98]"
                                >
                                    <span>COOKING</span>
                                    <Utensils class="h-5 w-5" />
                                </Button>

                                <!-- ACTION: Preparing -> Done -->
                                <div v-else-if="order.status === 'preparing'" class="flex gap-2">
                                    <Button 
                                        variant="outline"
                                        @click="updateStatus(order.id, 'pending')"
                                        class="h-14 w-14 rounded-2xl border-slate-200 text-slate-300 hover:bg-slate-50 hover:text-slate-500"
                                    >
                                        <RotateCcw class="h-5 w-5" />
                                    </Button>
                                    <Button 
                                        @click="updateStatus(order.id, 'ready')"
                                        class="flex-1 h-14 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black uppercase tracking-widest shadow-lg shadow-emerald-100 flex items-center justify-between px-6 transition-all active:scale-[0.98]"
                                    >
                                        <span>DONE</span>
                                        <CheckCircle2 class="h-5 w-5" />
                                    </Button>
                                </div>

                                <!-- ACTION: Ready -> Served (Bump) -->
                                <Button 
                                    v-else-if="order.status === 'ready'" 
                                    @click="updateStatus(order.id, 'served')"
                                    class="w-full h-14 rounded-2xl bg-slate-900 hover:bg-black text-white font-black uppercase tracking-widest shadow-xl flex items-center justify-between px-6 transition-all active:scale-[0.98]"
                                >
                                    <span>BUMP ORDER</span>
                                    <MoveRight class="h-5 w-5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Bottom Action Bar -->
            <div class="h-20 bg-slate-900 flex items-center justify-between px-8 shrink-0">
                <div class="flex gap-4">
                    <Button variant="ghost" class="text-white/40 hover:text-white font-black uppercase tracking-[0.2em] text-[10px] gap-2">
                        <ChevronLeft class="h-4 w-4" /> Prev
                    </Button>
                    <Button variant="ghost" class="text-white/40 hover:text-white font-black uppercase tracking-[0.2em] text-[10px] gap-2">
                        Next <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>

                <div class="flex gap-3 items-center">
                    <div v-if="refreshing" class="text-[9px] font-black text-white/30 uppercase tracking-[0.2em] mr-4 flex items-center gap-2">
                        <Loader2 class="h-3 w-3 animate-spin" /> Syncing
                    </div>
                    <Button @click="toggleHistory" variant="outline" class="bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-700 h-12 rounded-2xl gap-3 font-black uppercase text-[10px] tracking-widest px-6">
                        <History v-if="viewMode === 'active'" class="h-4 w-4" />
                        <Utensils v-else class="h-4 w-4" />
                        {{ viewMode === 'active' ? 'RECALL LAST' : 'PESANAN AKTIF' }}
                    </Button>
                    <Button @click="fetchOrders()" variant="outline" class="bg-brand-900 border-brand-800 text-brand-300 hover:bg-brand-800 h-12 rounded-2xl gap-3 font-black uppercase text-[10px] tracking-widest px-6">
                        REFRESH
                    </Button>
                </div>
            </div>
        </div>

        <!-- 📊 RIGHT SIDEBAR: Product Summary -->
        <div class="w-[300px] bg-white border-l border-slate-200 flex flex-col shrink-0 animate-in slide-in-from-right duration-500">
            <div class="p-6 border-b border-slate-100 bg-slate-900 text-white">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Ringkasan</h2>
                    <Badge class="bg-brand-600 text-white text-[9px] px-2 py-0 border-none">{{ orders.length }} Pesanan</Badge>
                </div>
                <p class="text-2xl font-black tracking-tight">Kebutuhan Menu</p>
            </div>

            <div class="flex-1 overflow-y-auto p-6 custom-scrollbar bg-white">
                <div v-if="productSummary.length === 0" class="h-40 flex flex-col items-center justify-center text-slate-300 italic p-8 text-center border-2 border-dashed border-slate-100 rounded-3xl">
                    <p class="text-xs font-bold uppercase tracking-widest">Tidak ada item aktif</p>
                </div>
                
                <div v-for="cat in productSummary" :key="cat.category" class="mb-8 last:mb-0">
                    <h3 class="text-[10px] font-black text-brand-500 uppercase tracking-[0.2em] mb-4 border-b border-brand-100 pb-2 flex justify-between items-center">
                        {{ cat.category }}
                        <span class="text-slate-300 font-normal">({{ Object.keys(cat.items).length }})</span>
                    </h3>
                    <div class="space-y-3">
                        <div v-for="(qty, name) in cat.items" :key="name" class="flex justify-between items-start gap-4">
                            <span class="text-xs font-bold text-slate-700 uppercase leading-tight flex-1">{{ name }}</span>
                            <span class="text-sm font-black text-slate-900 shrink-0">x{{ qty }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-6 border-t border-slate-100 bg-slate-50 flex items-center justify-center">
                <ChefHat class="h-6 w-6 text-slate-200" />
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
