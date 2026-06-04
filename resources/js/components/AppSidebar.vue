<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { 
    LayoutGrid, 
    Monitor,
    ChefHat,
    Receipt,
    BarChart3,
    UtensilsCrossed, 
    Tags, 
    Gift, 
    PlusCircle, 
    Table2, 
    Boxes, 
    Layers, 
    Users, 
    Contact, 
    ShieldCheck,
    Carrot,
    FolderClosed,
    History,
    ArrowDownToLine,
    ArrowUpFromLine,
    ClipboardCheck,
    Search
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
} from '@/components/ui/sidebar';
import * as routes from '@/routes';
import catalog from '@/routes/catalog';
import ops from '@/routes/ops';
import team from '@/routes/team';
import type { NavItem } from '@/types';

const catalogNavItems: NavItem[] = [
    { title: 'Menu & Varian', href: catalog.menu().url, icon: UtensilsCrossed },
    { title: 'Kategori Menu', href: catalog.categories().url, icon: Tags },
    { title: 'Paket Menu', href: catalog.packages().url, icon: Gift },
    { title: 'Extra Topping', href: catalog.addOns().url, icon: PlusCircle },
];

const opsNavItems: NavItem[] = [
    { title: 'Meja', href: ops.tables().url, icon: Table2 },
    { title: 'Bahan Baku', href: ops.ingredients().url, icon: Carrot },
    { title: 'Kategori Bahan', href: ops.ingredientCategories().url, icon: FolderClosed },
    { title: 'Batch Bahan', href: ops.ingredientBatches().url, icon: History },
    { title: 'Barang / Peralatan', href: ops.inventory().url, icon: Boxes },
    { title: 'Kategori Barang', href: ops.inventoryCategories().url, icon: Layers },
    { title: 'Barang Masuk', href: ops.inventoryIns().url, icon: ArrowDownToLine },
    { title: 'Barang Keluar', href: ops.inventoryOuts().url, icon: ArrowUpFromLine },
    { title: 'Stok Opname', href: ops.inventoryOpnames().url, icon: ClipboardCheck },
];

const teamNavItems: NavItem[] = [
    { title: 'Karyawan', href: team.employees().url, icon: Users },
    { title: 'Jabatan', href: team.positions().url, icon: Contact },
    { title: 'User / Pengguna', href: team.users().url, icon: ShieldCheck },
];

const searchQuery = ref('');

const mainNavItems = [
    { title: 'Dashboard', href: routes.dashboard().url, icon: LayoutGrid },
    { title: 'Kasir (POS)', href: routes.pos().url, icon: Monitor },
    { title: 'Dapur (KDS)', href: routes.kitchen().url, icon: ChefHat },
    { title: 'Daftar Pesanan', href: routes.orders().url, icon: Receipt },
    { title: 'Laporan Keuangan', href: routes.reports().url, icon: BarChart3 },
];

const filteredMainItems = computed(() => {
    if (!searchQuery.value) return mainNavItems;
    return mainNavItems.filter(item =>
        item.title.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const filteredCatalogItems = computed(() => {
    if (!searchQuery.value) return catalogNavItems;
    return catalogNavItems.filter(item =>
        item.title.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const filteredOpsItems = computed(() => {
    if (!searchQuery.value) return opsNavItems;
    return opsNavItems.filter(item =>
        item.title.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const filteredTeamItems = computed(() => {
    if (!searchQuery.value) return teamNavItems;
    return teamNavItems.filter(item =>
        item.title.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="routes.dashboard().url">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- 🔍 Search Bar -->
            <SidebarGroup class="px-3 pt-3 pb-1">
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari menu..."
                        class="w-full rounded-lg border border-input bg-background pl-8 pr-3 py-1.5 text-xs shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-brand-500"
                    />
                </div>
            </SidebarGroup>

            <SidebarGroup v-if="filteredMainItems.length > 0" class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in filteredMainItems" :key="item.title">
                        <SidebarMenuButton as-child :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <NavMain v-if="filteredCatalogItems.length > 0" label="Katalog Produk" :items="filteredCatalogItems" />
            <NavMain v-if="filteredOpsItems.length > 0" label="Operasional & Stok" :items="filteredOpsItems" />
            <NavMain v-if="filteredTeamItems.length > 0" label="Manajemen Tim" :items="filteredTeamItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
