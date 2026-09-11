<template>
  <div v-if="activeSponsors.length > 0" class="w-full">
    <!-- Trường hợp chỉ có 1 nhà tài trợ: Hiển thị đúng 1 ảnh duy nhất, căn giữa gọn gàng, tuyệt đối không lặp ảnh -->
    <div v-if="activeSponsors.length === 1" class="flex items-center justify-center py-2">
      <component
        :is="activeSponsors[0].website_url ? 'a' : 'div'"
        :href="activeSponsors[0].website_url || undefined"
        :target="activeSponsors[0].website_url ? '_blank' : undefined"
        :rel="activeSponsors[0].website_url ? 'noopener noreferrer' : undefined"
        class="flex items-center gap-3 select-none transition-opacity duration-200"
        :class="activeSponsors[0].website_url ? 'cursor-pointer hover:opacity-75' : 'cursor-default'"
        :title="activeSponsors[0].name || ''"
      >
        <!-- Logo Dương bản (Nền sáng) -->
        <img
          :src="getLogoUrl(activeSponsors[0].logo_url)"
          :alt="activeSponsors[0].name || 'Logo'"
          class="h-9 md:h-11 w-auto max-w-[280px] object-contain"
          :class="activeSponsors[0].logo_dark_url ? 'block dark:hidden' : 'block'"
          loading="lazy"
        />
        <!-- Logo Âm bản (Nền tối / Dark Mode) -->
        <img
          v-if="activeSponsors[0].logo_dark_url"
          :src="getLogoUrl(activeSponsors[0].logo_dark_url)"
          :alt="activeSponsors[0].name || 'Logo'"
          class="h-9 md:h-11 w-auto max-w-[280px] object-contain hidden dark:block"
          loading="lazy"
        />
        <span
          v-if="activeSponsors[0].name"
          class="text-xs font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap"
        >
          {{ activeSponsors[0].name }}
        </span>
      </component>
    </div>

    <!-- Trường hợp có từ 2 nhà tài trợ trở lên: Chạy dải marquee ngang mượt mà -->
    <div v-else class="relative overflow-hidden marquee-mask group py-1">
      <div class="flex w-max">
        <!-- Track 1 -->
        <div class="marquee-track flex items-center shrink-0 min-w-full justify-around gap-16 px-8">
          <component
            :is="sponsor.website_url ? 'a' : 'div'"
            v-for="(sponsor, index) in activeSponsors"
            :key="`t1-${sponsor.id}-${index}`"
            :href="sponsor.website_url || undefined"
            :target="sponsor.website_url ? '_blank' : undefined"
            :rel="sponsor.website_url ? 'noopener noreferrer' : undefined"
            class="flex items-center gap-3 shrink-0 select-none transition-opacity duration-200"
            :class="sponsor.website_url ? 'cursor-pointer hover:opacity-75' : 'cursor-default'"
            :title="sponsor.name || ''"
          >
            <!-- Logo Dương bản (Nền sáng) -->
            <img
              :src="getLogoUrl(sponsor.logo_url)"
              :alt="sponsor.name || 'Logo'"
              class="h-8 md:h-10 w-auto max-w-[260px] object-contain"
              :class="sponsor.logo_dark_url ? 'block dark:hidden' : 'block'"
              loading="lazy"
            />
            <!-- Logo Âm bản (Nền tối / Dark Mode) -->
            <img
              v-if="sponsor.logo_dark_url"
              :src="getLogoUrl(sponsor.logo_dark_url)"
              :alt="sponsor.name || 'Logo'"
              class="h-8 md:h-10 w-auto max-w-[260px] object-contain hidden dark:block"
              loading="lazy"
            />
            <span
              v-if="sponsor.name"
              class="text-xs font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap"
            >
              {{ sponsor.name }}
            </span>
          </component>
        </div>

        <!-- Track 2 (chạy tiếp nối mượt mà) -->
        <div class="marquee-track flex items-center shrink-0 min-w-full justify-around gap-16 px-8" aria-hidden="true">
          <component
            :is="sponsor.website_url ? 'a' : 'div'"
            v-for="(sponsor, index) in activeSponsors"
            :key="`t2-${sponsor.id}-${index}`"
            :href="sponsor.website_url || undefined"
            :target="sponsor.website_url ? '_blank' : undefined"
            :rel="sponsor.website_url ? 'noopener noreferrer' : undefined"
            class="flex items-center gap-3 shrink-0 select-none transition-opacity duration-200"
            :class="sponsor.website_url ? 'cursor-pointer hover:opacity-75' : 'cursor-default'"
            :title="sponsor.name || ''"
          >
            <!-- Logo Dương bản (Nền sáng) -->
            <img
              :src="getLogoUrl(sponsor.logo_url)"
              :alt="sponsor.name || 'Logo'"
              class="h-8 md:h-10 w-auto max-w-[260px] object-contain"
              :class="sponsor.logo_dark_url ? 'block dark:hidden' : 'block'"
              loading="lazy"
            />
            <!-- Logo Âm bản (Nền tối / Dark Mode) -->
            <img
              v-if="sponsor.logo_dark_url"
              :src="getLogoUrl(sponsor.logo_dark_url)"
              :alt="sponsor.name || 'Logo'"
              class="h-8 md:h-10 w-auto max-w-[260px] object-contain hidden dark:block"
              loading="lazy"
            />
            <span
              v-if="sponsor.name"
              class="text-xs font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap"
            >
              {{ sponsor.name }}
            </span>
          </component>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  sponsors: {
    type: Array,
    default: () => [],
  },
});

const getLogoUrl = (url) => {
  if (!url) return '';
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }
  const storageBase = import.meta.env.VITE_STORAGE_URL || '/storage/';
  return `${storageBase.replace(/\/$/, '')}/${url.replace(/^\//, '')}`;
};

const activeSponsors = computed(() => {
  return (props.sponsors || []).filter(s => s.is_active !== false);
});
</script>

<style scoped>
.marquee-mask {
  mask-image: linear-gradient(
    to right,
    transparent 0%,
    black 4%,
    black 96%,
    transparent 100%
  );
  -webkit-mask-image: linear-gradient(
    to right,
    transparent 0%,
    black 4%,
    black 96%,
    transparent 100%
  );
}

.marquee-track {
  animation: marquee-scroll 22s linear infinite;
  will-change: transform;
}

.group:hover .marquee-track {
  animation-play-state: paused;
}

@keyframes marquee-scroll {
  from {
    transform: translateX(0%);
  }
  to {
    transform: translateX(-100%);
  }
}
</style>
