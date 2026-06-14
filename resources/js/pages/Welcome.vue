<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { ChevronRight, ArrowRight, ShoppingBag } from "lucide-vue-next";
</script>

<template>
    <Head title="Toffeeman">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
        <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400..800;1,400..800" rel="stylesheet">
    </Head>

    <div class="min-h-screen bg-[#faf8f5] text-amber-950 eb-garamond selection:bg-amber-200 selection:text-amber-950 eb-garamond">
        <!-- ─── HEADER NAVBAR ─── -->
        <header class="max-w-6xl mx-auto px-6 py-6">
            <nav class="flex items-center justify-between">
                <Link href="/" class="text-xl font-bold tracking-tight text-amber-950 font-sans">
                    Toffeeman
                </Link>
                <div class="flex items-center gap-6">
                    <!-- Check if logged in or guest -->
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="group flex items-center gap-1 text-sm font-bold font-sans text-amber-900 hover:text-amber-700 transition">
                        <span>Dashboard</span>
                        <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                    </Link>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-bold font-sans text-slate-600 hover:text-slate-900 transition">
                            Login
                        </Link>
                        <Link :href="route('register')" class="group flex items-center gap-1 bg-amber-900 text-[#faf8f5] text-xs font-bold font-sans px-4 py-2 rounded-lg hover:bg-amber-800 transition">
                            <span>Daftar Sekarang</span>
                            <ChevronRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <!-- ─── MAIN HERO SECTION ─── -->
        <main class="max-w-6xl mx-auto px-6 md:px-12 py-16 md:py-24 space-y-24 md:space-y-36">

            <!-- SECTION 1: Text Left, Image Right -->
            <section class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-16 items-center">
                <div class="md:col-span-7 space-y-6">
                    <h1 class="text-5xl md:text-7xl font-bold tracking-tight text-neutral-900 leading-tight">
                        Cita Rasa Mewah Pisang Karamel Klasik
                    </h1>
                    <p class="text-lg md:text-xl text-neutral-700 font-serif leading-relaxed">
                        Nikmati pisang karamel panggang khas Toffeeman yang disajikan hangat dengan es krim vanila lembut, taburan kacang pecan renyah, dan siraman saus karamel keemasan yang menggugah selera.
                    </p>
                    <div class="pt-2">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="inline-flex items-center gap-2 bg-amber-950 text-[#faf8f5] font-sans text-sm font-bold px-6 py-3.5 rounded-xl hover:bg-neutral-800 transition duration-300">
                            <span>Mulai Kasir POS</span>
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <Link v-else :href="route('login')" class="inline-flex items-center gap-2 bg-amber-950 text-[#faf8f5] font-sans text-sm font-bold px-6 py-3.5 rounded-xl hover:bg-neutral-800 transition duration-300">
                            <span>Pesan Sekarang</span>
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
                <div class="md:col-span-5">
                    <div class="overflow-hidden rounded-2xl border border-amber-900/10 shadow-2xl shadow-amber-900/5 aspect-square">
                        <img
                            src="/nano_banana_dessert.png"
                            alt="Gourmet Banana Dessert"
                            class="w-full h-full object-cover hover:scale-105 transition duration-500"
                        />
                    </div>
                </div>
            </section>

            <!-- SECTION 2: Image Left, Text Right -->
            <section class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-16 items-center">
                <div class="md:col-span-5 order-last md:order-first">
                    <div class="overflow-hidden rounded-2xl border border-amber-900/10 shadow-2xl shadow-amber-900/5 aspect-square">
                        <img
                            src="/nano_banana_breakfast.png"
                            alt="Gourmet Banana Breakfast Pancakes"
                            class="w-full h-full object-cover hover:scale-105 transition duration-500"
                        />
                    </div>
                </div>
                <div class="md:col-span-7 space-y-6">
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-amber-900/10 bg-amber-50 px-3 py-1 text-xs font-semibold font-sans text-amber-900">
                        <span>Sarapan Sehat & Segar</span>
                        <ChevronRight class="h-3 w-3" />
                    </div>
                    <h2 class="text-4xl md:text-6xl font-bold tracking-tight text-neutral-900 leading-tight">
                        Pancake Pisang & Smoothie Berenergi
                    </h2>
                    <p class="text-lg md:text-xl text-neutral-700 font-serif leading-relaxed">
                        Lengkapi pagi produktif Anda dengan tumpukan pancake lembut bertabur pisang segar dan kacang walnut gurih, disiram madu murni berkualitas, bersanding dengan smoothie pisang segar tinggi protein.
                    </p>
                    <div class="pt-2">
                        <a href="#about" class="group inline-flex items-center gap-1 text-sm font-bold font-sans text-amber-900 hover:text-amber-700 transition">
                            <span>Pelajari cara kami menyajikan hidangan</span>
                            <ChevronRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                        </a>
                    </div>
                </div>
            </section>

        </main>

        <!-- ─── FOOTER ─── -->
        <footer class="border-t border-amber-900/5 bg-neutral-900 text-neutral-400 py-12 px-6 text-center text-xs font-sans">
            <p>&copy; 2026 Toffeeman POS. All rights reserved.</p>
        </footer>
    </div>
</template>

<style scoped>
.eb-garamond {
    font-family: 'EB Garamond', serif;
}
</style>
