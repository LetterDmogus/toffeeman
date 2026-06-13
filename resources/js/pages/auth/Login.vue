<script setup lang="ts">
import { Form, Head, Link } from "@inertiajs/vue3";
import AppLogoIcon from "@/components/AppLogoIcon.vue";
import InputError from "@/components/InputError.vue";
import PasswordInput from "@/components/PasswordInput.vue";
import TextLink from "@/components/TextLink.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { register } from "@/routes";
import { store } from "@/routes/login";
import { request } from "@/routes/password";

defineOptions({
	layout: (page: any) => page,
});

defineProps<{
	status?: string;
	canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen flex flex-col items-center justify-center bg-[#faf8f5] text-amber-950 eb-garamond p-6">
        <!-- Main Form Card Container (Single Container/Card) -->
        <div class="w-full max-w-md border border-amber-900/10 bg-white rounded-2xl p-8 shadow-xl shadow-amber-900/5 space-y-6">
            <!-- Header/Brand -->
            <div class="text-center space-y-2 flex flex-col items-center">
                <Link href="/" class="flex flex-col items-center gap-2 font-sans group">
                    <AppLogoIcon class="h-9 w-9 fill-current text-amber-950" />
                    <span class="text-2xl font-bold tracking-tight text-amber-950">Toffeeman</span>
                </Link>
                <h1 class="text-2xl font-bold font-serif text-neutral-900 mt-2">Selamat Datang Kembali</h1>
                <p class="text-sm text-neutral-600 font-sans">
                    Masukkan email dan password Anda untuk masuk ke aplikasi.
                </p>
            </div>

            <div
                v-if="status"
                class="mb-4 text-center text-sm font-medium text-green-600 font-sans"
            >
                {{ status }}
            </div>

            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-5 font-sans animate-in fade-in-0 duration-200"
            >
                <div class="grid gap-2">
                    <Label for="email" class="text-sm font-semibold text-neutral-800">Email Address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                        class="bg-background border-slate-200 focus:border-amber-900 focus:ring-amber-900"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-sm font-semibold text-neutral-800">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-xs text-amber-900 hover:text-amber-700 font-medium"
                            :tabindex="5"
                        >
                            Lupa Password?
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password Anda"
                        class="bg-background border-slate-200 focus:border-amber-900 focus:ring-amber-900"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-2.5 cursor-pointer">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span class="text-sm text-neutral-600">Ingat saya</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full bg-amber-950 hover:bg-neutral-800 text-[#faf8f5] font-bold h-11 rounded-xl transition duration-200"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Masuk
                </Button>

                <div class="text-center text-sm text-neutral-600">
                    Belum punya akun?
                    <TextLink :href="register()" :tabindex="5" class="text-amber-900 hover:text-amber-700 font-semibold ml-1">Daftar Sekarang</TextLink>
                </div>
            </Form>
        </div>
    </div>
</template>
