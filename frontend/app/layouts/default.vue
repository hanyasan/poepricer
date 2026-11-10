<script setup lang="ts">

import Footer from '~/components/base/Footer.vue'
import Header from  '~/components/base/Header.vue'

</script>

<template>
  <div class="min-h-screen">
    <Header/>
    <UContainer class="py-6">
      <slot />
    </UContainer>
    <Footer/>
  </div>
</template>
