<script setup>
import {
    reactive,
    computed,
    onMounted,
    ref,
    onBeforeUnmount,
    watch,
    nextTick
} from "vue";
import useVuelidate from "@vuelidate/core";
import { required, helpers } from "@vuelidate/validators";
import Button from "@/components/atoms/Button.vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import {
    ArrowLeftIcon,
    PencilIcon,
    ArrowUpTrayIcon,
    CheckCircleIcon
} from "@heroicons/vue/24/solid";
import * as LocationService from "@/service/location";
import * as SportService from "@/service/sport";
import * as ClubService from "@/service/club";
import { useUserStore } from "@/store/auth";
import { storeToRefs } from "pinia";
import confetti from "canvas-confetti";
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { genderOptions } from '@/data/user/index.js'
import { formatDateForDB, convertToDateObject } from '@/composables/formatDatetime.js'

const userStore = useUserStore();
const { getUser } = storeToRefs(userStore);
const router = useRouter();

// Step control
const currentStep = ref(1); // 1: Profile form, 2: Sports selection, 3: Upload popup, 4: Club selection, 5: Completion (Confetti)

const preview = ref(null);
const selectedFile = ref(null);

const data = reactive({
    avatar_url: getUser.value?.avatar_url || "",
    full_name: getUser.value?.full_name || "",
    location_id: getUser.value?.location_id || "",
    skill_level: "",
    gender: getUser.value?.gender || null,
    date_of_birth: convertToDateObject(getUser.value?.date_of_birth) || "",
    about: getUser.value?.about || "",
    sports: [],
});

const locations = reactive([]);
const sports = reactive([]);
const searchLocation = ref("");
const isLocationDropdownOpen = ref(false);
const locationDropdownRef = ref(null);

const isSkillDropdownOpen = ref(false);
const skillDropdownRef = ref(null);

const defaultAvatar = "/images/default-avatar.png";

// --- Validation ---
const rules = computed(() => ({
    full_name: {
        required: helpers.withMessage("Tên không được để trống", required),
    },
    location_id: {
        required: helpers.withMessage(
            "Thành phố không được để trống",
            required
        ),
    },
    skill_level: {
        required: helpers.withMessage("Vui lòng chọn trình độ", required),
    },
    gender: {
    required: helpers.withMessage("Vui lòng chọn giới tính", required),
    },
    date_of_birth: {
        required: helpers.withMessage("Vui lòng chọn ngày sinh", required),
    }
}));
const v$ = useVuelidate(rules, data);

// --- Location ---
const filteredLocations = computed(() => {
    if (!searchLocation.value) return locations;
    return locations.filter((location) =>
        location.name.toLowerCase().includes(searchLocation.value.toLowerCase())
    );
});

const selectLocation = (location) => {
    data.location_id = location.id;
    searchLocation.value = location.name;
    isLocationDropdownOpen.value = false;
};

// --- Skill ---
const selectSkill = (level) => {
    data.skill_level = level.value;
    isSkillDropdownOpen.value = false;
};

// --- Upload ---
const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        selectedFile.value = file;
        preview.value = URL.createObjectURL(file);
    }
};

const handleDrop = (e) => {
    const file = e.dataTransfer.files[0];
    if (file) {
        selectedFile.value = file;
        preview.value = URL.createObjectURL(file);
    }
};

const cancelUpload = () => {
    currentStep.value = 1;
    preview.value = null;
    selectedFile.value = null;
};

const confirmUpload = () => {
    currentStep.value = 1;
};

// --- Sports Selection ---
const searchSport = ref("");
const showSkillPopup = ref(false);
const selectedSportForSkill = ref(null);

const selectedSports = reactive([]);

const sportSkillLevels = [
    { value: 1, label: "1.0" },
    { value: 1.5, label: "1.5" },
    { value: 2, label: "2.0" },
];

const filteredSports = computed(() => {
    if (!searchSport.value) return sports;
    return sports.filter((sport) =>
        sport.name.toLowerCase().includes(searchSport.value.toLowerCase())
    );
});

const selectSportFromAvailable = (sport) => {
    selectedSportForSkill.value = sport;
    showSkillPopup.value = true;
};

const confirmSkillLevel = (level) => {
    if (selectedSportForSkill.value) {
        // Thêm vào danh sách đã chọn
        selectedSports.push({
            ...selectedSportForSkill.value,
            skillLevel: level,
        });

        // Xóa khỏi danh sách có sẵn
        const index = sports.findIndex(
            (s) => s.id === selectedSportForSkill.value.id
        );
        if (index > -1) {
            sports.splice(index, 1);
        }
    }

    showSkillPopup.value = false;
    selectedSportForSkill.value = null;
};

const cancelSkillPopup = () => {
    showSkillPopup.value = false;
    selectedSportForSkill.value = null;
};

const removeSport = (sport) => {
    // Xóa khỏi danh sách đã chọn
    const index = selectedSports.findIndex((s) => s.id === sport.id);
    if (index > -1) {
        selectedSports.splice(index, 1);

        // Thêm lại vào danh sách có sẵn
        sports.push({
            id: sport.id,
            name: sport.name,
            icon: sport.icon,
        });
    }
};

// --- Club Selection (Step 4) ---
const searchClub = ref("");
const clubs = reactive([]);
const selectedClubs = reactive([]);
const isLoadingClubs = ref(false);

const filteredClubs = computed(() => {
    if (!searchClub.value) return clubs;
    return clubs.filter((club) =>
        club.name.toLowerCase().includes(searchClub.value.toLowerCase())
    );
});

const getClubs = async (searchQuery = "") => {
    try {
        isLoadingClubs.value = true;
        const response = await ClubService.getAllClubs();
        clubs.length = 0;
        clubs.push(...response.data.clubs);
    } catch (error) {
        toast.error(
            error.response?.data?.message || "Không tải được danh sách câu lạc bộ!"
        );
    } finally {
        isLoadingClubs.value = false;
    }
};

const toggleClubSelection = async (club) => {
    const index = selectedClubs.findIndex((c) => c.id === club.id);

    if (index > -1) {
        // Nếu đã chọn thì bỏ chọn (không gọi API leave vì chưa có)
        selectedClubs.splice(index, 1);
        toast.info("Đã bỏ chọn câu lạc bộ");
    } else {
        // Nếu chưa chọn thì join club
        try {
            await ClubService.joinRequest(club.id);
            selectedClubs.push(club);
            toast.success(`Đã gửi yêu cầu tham gia ${club.name}`);
        } catch (error) {
            toast.error(
                error.response?.data?.message || "Không thể tham gia câu lạc bộ!"
            );
        }
    }
};

// --- Submit Step 1 (Profile Form) ---
const goToSportsSelection = () => {
    v$.value.$touch();
    if (v$.value.$invalid) return;

    currentStep.value = 2; // Chuyển sang form chọn môn thể thao
};

// --- Submit Step 2 (Sports Selection) ---
const goToClubSelection = async () => {
    if (selectedSports.length === 0) {
        toast.error("Vui lòng chọn ít nhất một môn thể thao!");
        return;
    }
    currentStep.value = 4; // Chuyển sang form chọn câu lạc bộ
    await getClubs(); // Load danh sách câu lạc bộ
};

const goBackToProfile = () => {
    currentStep.value = 1;
};

const goBackToSports = () => {
    currentStep.value = 2;
};

// --- Submit Final (Step 4) ---
const submitFinalProfile = async () => {
    try {
        const formData = new FormData();
        formData.append("full_name", data.full_name);
        formData.append("location_id", data.location_id);
        formData.append("skill_level", data.skill_level);
        formData.append("about", data.about);
        formData.append("gender", data.gender);
        formData.append("date_of_birth", formatDateForDB(data.date_of_birth));
        formData.append("is_profile_completed", 1);

        if (selectedFile.value) {
            formData.append("avatar_url", selectedFile.value);
        }

        selectedSports.forEach((sport) => {
            formData.append("sport_ids[]", sport.id);
        });

        selectedSports.forEach((sport) => {
            formData.append("score_value[]", sport.skillLevel);
        });

        await userStore.updateUser(formData);

        toast.success("Hồ sơ đã được cập nhật!");
        // --- CHUYỂN SANG STEP 5 (HOÀN TẤT) ---
        currentStep.value = 5;
    } catch (error) {
        toast.error(error.response?.data?.message || "Có lỗi xảy ra!");
    }
};

const confettiCanvas = ref(null);
let confettiInstance = null;

watch(currentStep, (newStep) => {
  if (newStep === 5) {
    // Khi chuyển sang Step 5 thì tạo confetti instance riêng
    nextTick(() => {
      if (confettiCanvas.value) {
        confettiInstance = confetti.create(confettiCanvas.value, {
          resize: true,
          useWorker: true,
        });

        // Bắn confetti giới hạn trong khung
        confettiInstance({
          particleCount: 150,
          spread: 100,
          startVelocity: 30,
          origin: { x: 0.5, y: 0.7 },
          colors: ["#dc2626", "#f59e0b", "#10b981", "#3b82f6"],
        });

        // hiệu ứng bắn nhiều lần cho đẹp
        const duration = 1500;
        const end = Date.now() + duration;

        (function frame() {
          confettiInstance({
            particleCount: 5,
            spread: 60,
            startVelocity: 30,
            origin: { x: Math.random() * 0.3 + 0.35, y: 0.6 },
          });
          if (Date.now() < end) requestAnimationFrame(frame);
        })();
      }
    });
  }
});


const skipClubSelection = () => {
    submitFinalProfile();
};

const goToHomePage = () => {
    router.push("/");
};

watch(preview, (newVal) => {
    data.avatar_url = newVal || "";
});

const getLocations = async () => {
    try {
        const response = await LocationService.getAllLocations();
        locations.push(...response);
        selectLocation(
            locations.find((loc) => loc.id === data.location_id) || {}
        );
    } catch (error) {
        toast.error(
            error.response?.data?.message ||
                "Không tải được danh sách thành phố!"
        );
    }
};

const getSports = async () => {
    try {
        const response = await SportService.getAllSports();
        sports.push(...response);
    } catch (error) {
        toast.error(
            error.response?.data?.message || "Không tải được danh sách môn thể thao!"
        );
    }
};

const handleClickOutside = (event) => {
    if (
        locationDropdownRef.value &&
        !locationDropdownRef.value.contains(event.target)
    ) {
        isLocationDropdownOpen.value = false;
    }
    if (
        skillDropdownRef.value &&
        !skillDropdownRef.value.contains(event.target)
    ) {
        isSkillDropdownOpen.value = false;
    }
};

const skillLevels = [
    { value: "1.0", label: "1.0" },
    { value: "1.5", label: "1.5" },
    { value: "2.0", label: "2.0" },
];

onMounted(async () => {
    await getLocations();
    await getSports();
    document.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center">
        <!-- Popup Upload Ảnh (Step 3) -->
        <div v-if="currentStep === 3"
            class="w-full max-w-md p-6 bg-white rounded-[12px] shadow items-center justify-center">
            <div class="flex items-center mb-4 gap-32">
                <ArrowLeftIcon class="w-6 h-6 cursor-pointer font-semibold hover:text-gray-800" @click="cancelUpload" />
                <h2 class="text-[20px] font-semibold">Ảnh đại diện</h2>
            </div>

            <label for="fileInput"
                class="w-full h-[516px] shadow bg-[#FFF5F5] rounded-lg flex flex-col items-center justify-center transition"
                @dragover.prevent @drop.prevent="handleDrop">
                <template v-if="!preview">
                    <h2 class="font-semibold">Tải lên ảnh đại diện của bạn</h2>
                    <p class="text-gray-500 text-[11px] mb-4">
                        Cộng thêm điểm khi tải lên ảnh bạn đang chơi pickleball
                    </p>
                    <div
                        class="border-2 border-dashed rounded-lg cursor-pointer bg-[#EDEEF2] hover:border-gray-400 transition flex items-center justify-center w-32 h-32">
                        <ArrowUpTrayIcon class="w-6 h-6 text-gray-400" />
                    </div>
                </template>

                <template v-else>
                    <h2 class="font-semibold">Tải lên ảnh đại diện của bạn</h2>
                    <p class="text-gray-500 text-[11px] mb-4">
                        Cộng thêm điểm khi tải lên ảnh bạn đang chơi pickleball
                    </p>
                    <div
                        class="rounded-lg cursor-pointer bg-[#EDEEF2] hover:border-gray-400 transition flex items-center justify-center w-32 h-32 overflow-hidden">
                        <img :src="preview" alt="Preview" class="object-cover w-full h-full" />
                    </div>
                </template>

                <input id="fileInput" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
            </label>

            <div class="flex gap-4 mt-8 w-full">
                <button @click="confirmUpload"
                    class="flex-1 py-2 !bg-primary hover:!bg-secondary text-white rounded transition"
                    :disabled="!preview">
                    Tiếp tục
                </button>
            </div>
        </div>

        <!-- Form Hồ sơ (Step 1) -->
        <div v-else-if="currentStep === 1" class="w-full max-w-md p-6 bg-white rounded-[12px] shadow">
            <form @submit.prevent="goToSportsSelection" class="space-y-4">
                <div class="flex items-center gap-11 mb-4">
                    <ArrowLeftIcon class="w-6 h-6 cursor-pointer font-semibold hover:text-gray-800"
                        @click="router.back()" />
                    <h2 class="text-[20px] font-semibold">
                        Khởi tạo hồ sơ người dùng
                    </h2>
                </div>

                <!-- Avatar -->
                <div class="flex flex-col items-center justify-center">
                    <div class="relative w-20 h-20">
                        <img :src="preview || data.avatar_url || defaultAvatar" alt="Avatar"
                            class="w-20 h-20 rounded-full object-cover border border-gray-300" />
                        <button type="button" @click="currentStep = 3"
                            class="absolute bottom-0 right-0 bg-blue-500 rounded-full cursor-pointer hover:bg-blue-600 transition flex items-center justify-center"
                            style="
                                width: 23px;
                                height: 23px;
                                border: 1px solid white;
                            ">
                            <PencilIcon class="w-3 h-3 text-white" />
                        </button>
                    </div>
                </div>

                <!-- Tên hiển thị -->
                <div>
                    <label for="full_name" class="font-semibold text-[14px]">Tên hiển thị</label>
                    <input id="full_name" type="text" placeholder="Nhập tên hiển thị của bạn" v-model="data.full_name"
                        class="w-full px-4 py-2 my-1 border bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm" />
                    <span v-for="err in v$.full_name.$errors" :key="err.$uid" class="text-red-500 text-sm">
                        {{ err.$message }}
                    </span>
                </div>
                <div>
                    <label class="font-semibold text-[14px]">Giới tính</label>
                    <div class="flex space-x-4 mt-2">
                        <div v-for="option in genderOptions" :key="option.value" class="flex items-center">
                            <input :id="`gender-${option.value}`" type="radio" :value="option.value"
                                v-model="data.gender"
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 cursor-pointer" />
                            <label :for="`gender-${option.value}`"
                                class="ml-2 text-sm font-medium text-gray-900 cursor-pointer">{{ option.label }}</label>
                        </div>
                    </div>
                    <span v-for="err in v$.gender.$errors" :key="err.$uid" class="text-red-500 text-sm">
                        {{ err.$message }}
                    </span>
                </div>

                <div>
                    <label for="date_of_birth" class="font-semibold text-[14px]">Ngày sinh</label>
                    <VueDatePicker v-model="data.date_of_birth"
                        :enable-time-picker="false" :max-date="new Date()" :format="'dd/MM/yyyy'" :auto-apply="true"
                        :placeholder="'Chọn ngày sinh'" class="mt-1" input-class-name="dp-custom-input" />
                    <span v-for="err in v$.date_of_birth.$errors" :key="err.$uid" class="text-red-500 text-sm">
                        {{ err.$message }}
                    </span>
                </div>
                <!-- Thành phố -->
                <div class="relative" ref="locationDropdownRef">
                    <label for="location_id" class="font-semibold text-[14px]">Thành phố</label>
                    <input v-model="searchLocation" @focus="isLocationDropdownOpen = true"
                        @input="isLocationDropdownOpen = true" type="text" placeholder="Nhập tên thành phố"
                        class="w-full px-4 py-2 mt-2 border rounded bg-[#EDEEF2] focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm" />
                    <div v-if="
                            isLocationDropdownOpen &&
                            filteredLocations.length > 0
                        "
                        class="absolute z-50 w-full mt-2 bg-white border border-gray-300 rounded shadow-lg max-h-60 overflow-y-auto">
                        <div v-for="location in filteredLocations" :key="location.id" @click="selectLocation(location)"
                            class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" :class="{
                                'bg-blue-50': data.location_id === location.id,
                            }">
                            {{ location.name }}
                        </div>
                    </div>
                    <span v-for="err in v$.location_id.$errors" :key="err.$uid" class="text-red-500 text-sm">
                        {{ err.$message }}
                    </span>
                </div>

                <!-- Kỹ năng -->
                <div class="relative" ref="skillDropdownRef">
                    <label for="skill_level" class="font-semibold text-[14px]">Đánh giá kỹ năng</label>
                    <div @click="isSkillDropdownOpen = !isSkillDropdownOpen"
                        class="w-full px-4 py-2 mt-1 border rounded cursor-pointer flex items-center bg-[#EDEEF2] justify-between">
                        <span :class="
                                data.skill_level
                                    ? 'text-gray-900'
                                    : 'text-gray-400 text-sm'
                            ">
                            {{
                            data.skill_level
                            ? skillLevels.find(
                            (l) => l.value === data.skill_level
                            )?.label
                            : "Kỹ năng chơi của bạn"
                            }}
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>

                    <div v-if="isSkillDropdownOpen"
                        class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded shadow-lg max-h-60 overflow-y-auto">
                        <div v-for="level in skillLevels" :key="level.value" @click="selectSkill(level)"
                            class="flex items-center justify-between px-4 py-2 cursor-pointer hover:bg-gray-100">
                            <span>{{ level.label }}</span>
                            <input type="radio" name="skill_level" :value="level.value" v-model="data.skill_level"
                                class="w-4 h-4 cursor-pointer" @click.stop />
                        </div>
                    </div>
                    <span v-for="err in v$.skill_level?.$errors || []" :key="err.$uid" class="text-red-500 text-sm">
                        {{ err.$message }}
                    </span>
                </div>

                <!-- Mô tả -->
                <div>
                    <div class="flex items-center justify-between">
                        <label for="about" class="font-semibold text-[14px]">Giới thiệu bản thân</label>
                        <span class="text-[11px] text-gray-500">
                            {{ data.about?.length || 0 }}/300
                        </span>
                    </div>
                    <textarea id="about" v-model="data.about" maxlength="300"
                        class="w-full px-4 py-2 mt-1 border rounded focus:outline-none bg-[#EDEEF2] focus:ring-2 focus:ring-blue-500 placeholder:text-sm resize-none"
                        rows="4" placeholder="Hãy chia sẻ một chút về bạn"></textarea>
                </div>

                <Button type="submit" class="w-full !bg-primary hover:!bg-secondary text-white"
                    style="margin-top: 60px">
                    Lưu thay đổi
                </Button>
            </form>
        </div>

        <!-- Form Chọn môn thể thao (Step 2) -->
        <div v-else-if="currentStep === 2" class="w-full max-w-md p-6 bg-white rounded-[12px] shadow">
            <!-- Popup chọn kỹ năng -->
            <div v-if="showSkillPopup"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                @click.self="cancelSkillPopup">
                <div class="bg-white rounded-lg p-6 w-80 shadow-lg">
                    <h3 class="text-lg font-semibold mb-4 text-center">
                        Chọn trình độ {{ selectedSportForSkill?.name }}
                    </h3>
                    <div class="grid grid-cols-3 gap-3">
                        <button v-for="level in sportSkillLevels" :key="level.value"
                            @click="confirmSkillLevel(level.value)"
                            class="py-3 px-4 border-[1.5px] border-gray-300 rounded-lg hover:border-primary hover:bg-red-50 transition font-semibold">
                            {{ level.label }}
                        </button>
                    </div>
                    <button @click="cancelSkillPopup"
                        class="w-full mt-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Hủy
                    </button>
                </div>
            </div>

            <!-- Header -->
            <div class="flex items-center gap-11 mb-6">
                <ArrowLeftIcon class="w-6 h-6 cursor-pointer font-semibold hover:text-gray-800"
                    @click="goBackToProfile" />
                <h2 class="text-[20px] font-semibold">Chọn môn thể thao</h2>
            </div>

            <!-- Search -->
            <input v-model="searchSport" type="text" placeholder="Tìm kiếm..."
                class="w-full px-4 py-2 mb-4 border bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm" />

            <!-- Môn thể thao đã chọn -->
            <div v-if="selectedSports.length > 0" class="mb-6">
                <h3 class="text-sm font-medium text-gray-600 mb-3">
                    Môn thể thao của tôi • {{ selectedSports.length }}
                </h3>
                <div class="grid grid-cols-4 gap-3 pb-[15px]">
                    <div v-for="sport in selectedSports" :key="sport.id" @click="removeSport(sport)"
                        class="flex flex-col items-center justify-center px-2 py-4 rounded-lg cursor-pointer transition bg-primary text-white hover:bg-red-700">
                        <div class="text-3xl mb-2">
                            <img :src="sport.icon || '/images/basketball.png'" class="filter-invert-white" alt="" />
                        </div>
                        <div class="font-semibold text-sm">
                            {{ sport.name }}
                        </div>
                        <div class="text-[9px] mt-1 opacity-90">
                            Người mới / {{ sport.skillLevel }}
                        </div>
                    </div>
                </div>
                <hr>
            </div>

            <!-- Những môn khác -->
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-600 mb-3">
                    Những môn khác • {{ filteredSports.length }}
                </h3>
                <div class="min-h-[200px]">
                    <div v-if="filteredSports.length === 0"
                        class="flex items-center justify-center h-[200px] text-gray-500">
                        <p>Không tìm thấy môn thể thao nào</p>
                    </div>
                    <div v-else class="grid grid-cols-4 gap-3">
                        <div v-for="sport in filteredSports" :key="sport.id" @click="selectSportFromAvailable(sport)"
                            class="flex flex-col items-center justify-center px-2 py-4 rounded-lg cursor-pointer transition bg-white border border-gray-200 hover:border-primary">
                            <div class="text-2xl mb-1">
                                <img :src="sport.icon || '/images/basketball.png'" alt="" />
                            </div>
                            <div class="text-xs font-medium text-center">
                                {{ sport.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mb-4">
                <p class="text-sm text-gray-600 mb-1">
                    Không tìm thấy môn yêu thích của bạn?
                </p>
                <a href="#" class="text-sm text-blue-500 hover:underline">Yêu cầu môn thể thao</a>
            </div>

            <!-- Submit Button -->
            <Button @click="goToClubSelection" class="w-full !bg-primary hover:!bg-secondary text-white">
                Tiếp tục
            </Button>
        </div>

        <!-- Form Chọn câu lạc bộ (Step 4) -->
        <div v-else-if="currentStep === 4" class="w-full max-w-md p-6 bg-white rounded-[12px] shadow">
            <!-- Header -->
            <div class="flex items-center mb-6">
                <ArrowLeftIcon class="w-6 h-6 cursor-pointer font-semibold hover:text-gray-800 mr-4"
                    @click="goBackToSports" />
                <h2 class="text-[20px] font-semibold flex-1 text-center">
                    Tìm câu lạc bộ
                </h2>
                <button @click="skipClubSelection" class="text-sm text-gray-600 hover:text-gray-800">
                    Bỏ qua
                </button>
            </div>

            <!-- Search -->
            <input v-model="searchClub" type="text" placeholder="Nhập tên hoặc mã của câu lạc bộ"
                class="w-full px-4 py-2 mb-4 border bg-[#EDEEF2] rounded focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder:text-sm" />

            <!-- Club List Container - Fixed Height -->
            <div class="min-h-[400px] mb-6">
                <!-- Loading State -->
                <div v-if="isLoadingClubs" class="flex items-center justify-center h-[400px]">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary"></div>
                </div>

                <!-- Empty State -->
                <div v-else-if="filteredClubs.length === 0"
                    class="flex items-center justify-center h-[400px] text-gray-500">
                    <p>Không tìm thấy câu lạc bộ nào</p>
                </div>

                <!-- Club List -->
                <div v-else class="space-y-3 max-h-[400px] overflow-y-auto">
                    <div v-for="club in filteredClubs" :key="club.id"
                        class="flex items-center justify-between p-3 bg-[#F9F9F9] rounded-lg hover:bg-gray-100 transition">
                        <div class="flex items-center gap-3">
                            <img :src="club.logo_url || '/images/default-avatar.png'" alt=""
                                class="w-12 h-12 rounded-full object-cover border-2 border-gray-200" />
                            <div>
                                <div class="font-semibold text-sm">
                                    {{ club.name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    Số thành viên: {{ club.quantity_members || 0 }}
                                </div>
                            </div>
                        </div>
                        <button @click="toggleClubSelection(club)" :class="[
                                'px-3 py-1 rounded-md text-sm font-medium transition',
                                selectedClubs.some((c) => c.id === club.id)
                                    ? 'bg-gray-300 text-gray-700 hover:bg-gray-400'
                                    : 'bg-[#4392E0] text-white hover:bg-[#1E5BB8]',
                            ]">
                            {{
                            selectedClubs.some((c) => c.id === club.id)
                            ? "Đã gửi yêu cầu"
                            : "Tham gia"
                            }}
                        </button>
                    </div>
                </div>
            </div>
            <!-- Thay vì dùng Button component -->
            <button
                @click="submitFinalProfile"
                :disabled="isSubmitting"
                class="w-full py-2 bg-primary hover:bg-secondary text-white rounded disabled:opacity-50"
            >
                {{ isSubmitting ? "Đang xử lý..." : "Hoàn Tất" }}
            </button>
        </div>

        <div v-else-if="currentStep === 5"
            class="relative w-full max-w-md p-6 bg-white rounded-[12px] shadow flex flex-col items-center text-center overflow-hidden"
            ref="step5Container">
            <!-- Canvas confetti -->
            <canvas ref="confettiCanvas" class="absolute inset-0 pointer-events-none z-0 w-full h-full"></canvas>

            <div class="w-full relative z-10 flex flex-col items-center justify-center h-full">
                <div class="w-20 h-20 flex items-center justify-center rounded-full bg-green-100 mb-6">
                    <CheckCircleIcon class="w-12 h-12 text-green-500" />
                </div>

                <h2 class="text-2xl font-bold text-gray-800 mb-2">Hoàn tất hồ sơ</h2>

                <p class="text-gray-600 mb-8">
                    Bạn đã khởi tạo hồ sơ thành công!
                </p>

                <Button @click="goToHomePage" class="w-full !bg-primary hover:!bg-secondary text-white">
                    Quay về trang chủ
                </Button>
            </div>
        </div>

    </div>
</template>

<style scoped>
.max-h-60::-webkit-scrollbar {
    width: 6px;
}
.max-h-60::-webkit-scrollbar-track {
    background: transparent;
}
.max-h-60::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.max-h-\[400px\]::-webkit-scrollbar {
    width: 6px;
}
.max-h-\[400px\]::-webkit-scrollbar-track {
    background: transparent;
}
.max-h-\[400px\]::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.bg-primary {
    background-color: #dc2626;
}
.hover\:bg-secondary:hover {
    background-color: #b91c1c;
}
.border-primary {
    border-color: #dc2626;
}
.filter-invert-white {
  filter: invert(1) grayscale(100%) brightness(200%) contrast(150%);
}
.confetti-canvas {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  width: 100%;
  height: 100%;
}

</style>
