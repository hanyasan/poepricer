<template>
  <div class="min-h-screen">
    <header class="border-b border-default">
      <UContainer class="flex h-14 items-center justify-between">
        <NuxtLink to="/" class="font-semibold">PoE Pricer</NuxtLink>
        <nav class="flex items-center gap-4 text-sm">
          <NuxtLink to="/">Лиги</NuxtLink>
          <NuxtLink to="/about/metrics">О метриках</NuxtLink>
          <UColorModeButton />
        </nav>
      </UContainer>
    </header>
    <UContainer class="py-6">
      <slot />
    </UContainer>
  </div>
</template>
