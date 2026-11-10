<script setup lang="ts">
const { t } = useI18n()
const siteUrl = useSiteConfig().url
const ogImage = `${siteUrl}/og-default.png`

// Defaults of the pages: the picture of a shared link is the logo; a page sets its own
// title and description.
useSeoMeta({
  ogType: 'website',
  ogImage,
  ogImageWidth: 1200,
  ogImageHeight: 630,
  ogImageAlt: () => t('seo.home.title'),
  twitterCard: 'summary_large_image',
  twitterImage: ogImage,
})
</script>

<template>
  <!-- An open select or menu locks the scroll of the page and pads the body by the width of the
       scrollbar. The scrollbar is always there (`overflow-y: scroll` in main.css), so no padding is needed. -->
  <UApp :scroll-body="{ padding: 0, margin: 0 }">
    <NuxtLoadingIndicator color="var(--ui-primary)" :height="3" />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <ScrollTopButton />
  </UApp>
</template>
