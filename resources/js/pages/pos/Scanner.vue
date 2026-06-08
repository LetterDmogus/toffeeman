<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    session: string;
}>();

const scanStatus = ref<'idle' | 'scanning' | 'success' | 'error'>('idle');
const statusMessage = ref<string>('Izinkan akses kamera untuk mulai memindai.');
const lastScanned = ref<string | null>(null);
const isLoading = ref<boolean>(true);

let html5QrCode: any = null;

// Load html5-qrcode library dynamically from CDN
const loadScannerLibrary = (): Promise<void> => {
    return new Promise((resolve, reject) => {
        if ((window as any).Html5Qrcode) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Gagal memuat library scanner.'));
        document.head.appendChild(script);
    });
};

const startScanning = async () => {
    if (!html5QrCode) return;

    scanStatus.value = 'scanning';
    statusMessage.value = 'Mencari barcode/QR code...';

    try {
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 150 }, // typical barcode/QR aspect ratio
            aspectRatio: 1.0
        };

        await html5QrCode.start(
            { facingMode: "environment" }, // Use back camera
            config,
            onScanSuccess,
            onScanFailure
        );
    } catch (err: any) {
        console.error('Gagal memulai kamera:', err);
        scanStatus.value = 'error';
        statusMessage.value = 'Gagal mengakses kamera. Pastikan izin kamera telah diberikan dan situs menggunakan HTTPS.';
    }
};

const stopScanning = async () => {
    if (html5QrCode && html5QrCode.isScanning) {
        try {
            await html5QrCode.stop();
            scanStatus.value = 'idle';
            statusMessage.value = 'Kamera dinonaktifkan.';
        } catch (err) {
            console.error('Gagal menghentikan kamera:', err);
        }
    }
};

const onScanSuccess = async (decodedText: string, decodedResult: any) => {
    // Prevent double scan within 1.5 seconds of same code
    if (lastScanned.value === decodedText && scanStatus.value === 'success') {
        return;
    }

    lastScanned.value = decodedText;
    scanStatus.value = 'success';
    statusMessage.value = `Berhasil memindai: ${decodedText}. Mengirim...`;

    // Play a beep sound locally if supported
    try {
        const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = 1000;
        gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.1);
    } catch (e) {
        // ignore audio context failures
    }

    try {
        const response = await fetch('/api/scanner/submit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                session: props.session,
                sku: decodedText
            })
        });

        if (response.ok) {
            statusMessage.value = `Terkirim: ${decodedText}. Siap memindai lagi.`;
            setTimeout(() => {
                if (scanStatus.value === 'success') {
                    scanStatus.value = 'scanning';
                    statusMessage.value = 'Mencari barcode/QR code...';
                }
            }, 1500);
        } else {
            scanStatus.value = 'error';
            statusMessage.value = 'Gagal mengirim data ke POS server.';
        }
    } catch (error) {
        scanStatus.value = 'error';
        statusMessage.value = 'Kesalahan koneksi ke server.';
    }
};

const onScanFailure = (error: any) => {
    // Frequently triggered while searching, can be ignored to avoid spam
};

onMounted(async () => {
    if (!props.session) {
        scanStatus.value = 'error';
        statusMessage.value = 'Sesi scanner tidak valid. Buka POS di laptop terlebih dahulu.';
        isLoading.value = false;
        return;
    }

    try {
        await loadScannerLibrary();
        isLoading.value = false;
        html5QrCode = new (window as any).Html5Qrcode("reader");
        startScanning();
    } catch (err: any) {
        isLoading.value = false;
        scanStatus.value = 'error';
        statusMessage.value = err.message || 'Gagal menyiapkan modul scanner.';
    }
});

onUnmounted(async () => {
    await stopScanning();
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between p-4 font-sans select-none">
        <!-- Header -->
        <header class="text-center py-4">
            <h1 class="text-xl font-bold tracking-tight text-emerald-400 flex items-center justify-center gap-2">
                HP Barcode Scanner
            </h1>
            <p class="text-xs text-slate-400 mt-1">Terhubung ke Sesi: <code class="bg-slate-900 px-2 py-0.5 rounded font-mono text-amber-400">{{ session }}</code></p>
        </header>

        <!-- Scanner Box -->
        <main class="flex-grow flex flex-col items-center justify-center">
            <div class="relative w-full max-w-sm aspect-square bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden shadow-2xl flex items-center justify-center">
                <div v-show="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-slate-900 z-10">
                    <div class="w-10 h-10 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-xs text-slate-400 mt-3">Menyiapkan kamera...</span>
                </div>

                <!-- HTML5 QrCode Reader Container -->
                <div id="reader" class="w-full h-full object-cover"></div>

                <!-- Laser scanning overlay animation -->
                <div v-if="scanStatus === 'scanning'" class="absolute inset-x-4 h-0.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)] animate-pulse" style="top: 50%;"></div>
            </div>

            <!-- Status Indicator -->
            <div class="mt-6 w-full max-w-sm text-center">
                <div
                    class="p-4 rounded-xl text-sm border flex items-center justify-center gap-3"
                    :class="{
                        'bg-slate-900/60 border-slate-800 text-slate-300': scanStatus === 'idle',
                        'bg-blue-500/10 border-blue-500/20 text-blue-400': scanStatus === 'scanning',
                        'bg-emerald-500/10 border-emerald-500/20 text-emerald-400': scanStatus === 'success',
                        'bg-red-500/10 border-red-500/20 text-red-400': scanStatus === 'error'
                    }"
                >
                    <p class="font-medium leading-relaxed">{{ statusMessage }}</p>
                </div>
            </div>
        </main>

        <!-- Footer / Control Buttons -->
        <footer class="py-6 flex flex-col gap-3 max-w-sm mx-auto w-full">
            <button
                v-if="scanStatus === 'idle' || scanStatus === 'error'"
                @click="startScanning"
                class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 px-4 rounded-xl transition duration-200 active:scale-95 shadow-lg shadow-emerald-900/20"
            >
                Aktifkan Kamera
            </button>
            <button
                v-else
                @click="stopScanning"
                class="w-full bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold py-3 px-4 rounded-xl transition duration-200 active:scale-95"
            >
                Matikan Kamera
            </button>
        </footer>
    </div>
</template>

<style scoped>
/* Ensure the html5-qrcode default UI is hidden */
:deep(#reader__dashboard) {
    display: none !important;
}
:deep(#reader__status_span) {
    display: none !important;
}
:deep(#reader video) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    border-radius: 1rem !important;
}
</style>
