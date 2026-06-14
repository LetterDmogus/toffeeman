<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import {
	ArrowDownToLine,
	ArrowRightLeft,
	ArrowUpFromLine,
	BarChart3,
	Boxes,
	Carrot,
	ChefHat,
	ClipboardCheck,
	ClipboardList,
	Clock,
	Contact,
	FolderClosed,
	Gift,
	History,
	Layers,
	LayoutGrid,
	Monitor,
	PlusCircle,
	Receipt,
	Search,
	Settings,
	ShieldCheck,
	Table2,
	Tags,
	TicketPercent,
	Users,
	UtensilsCrossed,
	Wallet,
	Database,
} from "lucide-vue-next";
import { computed, ref } from "vue";
import AppLogo from "@/components/AppLogo.vue";
import NavMain from "@/components/NavMain.vue";
import NavUser from "@/components/NavUser.vue";
import {
	Sidebar,
	SidebarContent,
	SidebarFooter,
	SidebarGroup,
	SidebarHeader,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
} from "@/components/ui/sidebar";
import type { NavItem } from "@/types";

const catalogNavItems: NavItem[] = [
	{ title: "Menu & Varian", href: route("catalog.menu"), icon: UtensilsCrossed },
	{ title: "Almanak Resep", href: route("catalog.recipe-book"), icon: ChefHat },
	{ title: "Kategori Menu", href: route("catalog.categories"), icon: Tags },
	{ title: "Paket Menu", href: route("catalog.packages"), icon: Gift },
	{ title: "Extra Topping", href: route("catalog.add-ons"), icon: PlusCircle },
	{ title: "Sistem Promo", href: route("catalog.promos"), icon: TicketPercent },
];

const opsIngredientsNavItems: NavItem[] = [
	{ title: "Bahan Baku", href: route("ops.ingredients"), icon: Carrot },
	{
		title: "Kategori Bahan",
		href: route("ops.ingredient-categories"),
		icon: FolderClosed,
	},
	{ title: "Batch Bahan", href: route("ops.ingredient-batches"), icon: History },
	{
		title: "Mutasi Bahan",
		href: route("ops.ingredient-mutations"),
		icon: ArrowRightLeft,
	},
];

const opsEquipmentNavItems: NavItem[] = [
	{ title: "Meja", href: route("ops.tables"), icon: Table2 },
	{ title: "Barang / Peralatan", href: route("ops.inventory"), icon: Boxes },
	{
		title: "Kategori Barang",
		href: route("ops.inventory-categories"),
		icon: Layers,
	},
	{
		title: "Barang Masuk",
		href: route("ops.inventory-ins"),
		icon: ArrowDownToLine,
	},
	{
		title: "Barang Keluar",
		href: route("ops.inventory-outs"),
		icon: ArrowUpFromLine,
	},
	{
		title: "Stok Opname",
		href: route("ops.inventory-opnames"),
		icon: ClipboardCheck,
	},
	{
		title: "Mutasi Stok",
		href: route("ops.inventory-mutations"),
		icon: ArrowRightLeft,
	},
];

const teamNavItems: NavItem[] = [
	{ title: "Karyawan", href: route("team.employees"), icon: Users },
	{ title: "Jabatan", href: route("team.positions"), icon: Contact },
	{ title: "User / Pengguna", href: route("team.users"), icon: ShieldCheck },
	{ title: "Hak Akses Role", href: route("team.roles-permissions"), icon: ShieldCheck },
];

const siteSettingsNavItems: NavItem[] = [
	{ title: "IP & Lokasi", href: route("site-settings.ip-location.edit"), icon: Settings },
	{ title: "Database", href: "/site-settings/database", icon: Database },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const permissions = computed<string[]>(() => (page.props.auth as any).permissions ?? []);

const hasPermission = (permission: string): boolean => {
	if (!user.value) return false;
	if (user.value.role === "superadmin") return true;
	return permissions.value.includes(permission);
};

const isManagerOrAdmin = computed(() => {
	return user.value && ["superadmin", "manager", "admin"].includes(user.value.role);
});

const searchQuery = ref("");

const mainNavItems = computed(() => {
	const items = [
		{ title: "Dashboard", href: route("dashboard"), icon: LayoutGrid, permission: null },
		{ title: "Kasir (POS)", href: route("pos"), icon: Monitor, permission: "pos-access" },
		{ title: "Dapur (KDS)", href: route("kitchen"), icon: ChefHat, permission: "kitchen-access" },
		{ title: "Daftar Pesanan", href: route("orders"), icon: Receipt, permission: "orders-access" },
		{ title: "Kios Absensi", href: route("attendance.kiosk"), icon: Clock, permission: "kiosk-attendance-access" },
		{ title: "Manajemen Absensi", href: route("attendance.index"), icon: ClipboardList, permission: "attendance-management" },
		{ title: "Laporan Keuangan", href: route("reports"), icon: BarChart3, permission: "view-reports" },
		{ title: "Laporan Pesanan", href: route("reports.orders"), icon: ClipboardCheck, permission: "view-reports" },
		{ title: "Biaya Operasional", href: route("ops.expenses"), icon: Wallet, permission: "view-reports" },
		{ title: "Penggajian", href: route("payroll.index"), icon: Wallet, permission: "payroll-access" },
	];
	return items.filter(item => !item.permission || hasPermission(item.permission));
});

const filteredMainItems = computed(() => {
	if (!searchQuery.value) {
		return mainNavItems.value;
	}

	return mainNavItems.value.filter((item) =>
		item.title.toLowerCase().includes(searchQuery.value.toLowerCase()),
	);
});

const filteredCatalogItems = computed(() => {
	if (!hasPermission("manage-catalog")) return [];
	if (!searchQuery.value) {
		return catalogNavItems;
	}

	return catalogNavItems.filter((item) =>
		item.title.toLowerCase().includes(searchQuery.value.toLowerCase()),
	);
});

const filteredOpsIngredientsItems = computed(() => {
	if (!hasPermission("manage-ops-ingredients")) return [];
	if (!searchQuery.value) {
		return opsIngredientsNavItems;
	}

	return opsIngredientsNavItems.filter((item) =>
		item.title.toLowerCase().includes(searchQuery.value.toLowerCase()),
	);
});

const filteredOpsEquipmentItems = computed(() => {
	if (!hasPermission("manage-ops-equipment")) return [];
	if (!searchQuery.value) {
		return opsEquipmentNavItems;
	}

	return opsEquipmentNavItems.filter((item) =>
		item.title.toLowerCase().includes(searchQuery.value.toLowerCase()),
	);
});

const filteredTeamItems = computed(() => {
	if (!hasPermission("manage-team")) return [];
	if (!searchQuery.value) {
		return teamNavItems;
	}

	return teamNavItems.filter((item) =>
		item.title.toLowerCase().includes(searchQuery.value.toLowerCase()),
	);
});

const filteredSiteSettingsItems = computed(() => {
	if (!hasPermission("settings-access")) return [];
	if (!searchQuery.value) {
		return siteSettingsNavItems;
	}

	return siteSettingsNavItems.filter((item) =>
		item.title.toLowerCase().includes(searchQuery.value.toLowerCase()),
	);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
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
                    <Search
                        class="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari menu..."
                        class="w-full rounded-lg border border-input bg-background py-1.5 pr-3 pl-8 text-xs shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-brand-500 focus-visible:outline-none"
                    />
                </div>
            </SidebarGroup>

            <SidebarGroup v-if="filteredMainItems.length > 0" class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in filteredMainItems"
                        :key="item.title"
                    >
                        <SidebarMenuButton as-child :tooltip="item.title">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <NavMain
                v-if="filteredCatalogItems.length > 0"
                label="Katalog Produk"
                :items="filteredCatalogItems"
            />
            <NavMain
                v-if="filteredOpsIngredientsItems.length > 0"
                label="Stok Bahan Baku"
                :items="filteredOpsIngredientsItems"
            />
            <NavMain
                v-if="filteredOpsEquipmentItems.length > 0"
                label="Meja & Peralatan"
                :items="filteredOpsEquipmentItems"
            />
            <NavMain
                v-if="filteredTeamItems.length > 0"
                label="Manajemen Tim"
                :items="filteredTeamItems"
            />
            <NavMain
                v-if="filteredSiteSettingsItems.length > 0"
                label="Site Settings"
                :items="filteredSiteSettingsItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
