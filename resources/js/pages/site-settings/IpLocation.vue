<script setup lang="ts">
import { Form, Head } from "@inertiajs/vue3";
import { ref } from "vue";
import AppSettingController from "@/actions/App/Http/Controllers/Settings/AppSettingController";
import Heading from "@/components/Heading.vue";
import InputError from "@/components/InputError.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MapPin, Info, Navigation, Loader2 } from "lucide-vue-next";
import { edit } from "@/routes/site-settings/ip-location";
import AppLayout from "@/layouts/AppLayout.vue";

defineOptions({
	layout: AppLayout,
	breadcrumbs: [
		{
			title: "Site Settings",
			href: edit(),
		},
		{
			title: "IP & Location",
			href: edit(),
		},
	],
});

const props = defineProps<{
	settings: {
		restaurant_ip: string | null;
		latitude: number | null;
		longitude: number | null;
		radius_meters: number;
	};
}>();

const gettingLocation = ref(false);
const locationError = ref<string | null>(null);

const getCurrentLocation = () => {
	gettingLocation.value = true;
	locationError.value = null;

	if (!navigator.geolocation) {
		locationError.value = "Geolocation tidak didukung oleh browser Anda.";
		gettingLocation.value = false;
		return;
	}

	navigator.geolocation.getCurrentPosition(
		(position) => {
			const latInput = document.getElementById("latitude") as HTMLInputElement;
			const lngInput = document.getElementById("longitude") as HTMLInputElement;
			
			if (latInput && lngInput) {
				latInput.value = position.coords.latitude.toFixed(8);
				latInput.dispatchEvent(new Event("input", { bubbles: true }));
				
				lngInput.value = position.coords.longitude.toFixed(8);
				lngInput.dispatchEvent(new Event("input", { bubbles: true }));
			}
			gettingLocation.value = false;
		},
		(error) => {
			console.error(error);
			locationError.value = "Gagal mengambil lokasi. Pastikan izin lokasi diaktifkan di browser Anda.";
			gettingLocation.value = false;
		},
		{ enableHighAccuracy: true, timeout: 10000 }
	);
};
</script>

<template>
    <Head title="Pengaturan Sistem" />

    <div class="px-6 py-6 w-full space-y-6">
        <Heading
            title="Site Settings"
            description="Manage your system configurations, geolocation parameters, and network constraints."
        />

        <!-- Form Card Full Width -->
        <div class="border border-border bg-card rounded-xl p-6 shadow-sm space-y-6">
            <div>
                <h3 class="text-lg font-medium">IP & Geolocation Settings</h3>
                <p class="text-xs text-muted-foreground">Konfigurasikan lokasi fisik dan alamat IP utama restoran Anda.</p>
            </div>

            <Form
                v-bind="AppSettingController.update.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <!-- IP Address Restoran -->
                <div class="grid gap-2">
                    <Label for="restaurant_ip" class="text-sm font-semibold">IP Address Restoran</Label>
                    <Input
                        id="restaurant_ip"
                        class="mt-1 block w-full font-mono bg-background"
                        name="restaurant_ip"
                        :default-value="settings.restaurant_ip || ''"
                        placeholder="Contoh: 192.168.1.1 atau 36.85.123.45"
                    />
                    <p class="text-xs text-muted-foreground">
                        Kosongkan jika hanya ingin memverifikasi berdasarkan radius koordinat GPS saja.
                    </p>
                    <InputError class="mt-2" :message="errors.restaurant_ip" />
                </div>

                <!-- Titik Geolocation -->
                <div class="grid gap-4 p-4 border border-dashed border-border rounded-lg bg-muted/40">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold flex items-center gap-2">
                            <MapPin class="h-4 w-4 text-brand-500" />
                            Koordinat GPS Restoran
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="gettingLocation"
                            @click="getCurrentLocation"
                            class="gap-1.5 text-xs h-8 bg-background"
                        >
                            <Loader2 v-if="gettingLocation" class="h-3.5 w-3.5 animate-spin" />
                            <Navigation v-else class="h-3.5 w-3.5" />
                            Gunakan Lokasi Saat Ini
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="latitude" class="text-xs">Latitude</Label>
                            <Input
                                id="latitude"
                                type="number"
                                step="any"
                                class="mt-1 block w-full bg-background"
                                name="latitude"
                                :default-value="settings.latitude ?? ''"
                                placeholder="Contoh: -6.2088"
                                required
                            />
                            <InputError class="mt-2" :message="errors.latitude" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="longitude" class="text-xs">Longitude</Label>
                            <Input
                                id="longitude"
                                type="number"
                                step="any"
                                class="mt-1 block w-full bg-background"
                                name="longitude"
                                :default-value="settings.longitude ?? ''"
                                placeholder="Contoh: 106.8456"
                                required
                            />
                            <InputError class="mt-2" :message="errors.longitude" />
                        </div>
                    </div>

                    <div v-if="locationError" class="text-xs text-destructive mt-1">
                        {{ locationError }}
                    </div>
                </div>

                <!-- Radius Toleransi -->
                <div class="grid gap-2">
                    <Label for="radius_meters" class="text-sm font-semibold">Radius Toleransi (Meter)</Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="radius_meters"
                            type="number"
                            class="mt-1 block w-full bg-background"
                            name="radius_meters"
                            :default-value="settings.radius_meters"
                            min="5"
                            required
                        />
                        <span class="text-sm font-medium text-muted-foreground mt-1">meter</span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Jarak maksimal karyawan dari koordinat restoran agar absensi disetujui otomatis.
                    </p>
                    <InputError class="mt-2" :message="errors.radius_meters" />
                </div>

                <!-- Button Save -->
                <div class="flex items-center gap-4 pt-2">
                    <Button :disabled="processing">
                        Simpan Pengaturan
                    </Button>
                </div>
            </Form>
        </div>

        <!-- Information Card Horizontal Footer -->
        <div class="border border-border bg-muted/20 rounded-xl p-5 space-y-4">
            <div class="flex items-start gap-2.5">
                <Info class="h-5 w-5 text-brand-500 shrink-0 mt-0.5" />
                <div>
                    <h4 class="font-semibold text-sm">Bagaimana ini digunakan?</h4>
                    <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                        Ketika karyawan melakukan absensi selfie, sistem kami mencocokkan IP Address serta koordinat lokasi perangkat mereka saat itu.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                <div class="bg-card border border-border rounded-lg p-4 text-xs shadow-xs">
                    <span class="inline-block px-1.5 py-0.5 bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-400 font-semibold rounded text-[10px] mb-1.5">
                        AUTO APPROVED ✅
                    </span>
                    <p class="text-muted-foreground leading-relaxed">Karyawan berada di dalam radius GPS restoran yang telah diset, <strong>atau</strong> terhubung ke WiFi/IP restoran.</p>
                </div>

                <div class="bg-card border border-border rounded-lg p-4 text-xs shadow-xs">
                    <span class="inline-block px-1.5 py-0.5 bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-400 font-semibold rounded text-[10px] mb-1.5">
                        PENDING VERIFICATION ⏳
                    </span>
                    <p class="text-muted-foreground leading-relaxed">Karyawan absen di luar radius GPS dan tidak menggunakan IP internet restoran. Manager harus menyetujui absensi secara manual.</p>
                </div>
            </div>
        </div>
    </div>
</template>
