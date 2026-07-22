<template>
    <div class="p-4 max-w-6xl mx-auto">
        <div class="max-w-7xl mx-auto h-screen">
            <!-- Background Banner -->
            <div class="relative w-full h-[270px] rounded-[8px] shadow-lg text-white">
                <!-- Clickable Background Area -->
                <div class="absolute inset-0 rounded-[8px] cursor-pointer"
                    :style="backgroundStyle"
                    @click="openImageViewer(user.thumbnail || Background)">
                </div>
                


                <!-- Minimalist Dropdown Button -->
                <div class="absolute bottom-4 right-4 z-20" v-click-outside="closeThumbnailMenu">
                    <button 
                        class="text-black bg-white/95 hover:bg-white rounded-full p-2 cursor-pointer shadow-md flex items-center justify-center transition-all"
                        @click.stop="toggleThumbnailMenu">
                        <ChevronDownIcon class="w-5 h-5 text-gray-700" />
                    </button>

                    <!-- Dropdown Menu -->
                    <div v-if="showThumbnailMenu"
                        class="absolute bottom-full mb-2 right-0 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-30 min-w-[180px]"
                        @click.stop>
                        <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-gray-700 flex items-center gap-2"
                            @click.stop="openImageViewer(user.thumbnail || Background)">
                            <EyeIcon class="w-4 h-4" />
                            Xem ảnh bìa
                        </div>
                        <div v-if="isOwner"
                            class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-gray-700 flex items-center gap-2 border-t"
                            @click.stop="triggerThumbnailPicker(); closeThumbnailMenu()">
                            <PencilIcon class="w-4 h-4" />
                            Thay ảnh bìa
                        </div>
                    </div>
                </div>
                
                <!-- INPUT FILE ẨN Thumbnail -->
                <input type="file" accept="image/*" ref="thumbnailInput" class="hidden"
                    @change="handleThumbnailChange" />

                <!-- Avatar + Name -->
                <div class="absolute -bottom-32 left-10 flex flex-col items-start z-20" @click.stop>
                    <div class="relative" v-click-outside="closeAvatarMenu">
                        <div class="w-[120px] h-[120px] rounded-full overflow-hidden border-2 border-white shadow-md cursor-pointer"
                            @click="toggleAvatarMenu">
                            <img :src="user.avatar_url || defaultAvatar" alt="User Avatar"
                                class="w-full h-full object-cover" />
                        </div>

                        <!-- Button Update Avatar (pencil icon) -->
                        <button 
                            v-if="isOwner"
                            class="absolute bottom-1 right-1 bg-[#4392E0] rounded-full p-2 cursor-pointer shadow"
                            @click="toggleAvatarMenu">
                            <PencilIcon class="w-4 h-4 text-white" />
                        </button>

                        <!-- Avatar Options Menu -->
                        <div v-if="showAvatarMenu"
                            class="absolute top-full mt-2 left-0 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-30 min-w-[180px]">
                            <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-gray-700 flex items-center gap-2"
                                @click="openImageViewer(user.avatar_url || defaultAvatar)">
                                <EyeIcon class="w-4 h-4" />
                                Xem ảnh
                            </div>
                            <div v-if="isOwner"
                                class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-gray-700 flex items-center gap-2"
                                @click="triggerAvatarPicker">
                                <PencilIcon class="w-4 h-4" />
                                Đổi ảnh đại diện
                            </div>
                        </div>

                        <!-- INPUT FILE ẨN Avatar -->
                        <input type="file" accept="image/*" ref="avatarInput" class="hidden"
                            @change="handleAvatarChange" />
                    </div>

                    <div class="flex items-center gap-2 mt-3">
                        <p class="text-[#3E414C] font-semibold text-2xl">{{ user.full_name ?? 'Không rõ' }}</p>

                        <!-- Badge display -->
                        <div v-if="user.badges?.length || user.primary_badge" class="flex items-center gap-1">
                          <BadgeIcon
                            v-for="badge in displayBadges"
                            :key="badge"
                            :badge="badge"
                            size="sm"
                            class="inline-block"
                          />
                        </div>

                        <div v-if="isOwner" class="relative" v-click-outside="closeVisibilityMenu">
                            <span class="px-2 py-1 rounded text-xs font-medium capitalize cursor-pointer" :class="{
                                'bg-green-100 text-green-700': user.visibility === 'open',
                                'bg-yellow-100 text-yellow-700': user.visibility === 'friend-only',
                                'bg-red-100 text-red-700': user.visibility === 'private'
                            }" @click="toggleVisibilityMenu">
                                {{ getVisibilityText(user.visibility) }}
                            </span>
                            <!-- Dropdown Menu -->
                            <div v-if="showVisibilityMenu"
                                class="absolute top-full mt-2 left-0 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-30 min-w-[250px]">
                                <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-green-700 font-medium"
                                    @click="changeVisibility('open')">
                                    Công khai <br>
                                    <span class="text-gray-500 text-xs">Mọi người đều thấy hồ sơ của bạn</span>
                                </div>
                                <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-yellow-700 font-medium"
                                    @click="changeVisibility('friend-only')">
                                    Bạn bè <br>
                                    <span class="text-gray-500 text-xs">Chỉ bạn bè mới thấy hồ sơ của bạn</span>
                                </div>
                                <div class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm text-red-700 font-medium"
                                    @click="changeVisibility('private')">
                                    Riêng tư <br>
                                    <span class="text-gray-500 text-xs">Chỉ bạn mới thấy hồ sơ của bạn</span>
                                </div>
                            </div>
                        </div>
                        <span 
                            v-else
                            class="px-2 py-1 rounded text-xs font-medium capitalize" 
                            :class="{
                                'bg-green-100 text-green-700': user.visibility === 'open',
                                'bg-yellow-100 text-yellow-700': user.visibility === 'friend-only',
                                'bg-red-100 text-red-700': user.visibility === 'private'
                            }">
                            {{ user.visibility }}
                        </span>
                    </div>

                    <div class="text-sm text-[#6B6F80] mt-1 flex items-center gap-2">
                        <div class="flex items-center gap-1">
                            <component :is="maleIcon" class="w-5 h-5" v-if="user.gender === 1"/>
                            <component :is="femaleIcon" class="w-5 h-5" v-else-if="user.gender === 2"/>
                            <ExclamationCircleIcon v-else-if="user.gender === 3" class="w-5 h-5" />
                            <QuestionMarkCircleIcon v-else-if="user.gender === 0" class="w-5 h-5" />
                            <p>{{ user.gender_text }}</p>
                        </div>
                        <div v-if="user.age_group">• {{ user.age_group }}</div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end items-center mt-4 text-[#4392E0] gap-11 mr-10 mb-28"
                :class="{ invisible: isOwner }">
                <div class="flex flex-col items-center cursor-pointer">
                    <StarIcon v-if="user.is_followed == false" class="w-7 h-7 mb-2" />
                    <StarIconSolid v-else class="w-7 h-7 mb-2" />
                    <span class="text-xs font-semibold">{{ user.is_followed ? 'Đã yêu thích' : 'Yêu thích' }}</span>
                </div>
                <div class="flex flex-col items-center cursor-pointer">
                    <CalendarIcon class="w-7 h-7 mb-2" />
                    <span class="text-xs font-semibold">Mời bạn</span>
                </div>
                <div class="flex flex-col items-center cursor-pointer">
                    <ChatBubbleBottomCenterTextIcon class="w-7 h-7 mb-2" />
                    <span class="text-xs font-semibold">Trò chuyện</span>
                </div>
            </div>

            <!-- Sports -->
            <section>
                <div class="flex items-center justify-start mb-4 mx-10">
                    <h2 class="text-xl font-semibold text-gray-800">Môn thể thao</h2>
                    <div
                        v-if="isOwner"
                        class="flex items-center text-sm text-gray-600 ml-4 cursor-pointer hover:text-gray-800 bg-white p-1.5 rounded-full shadow-md"
                        @click="openSportModal">
                        <PencilIcon class="w-4 h-4" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mx-10 mb-10 items-start">
                    <SportLevelCard
                        v-for="(item, index) in mappedSports" :key="index"
                        :icon="item.sport_icon || '/images/basketball.png'"
                        :title="item.sport_name"
                        :subtitle="item.dupr == 0 ? 'PICKI ' + item.vndupr : 'DUPR ' + item.dupr"
                        :selfScore="item.selfScore"
                        :dupr="item.dupr"
                        :vndupr="item.vndupr"
                        :is-open="openIndex === index"
                        @toggle="toggleCard(index)"
                    />
                </div>
            </section>

            <!-- Match History -->
            <section>
                <div class="flex items-center justify-start mb-4 mx-10">
                    <h2 class="text-xl font-semibold text-gray-800">Lịch sử đấu</h2>
                </div>

                <div class="mx-10 mb-10">
                    <MatchHistorySection
                        v-if="user.id"
                        :userId="user.id"
                    />
                </div>
            </section>

            <!-- Clubs -->
            <section>
                <div class="flex items-center justify-start mb-4 mx-10">
                    <h2 class="text-xl font-semibold text-gray-800">Cộng đồng</h2>
                    <div
                        v-if="isOwner"
                        class="flex items-center text-sm text-gray-600 ml-4 cursor-pointer hover:text-gray-800 bg-white p-1.5 rounded-full shadow-md">
                        <PencilIcon class="w-4 h-4" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 mx-10 mb-10 items-center justify-items-center">
                    <div v-for="club in clubs" :key="club.id"
                        class="flex items-center gap-6 hover:bg-gray-100 rounded-md p-4 cursor-pointer"
                        @click="router.push({ name: 'club-detail', params: { id: club.id } })">
                        <div class="flex justify-center items-center gap-4 min-w-0">
                            <img :src="club.logo_url || '/images/default-avatar.png'" alt="" class="w-10 h-10 rounded-full flex-shrink-0">
                            <p class="truncate w-40 text-sm text-[#4392E0] font-semibold" v-tooltip="club.name" :title="club.name">
                                {{ club.name }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-[#4392E0] text-xs text-nowrap">Hoạt động</p>
                            <ChevronRightIcon class="w-4 h-4" />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <SportSelectCard 
            :is-open="showSportModal"
            :my-sports="sports"
            :all-sports="allSports"
            @close="closeSportModal"
            @save="handleSaveSports"
        />

        <!-- Image Viewer Lightbox -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="showImageViewer" 
                    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-md p-4"
                    @click="closeImageViewer">
                    <button 
                        class="absolute top-6 right-6 text-white p-2 hover:bg-white/10 rounded-full transition-colors"
                        @click="closeImageViewer">
                        <XMarkIcon class="w-8 h-8" />
                    </button>
                    
                    <div class="relative max-w-4xl max-h-[90vh] flex items-center justify-center" @click.stop>
                        <img 
                            :src="viewerImageUrl" 
                            alt="User Large Image"
                            class="max-w-full max-h-full object-contain rounded-lg shadow-2xl animate-scale-up border-4 border-white/20" />
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Image Cropper Modal -->
        <ImageCropperModal
            :is-open="showCropper"
            :image="imageToCrop"
            :stencil-props="cropperStencilProps"
            @close="showCropper = false"
            @save="onCropSave"
        />
    </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import Background from "@/assets/images/dashboard-bg.svg?url";
import maleIcon from '@/assets/images/male.svg';
import femaleIcon from '@/assets/images/female.svg';
import { vClickOutside } from "@/directives/clickOutside";
import * as SportService from '@/service/sport';

import {
    CalendarIcon,
    ChevronRightIcon,
    ChevronDownIcon,
    StarIcon as StarIconSolid
} from "@heroicons/vue/24/solid";

import {
    StarIcon,
    ChatBubbleBottomCenterTextIcon,
    PencilIcon,
    ExclamationCircleIcon,
    QuestionMarkCircleIcon,
    EyeIcon,
    XMarkIcon
} from "@heroicons/vue/24/outline";

import SportLevelCard from "@/components/molecules/SportLevelCard.vue";
import SportSelectCard from "@/components/molecules/SportSelectCard.vue";
import ImageCropperModal from "@/components/molecules/ImageCropperModal.vue";
import MatchHistorySection from "@/components/organisms/MatchHistorySection.vue";
import BadgeIcon from "@/components/atoms/BadgeIcon.vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import { useUserStore } from "@/store/auth";
import { storeToRefs } from "pinia";
import { getVisibilityText }  from "@/composables/formatVisibilityText";

const route = useRoute();
const router = useRouter();
const id = route.params.id;
const user = ref({});
const defaultAvatar = "/images/default-avatar.png";

const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);

const sports = ref([]);
const clubs = ref([]);
const openIndex = ref(null);
const showVisibilityMenu = ref(false);
const showAvatarMenu = ref(false);
const showThumbnailMenu = ref(false);
const showImageViewer = ref(false);
const viewerImageUrl = ref('');
const showSportModal = ref(false);
const allSports = ref([]);

// Cropper
const showCropper = ref(false);
const imageToCrop = ref(null);
const cropperType = ref('avatar'); // 'avatar' or 'thumbnail'
const cropperStencilProps = computed(() => {
    if (cropperType.value === 'avatar') {
        return { aspectRatio: 1 };
    }
    return { aspectRatio: 1152 / 270 };
});

// Avatar
const avatarInput = ref(null);
const triggerAvatarPicker = () => avatarInput.value?.click();

// Banner Thumbnail
const thumbnailInput = ref(null);
const triggerThumbnailPicker = () => thumbnailInput.value?.click();

const isOwner = computed(() => user.value.id === getUser.value.id);

const displayBadges = computed(() => {
  if (user.value.badges?.length) {
    const badgeOrder = ['picki', 'champion', 'anchor', 'verified']
    return [...user.value.badges].sort((a, b) =>
      badgeOrder.indexOf(a.toLowerCase()) - badgeOrder.indexOf(b.toLowerCase())
    )
  }
  return []
})

const mappedSports = computed(() =>
    sports.value.map(s => ({
        ...s,
        selfScore: s.scores.personal_score ?? 0,
        dupr: s.scores.dupr_score ?? 0,
        vndupr: s.scores.vndupr_score ?? 0
    }))
);

const backgroundStyle = computed(() => ({
    backgroundImage: `url('${user.value.thumbnail || Background}')`,
    backgroundSize: 'cover',
    backgroundPosition: 'center'
}));

const toggleCard = (index) => {
    openIndex.value = openIndex.value === index ? null : index;
};

const toggleVisibilityMenu = () => {
    showVisibilityMenu.value = !showVisibilityMenu.value;
};

const closeVisibilityMenu = () => {
    showVisibilityMenu.value = false;
};

const toggleAvatarMenu = () => {
    showAvatarMenu.value = !showAvatarMenu.value;
};

const closeAvatarMenu = () => {
    showAvatarMenu.value = false;
};

const openImageViewer = (url) => {
    viewerImageUrl.value = url;
    showImageViewer.value = true;
    showAvatarMenu.value = false;
};

const closeImageViewer = () => {
    showImageViewer.value = false;
};

const toggleThumbnailMenu = () => {
    showThumbnailMenu.value = !showThumbnailMenu.value;
};

const closeThumbnailMenu = () => {
    showThumbnailMenu.value = false;
};

const changeVisibility = async (newVisibility) => {
    try {
        const formData = new FormData();
        formData.append("full_name", user.value.full_name);
        formData.append("visibility", newVisibility);

        await updateInfoUser(formData);
        showVisibilityMenu.value = false;
        toast.success("Cập nhật trạng thái hiển thị thành công!");
    } catch (error) {
        toast.error(error.response?.data?.message || "Lỗi khi cập nhật trạng thái");
    }
};

const openSportModal = async () => {
    if (allSports.value.length === 0) {
        try {
            allSports.value = await SportService.getAllSports();
        } catch (error) {
            toast.error('Không thể tải danh sách môn thể thao');
            return;
        }
    }
    showSportModal.value = true;
};

const closeSportModal = () => {
    showSportModal.value = false;
};

const handleSaveSports = async (sportsData) => {
    try {
        const formData = new FormData();
        formData.append("full_name", user.value.full_name);
        sportsData.sport_ids.forEach((id, index) => {
            formData.append(`sport_ids[${index}]`, id);
        });
        sportsData.score_value.forEach((score, index) => {
            formData.append(`score_value[${index}]`, score);
        });
        await updateInfoUser(formData);
        toast.success("Cập nhật môn thể thao thành công!");
    } catch (error) {
        toast.error(error.response?.data?.message || "Lỗi khi cập nhật môn thể thao");
    }
};

// Fetch User
const fetchDetailUser = async (id) => {
    try {
        const res = await userStore.detailUser(id);
        user.value = res || {};

        if (res) {
            sports.value = res.sports || [];
            clubs.value = res.clubs || [];
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi thực hiện thao tác này');
    }
};

// Update User
const updateInfoUser = async (data) => {
    try {
        await userStore.updateUser(data);
        await fetchDetailUser(id);
    } catch (error) {
        toast.error(error.response?.data?.message || 'Có lỗi xảy ra');
    }
};

// Upload Avatar
const handleAvatarChange = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        imageToCrop.value = e.target.result;
        cropperType.value = 'avatar';
        showCropper.value = true;
        showAvatarMenu.value = false;
    };
    reader.readAsDataURL(file);
};

// Upload Thumbnail (Background)
const handleThumbnailChange = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        imageToCrop.value = e.target.result;
        cropperType.value = 'thumbnail';
        showCropper.value = true;
    };
    reader.readAsDataURL(file);
};

const onCropSave = async (blob) => {
    showCropper.value = false;
    const formData = new FormData();
    formData.append("full_name", user.value.full_name);
    
    if (cropperType.value === 'avatar') {
        formData.append("avatar_url", blob, "avatar.jpg");
        try {
            await updateInfoUser(formData);
            toast.success("Cập nhật ảnh đại diện thành công!");
        } catch (error) {
            toast.error(error.response?.data?.message || "Lỗi khi cập nhật ảnh đại diện");
        }
    } else {
        formData.append("thumbnail", blob, "thumbnail.jpg");
        try {
            await updateInfoUser(formData);
            toast.success("Cập nhật ảnh bìa thành công!");
        } catch (error) {
            toast.error(error.response?.data?.message || "Lỗi khi cập nhật ảnh bìa");
        }
    }
};

onMounted(async () => {
    if (id) await fetchDetailUser(id);
});
</script>

<style scoped>
.bg-red-custom {
    background-size: cover;
    background-position: center;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.animate-scale-up {
    animation: scale-up 0.3s ease-out;
}

@keyframes scale-up {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
```