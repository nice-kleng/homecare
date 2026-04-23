<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard Dokter" />

    <div class="flex h-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold">Dashboard Dokter</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Selamat datang. Berikut ringkasan validasi dan rekam medis pasien Anda.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border p-6">
                <p class="text-muted-foreground text-sm font-medium">Menunggu Validasi</p>
                <p class="mt-2 text-3xl font-bold">—</p>
            </div>
            <div class="rounded-xl border p-6">
                <p class="text-muted-foreground text-sm font-medium">Total Pasien</p>
                <p class="mt-2 text-3xl font-bold">—</p>
            </div>
        </div>
    </div>
</template>
