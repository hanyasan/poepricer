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
  <UApp>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
