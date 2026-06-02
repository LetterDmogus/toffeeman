<script setup lang="ts">
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
    ShieldCheck 
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
    { title: 'Inventaris', href: ops.inventory().url, icon: Boxes },
    { title: 'Kategori Barang', href: ops.inventoryCategories().url, icon: Layers },
];

const teamNavItems: NavItem[] = [
    { title: 'Karyawan', href: team.employees().url, icon: Users },
    { title: 'Jabatan', href: team.positions().url, icon: Contact },
    { title: 'User / Pengguna', href: team.users().url, icon: ShieldCheck },
];
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
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :tooltip="'Dashboard'">
                            <Link :href="routes.dashboard().url">
                                <LayoutGrid />
                                <span>Dashboard</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :tooltip="'Kasir (POS)'">
                            <Link :href="routes.pos().url">
                                <Monitor />
                                <span>Kasir (POS)</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :tooltip="'Dapur (KDS)'">
                            <Link :href="routes.kitchen().url">
                                <ChefHat />
                                <span>Dapur (KDS)</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :tooltip="'Daftar Pesanan'">
                            <Link :href="routes.orders().url">
                                <Receipt />
                                <span>Daftar Pesanan</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :tooltip="'Laporan'">
                            <Link :href="routes.reports().url">
                                <BarChart3 />
                                <span>Laporan Keuangan</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <NavMain label="Katalog Produk" :items="catalogNavItems" />
            <NavMain label="Operasional & Stok" :items="opsNavItems" />
            <NavMain label="Manajemen Tim" :items="teamNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
