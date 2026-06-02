<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { PanelLeftClose, PanelLeftOpen } from "lucide-vue-next"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"
import { ref, onMounted } from "vue"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { isMobile, state, toggleSidebar } = useSidebar()

const isMounted = ref(false)
onMounted(() => {
  isMounted.value = true
})
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <template v-if="isMounted">
      <PanelLeftOpen v-if="isMobile || state === 'collapsed'" />
      <PanelLeftClose v-else />
    </template>
    <template v-else>
      <PanelLeftClose />
    </template>
    <span class="sr-only">Toggle sidebar</span>
  </Button>
</template>
