<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { Check, Minus } from 'lucide-vue-next'

const props = defineProps<{
  checked?: boolean | 'indeterminate'
  class?: HTMLAttributes['class']
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:checked': [checked: boolean]
}>()

function handleChange(event: Event) {
  const target = event.target as HTMLInputElement
  emit('update:checked', target.checked)
}
</script>

<template>
  <div
    :class="cn(
      'relative flex items-center justify-center size-4 shrink-0 rounded-[4px] border border-input shadow-xs transition-colors',
      'focus-within:ring-[3px] focus-within:border-ring focus-within:ring-ring/50',
      (checked === true || checked === 'indeterminate') ? 'bg-primary border-primary text-primary-foreground' : 'bg-background',
      disabled ? 'cursor-not-allowed opacity-50' : '',
      props.class
    )"
  >
    <input
      type="checkbox"
      :checked="checked === true"
      :disabled="disabled"
      class="absolute inset-0 opacity-0 w-full h-full cursor-pointer z-10 m-0 p-0"
      @change="handleChange"
      @click.stop
    />
    <div class="pointer-events-none z-0 flex items-center justify-center text-current">
      <Check v-if="checked === true" class="size-3.5" stroke-width="3" />
      <Minus v-else-if="checked === 'indeterminate'" class="size-3.5" stroke-width="3" />
    </div>
  </div>
</template>
