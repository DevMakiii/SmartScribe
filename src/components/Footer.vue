<template>
  <footer class="p-4 bg-gray-800 text-gray-400 flex justify-between items-center text-sm">
    <div class="flex space-x-4">
      <a
        v-for="link in links"
        :key="link.text"
        :href="link.href"
        class="hover:text-white transition-colors"
      >
        {{ link.text }}
      </a>
    </div>
    <div>{{ copyright }}</div>
  </footer>
</template>

<script>
export default {
  name: 'AppFooter',
  props: {
    links: {
      type: Array,
      default: () => [
        { text: 'Docs', href: '#' },
        { text: 'Guides', href: '#' },
        { text: 'Help', href: '#' },
        { text: 'Contact', href: '#' }
      ]
    },
    copyright: {
      type: String,
      default: '© 2025 SmartScribe Inc.'
    }
  }
}
</script>