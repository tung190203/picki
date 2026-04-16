<template src="./MiniTournamentDetail.html"></template>

<script>
import {computed, onMounted, ref} from 'vue'
import { ArrowTrendingUpIcon, ChevronRightIcon, LinkIcon, LockClosedIcon, LockOpenIcon, PaperAirplaneIcon, PhotoIcon, QrCodeIcon } from '@heroicons/vue/24/solid'
import {
    CalendarDaysIcon,
    MapPinIcon,
    CircleStackIcon,
    UserIcon,
    PencilIcon,
    XCircleIcon,
    UserGroupIcon as UserMultiple,
    UsersIcon,
    CreditCardIcon,
    ClipboardDocumentCheckIcon,
    FaceSmileIcon,
    MegaphoneIcon,
    CheckIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline'
import UserCard from '@/components/molecules/UserCard.vue'
import InviteGroup from '@/components/molecules/InviteGroup.vue'
import * as MiniTournamnetService from '@/service/miniTournament.js'
import * as MiniParticipantService from '@/service/miniParticipant.js'
import * as ClubService from '@/service/club.js'
import { useRoute, useRouter } from 'vue-router'
import ShareAction from '@/components/molecules/ShareAction.vue'
import { formatEventDate } from '@/composables/formatDatetime.js'
import { TABS } from '@/data/mini/index.js'
import debounce from "lodash.debounce";
import {toast} from "vue3-toastify";
import {storeToRefs} from "pinia";
import { useUserStore } from '@/store/auth';
import * as MiniTournamentStaffService from '@/service/miniTournamentStaff.js';
import QRcodeModal from '@/components/molecules/QRcodeModal.vue';
import DeleteConfirmationModal from '@/components/molecules/DeleteConfirmationModal.vue';
import CancelRecurrenceModal from '@/components/molecules/CancelRecurrenceModal.vue';
import MiniMatchScheduleTab from '@/components/molecules/mini-match-schedule-tab/MiniMatchScheduleTab.vue'
import ChatFormMiniTournament from '@/components/organisms/ChatFormMiniTournament.vue'
import PromotionModal from '@/components/organisms/PromotionModal.vue'
import MiniTournamentPaymentModal from '@/components/pages/mini-tournament/partials/MiniTournamentPaymentModal.vue'
import MiniTournamentSubmitReceiptModal from '@/components/pages/mini-tournament/partials/MiniTournamentSubmitReceiptModal.vue'
import AddGuestModal from '@/components/pages/mini-tournament/partials/AddGuestModal.vue'
import DeleteStaffModal from '@/components/molecules/DeleteStaffModal.vue'
import MemberActionModal from '@/components/molecules/MemberActionModal.vue'
import {
    markMiniParticipantCheckIn,
    markMiniParticipantAbsent,
    selfCheckInMini,
    selfMarkAbsentMini,
} from '@/service/miniParticipant.js'

export default {
    name: 'MiniTournamentDetail',
    components: {
        ArrowTrendingUpIcon,
        ChevronRightIcon,
        LinkIcon,
        LockClosedIcon,
        LockOpenIcon,
        PaperAirplaneIcon,
        PhotoIcon,
        QrCodeIcon,
        CalendarDaysIcon,
        MapPinIcon,
        CircleStackIcon,
        PencilIcon,
        XCircleIcon,
        UserMultiple,
        UserIcon,
        UsersIcon,
        CreditCardIcon,
        ClipboardDocumentCheckIcon,
        FaceSmileIcon,
        MiniMatchScheduleTab,
        QRcodeModal,
        UserCard,
        InviteGroup,
        ShareAction,
        DeleteConfirmationModal,
        CancelRecurrenceModal,
        ChatFormMiniTournament,
        PromotionModal,
        MiniTournamentPaymentModal,
        MiniTournamentSubmitReceiptModal,
        AddGuestModal,
        MegaphoneIcon,
        MemberActionModal
    },

    setup() {
        const route = useRoute()
        const router = useRouter()
        const userStore = useUserStore()
        const { getUser } = storeToRefs(userStore)
        const id = route.params.id
        const mini = ref([])
        const activeTab = ref('detail')
        const subActiveTab = ref('ranking')
        const autoApprove = ref(false)
        const showInviteModal = ref(false)
        const showCreateMatchModal = ref(false)
        const tabs = TABS
        const searchQuery = ref('')
        const inviteType = ref('participant')
        const activeScope = ref('all');
        const inviteGroupData = ref([]);
        const selectedClub = ref(null);
        const clubs = ref([])
        const currentRadius = ref(10);
        const showQRCodeModal = ref(false);
        const miniTournamentLink = window.location.href;
        const descriptionModel = ref('');
        const isEditingDescription = ref(false);
        const showDeleteModal = ref(false);
        const showCancelRecurrenceModal = ref(false);
        const isPromotionModalOpen = ref(false);
        const currentParticipant = ref(null)
        const isUserConfirmed = ref(false)
        const showDelineMiniParticipantModal = ref(false)
        const showPaymentModal = ref(false)
        const showSubmitPaymentModal = ref(false)
        const showAddGuestModal = ref(false)
        const showDeleteStaffModal = ref(false)
        const deleteStaffData = ref({
            staffId: null,
            guarantor: null,
            guests: [],
            candidates: [],
        })

        const isDescriptionChanged = computed(() => {
            return descriptionModel.value !== mini.value.description;
        });

        const setupDescription = () => {
            descriptionModel.value = mini.value.description || '';
            isEditingDescription.value = true;
        };

        const handleInvite = async (user) => {
            if (inviteType.value === 'staff') {
                await inviteStaff(user.id);
            } else {
                await invite(user.id);
            }
            await detailMiniTournament(id);
        }

        const copyLink = () => {
            if (navigator.share) {
                navigator.share({
                    title: 'Hãy tham gia kèo đấu ' + mini.value.name + ' của tôi!',
                    url: miniTournamentLink
                }).then(() => {
                    toast.success('Đã sao chép link kèo đấu vào clipboard!');
                }).catch(console.error);
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(miniTournamentLink).then(() => {
                    toast.success('Đã sao chép link kèo đấu vào clipboard!');
                }).catch(console.error);
            } else {
                alert(`Link kèo đấu: ${miniTournamentLink}`);
            }
        }

        const onRadiusChange = debounce(async (radius) => {
            currentRadius.value = radius;
            await getInviteGroupData();
        }, 300);

        const goToEditPage = () => {
            router.push({
                name: 'edit-mini-tournament',
                params: { id: mini.value.id }
            });
        };

        const getUserScore = (user) => {
            if (!user?.sports?.length || !mini.value?.sport?.id) {
                return '0'
            }

            const matchedSport = user.sports.find(s => s.sport_id === mini.value.sport.id)

            if (!matchedSport?.scores) {
                return '0'
            }

            const scores = matchedSport.scores
            if (scores.vndupr_score) {
                return parseFloat(scores.vndupr_score).toFixed(1)
            }

            if (scores.personal_score) {
                return parseFloat(scores.personal_score).toFixed(1)
            }

            return '0'
        }

        const detailMiniTournament = async (id) => {
            try {
                const response = await MiniTournamnetService.getMiniTournamentById(id)
                mini.value = response
                autoApprove.value = response.auto_approve

                if (response.description) {
                    isEditingDescription.value = true;
                }
                descriptionModel.value = response.description || '';
            } catch (error) {
                console.error('Error fetching mini tournament:', error)
            }
        }

        const updateMiniTournament = async (id, payload) => {
            try {
                const formData = new FormData()
                Object.entries(payload).forEach(([key, raw]) => {
                    if (raw === undefined || raw === null) return

                    // Recurring schedule: expand thành các field con như phía clubs
                    if (key === 'recurring_schedule' && typeof raw === 'object') {
                        const value = raw || {}
                        if (value.period) {
                            formData.append('recurring_schedule[period]', value.period)
                        }
                        if (Array.isArray(value.week_days) && value.week_days.length) {
                            value.week_days.forEach((day, index) => {
                                formData.append(`recurring_schedule[week_days][${index}]`, day)
                            })
                        }
                        if (value.recurring_date) {
                            formData.append('recurring_schedule[recurring_date]', value.recurring_date)
                        }
                        return
                    }

                    let value = raw
                    if (typeof value === 'boolean') {
                        value = value ? '1' : '0'
                    }
                    formData.append(key, value)
                })

                await MiniTournamnetService.updateMiniTournament(id, formData)
                toast.success('Cập nhật thông tin kèo đấu thành công!')
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi cập nhật thông tin giải đấu.')
            }
        }

        const toggleAutoApprove = debounce(async () => {
            autoApprove.value = !autoApprove.value
            await updateAutoApprove(autoApprove.value)
        }, 300)

        const updateAutoApprove = async (value) => {
            try {
                const update = await baseSetColumnUpdateMiniTournament()
                update.auto_approve = value
                await updateMiniTournament(mini.value.id, update)
                autoApprove.value = value
            } catch (error) {
            }
        }

        const isCreator = computed(() => {
            return mini.value?.staff?.organizer?.some(
                staff => staff.role === 1 && staff.user?.id === getUser.value.id
            )
        })

        // Tất cả người tham gia (confirmed, chưa checkin, chưa vắng) - base cho các section
        const allParticipants = computed(() => {
            if (!mini.value?.participants) return []
            return mini.value.participants.filter(p => p.is_confirmed && !p.checked_in_at && !p.is_absent)
        })

        // Đã check-in (confirmed + checked_in + not absent)
        const checkedInParticipants = computed(() => {
            if (!mini.value?.participants) return []
            return mini.value.participants.filter(p => p.is_confirmed && p.checked_in_at && !p.is_absent)
        })

        // Đã báo vắng (is_absent = true)
        const absentParticipants = computed(() => {
            if (!mini.value?.participants) return []
            return mini.value.participants.filter(p => p.is_absent)
        })

        // Người tham gia: tất cả user + guest đã thanh toán (payment_status = confirmed) - dùng cho hiển thị
        const displayedParticipants = computed(() => {
            return allParticipants.value
        })

        // Chưa thanh toán: user và guest đã tham gia (is_confirmed) nhưng chưa thanh toán
        const unpaidParticipants = computed(() => {
            if (!mini.value?.participants) return []
            return mini.value.participants.filter(
                p => p.is_confirmed === true && p.payment_status !== 'confirmed'
            )
        })

        // Chờ xác nhận: người tự xin (is_invited=false) + người được mời (is_invited=true) chưa xác nhận
        const pendingParticipants = computed(() => {
            if (!mini.value?.participants) return []
            return mini.value.participants.filter(p => p.is_confirmed === false && p.is_invited === false)
        })

        const invitedParticipants = computed(() => {
            if (!mini.value?.participants) return []
            return mini.value.participants.filter(p => p.is_confirmed === false && p.is_invited === true)
        })

        // Gộp: chờ xác nhận = pending (tự xin) + invited (được mời)
        const waitingConfirmationParticipants = computed(() => {
            return [...pendingParticipants.value, ...invitedParticipants.value]
        })

        // Guest section đầy khi: allParticipants >= max_players
        const isGuestSectionFull = computed(() => {
            return allParticipants.value.length >= (mini.value?.max_players || 0)
        })

        const isAutoSplitPaymentReady = computed(() => {
            if (!mini.value?.has_fee) return false
            if (!mini.value?.auto_split_fee) return true

            if (mini.value?.auto_payment_created) return true

            // Fallback cho dữ liệu cũ chưa có auto_payment_created:
            // auto-split chỉ coi là đã chia khoản thu khi xuất hiện trạng thái chờ/nộp tiền.
            return (mini.value?.participants || []).some((p) =>
                p.payment_status === 'pending' || p.payment_status === 'paid'
            )
        })

        const canShowPaymentButton = computed(() => {
            if (!mini.value?.has_fee) return false
            if (!mini.value?.auto_split_fee) return true
            return isAutoSplitPaymentReady.value
        })

        // Organizer: luôn thấy nút quản lý thanh toán sau khi trận đấu hoàn tất
        const canManagePayments = computed(() => {
            if (!mini.value?.has_fee) return false
            if (!isCreator.value) return false
            if (!mini.value?.auto_split_fee) return true
            return isAutoSplitPaymentReady.value
        })

        const isCurrentUserParticipant = computed(() => {
            return mini.value?.participants?.some(
                p => p.user?.id === getUser.value?.id
            )
        })

        const showMemberActionModal = ref(false)
        const selectedMember = ref(null)

        const openMemberActionModal = (param) => {
            // param có thể là props object (từ UserCard click event) hoặc participant item
            // UserCard emit 'click' với props object: { id, name, avatar, rating, userId, ... }
            if (param && typeof param === 'object') {
                // Nếu param là Event (có target), lấy thông tin từ target
                if (param.target && param.currentTarget) {
                    // Đây là Event object - không làm gì
                    return
                }
                // UserCard props object: có id=participant_id, userId=user_id, name, avatar, rating
                if (param.id !== undefined && !param.user) {
                    const participantId = param.id
                    const userId = param.userId || param.id
                    selectedMember.value = {
                        id: participantId,
                        participant_id: participantId,
                        name: param.name,
                        avatar: param.avatar,
                        rating: param.rating,
                        checked_in_at: param.checked_in_at || null,
                        is_absent: param.is_absent || false,
                        is_guest: param.is_guest || false,
                        user: { id: userId, full_name: param.name, avatar_url: param.avatar }
                    }
                } else {
                    selectedMember.value = param
                }
            } else {
                // Fallback: param là primitive value (id)
                selectedMember.value = { id: param, participant_id: param }
            }
            showMemberActionModal.value = true
        }

        const handleMemberViewProfile = (member) => {
            router.push(`/profile/${member.user?.id}`)
        }

        const handleMemberCheckIn = async (member) => {
            try {
                await markMiniParticipantCheckIn(id, member.id)
                toast.success('Đã đánh dấu check-in thành công!')
                await detailMiniTournament(id)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi đánh dấu check-in.')
            }
        }

        const handleMemberAbsent = async (member) => {
            try {
                await markMiniParticipantAbsent(id, member.id)
                toast.success('Đã đánh dấu vắng mặt thành công!')
                await detailMiniTournament(id)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi đánh dấu vắng mặt.')
            }
        }

        const handleMemberSelfCheckIn = async (member) => {
            try {
                await selfCheckInMini(id)
                toast.success('Check-in thành công!')
                await detailMiniTournament(id)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi check-in.')
            }
        }

        const handleMemberSelfAbsent = async (member) => {
            try {
                await selfMarkAbsentMini(id)
                toast.success('Đã báo vắng thành công!')
                await detailMiniTournament(id)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi báo vắng.')
            }
        }

        const openPromotionModal = () => {
            isPromotionModalOpen.value = true;
        };

        const handlePaymentSubmitSuccess = async () => {
            await detailMiniTournament(id);
        };

        const getPaymentStatusBadgeClass = (status) => {
            switch(status) {
                case 'confirmed':
                    return 'bg-green-100 text-green-700'
                case 'pending':
                    return 'bg-yellow-100 text-yellow-700'
                case 'cancelled':
                    return 'bg-red-100 text-red-700'
                default:
                    return 'bg-gray-100 text-gray-700'
            }
        }

        const getPaymentStatusLabel = (status) => {
            switch(status) {
                case 'confirmed':
                    return 'Đã xác nhận'
                case 'pending':
                    return 'Chờ thanh toán'
                case 'cancelled':
                    return 'Đã hủy'
                default:
                    return 'Không xác định'
            }
        }

        const openInviteModalDefault = async () => {
            inviteType.value = 'staff'
            activeScope.value = 'all'
            await getInviteGroupData()
            showInviteModal.value = true
        }

        const openInviteModalWithFriends = async () => {
            inviteType.value = 'participant'
            activeScope.value = 'friends'
            await getInviteGroupData()
            showInviteModal.value = true
        }

        const getInviteGroupData = async () => {
            if (activeScope.value === 'club' && !selectedClub.value) {
                inviteGroupData.value = [];
                return;
            }

            const payload = {
                scope: activeScope.value,
                per_page: 50,
                ...(activeScope.value === 'club' ? { club_id: selectedClub.value } : {}),
                ...(searchQuery.value ? { search: searchQuery.value } : {})
            };
            if (activeScope.value === 'area') {
                payload.lat = mini.value.competition_location.latitude
                payload.lng = mini.value.competition_location.longitude
                payload.radius = currentRadius.value
            }
            try {
                const resp = await MiniParticipantService.getMiniTournamentInviteGroups(id, payload);
                inviteGroupData.value = resp || [];
            } catch (e) {
                inviteGroupData.value = [];
            }
        };

        const getMyClubs = async () => {
            try {
                const response = await ClubService.myClubs();
                clubs.value = response || [];

                if (clubs.value.length === 0) {
                    selectedClub.value = null;
                } else {
                    selectedClub.value = clubs.value[0].id;
                }
            } catch (e) {
                clubs.value = [];
                selectedClub.value = null;
            }
        };

        const onSearchChange = debounce(async (query) => {
            searchQuery.value = query;
            await getInviteGroupData();
        }, 300);

        const onScopeChange = async (scope) => {
            activeScope.value = scope;
            await getInviteGroupData();
        };

        const onClubChange = async (clubId) => {
            selectedClub.value = clubId;
            await getInviteGroupData();
        };

        const invite = async (friendId) => {
            try {
                await MiniParticipantService.sendInvitation(id, friendId);
                toast.success('Đã gửi lời mời thành công!');
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi gửi lời mời.');
            }
        };

        const inviteStaff = async (userId) => {
            try {
                await MiniTournamentStaffService.addMiniTournamentStaff(id, userId);
                toast.success('Thêm thành công');
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi thêm.');
            }
        };

        const showQRCode = () => {
            showQRCodeModal.value = true;
        }

        const handleRemoveStaff = async (staffId, staffUserId, staffName, staffAvatar) => {
            try {
                const response = await MiniParticipantService.deleteStaff(staffId);
                // Nếu staff có guest bảo lãnh, API trả về thông tin để hiển thị modal
                if (response?.data?.has_guaranteed_guests) {
                    const data = response.data;
                    deleteStaffData.value = {
                        staffId,
                        guarantor: {
                            full_name: data.guarantor_name,
                            avatar_url: staffAvatar,
                        },
                        guests: data.guaranteed_guests || [],
                        candidates: data.guarantor_candidates || [],
                    };
                    showDeleteStaffModal.value = true;
                    return;
                }
                toast.success('Đã xóa người tổ chức khỏi kèo đấu');
                await detailMiniTournament(id);
            } catch (error) {
                toast.error(error.response?.data?.message || 'Xóa người tổ chức thất bại');
            }
        };

        const handleRemoveUser = async (data) => {
            // Support both old format (id only) and new format (object)
            const miniParticipantId = typeof data === 'object' ? data.id : data;
            try {
                await MiniParticipantService.deleteMiniParticipant(miniParticipantId);
                toast.success('Đã xóa người chơi khỏi kèo đấu');
                await detailMiniTournament(id);
            } catch (error) {
                toast.error(error.response?.data?.message || 'Xóa người chơi thất bại');
            }
        };

        const handleConfirmDeleteStaff = async ({ staffId, action, newGuarantorUserId }) => {
            try {
                await MiniParticipantService.deleteStaff(staffId, action, newGuarantorUserId);
                toast.success('Đã xóa người tổ chức khỏi kèo đấu');
                showDeleteStaffModal.value = false;
                await detailMiniTournament(id);
            } catch (error) {
                toast.error(error.response?.data?.message || 'Xóa người tổ chức thất bại');
            }
        };

        const saveDescription = async () => {
            const update = await baseSetColumnUpdateMiniTournament()
            update.description = descriptionModel.value

            await updateMiniTournament(mini.value.id, update);
            mini.value.description = descriptionModel.value;
            isEditingDescription.value = false;
        };

        const baseSetColumnUpdateMiniTournament = async () => {
            return {
                sport_id: mini.value.sport?.id,
                name: mini.value.name,
                description: mini.value.description,
                start_time: mini.value.start_time,
                end_time: mini.value.end_time,
                duration: mini.value.duration,
                competition_location_id: mini.value.competition_location?.id,
                is_private: mini.value.is_private,
                has_fee: mini.value.has_fee,
                auto_split_fee: mini.value.auto_split_fee,
                fee_description: mini.value.fee_description,
                fee_amount: mini.value.fee_amount,
                max_players: mini.value.max_players,
                min_rating: mini.value.min_rating,
                max_rating: mini.value.max_rating,
                set_number: mini.value.set_number,
                base_points: mini.value.base_points,
                points_difference: mini.value.points_difference,
                max_points: mini.value.max_points,
                gender: mini.value.gender,
                apply_rule: mini.value.apply_rule,
                allow_cancellation: mini.value.allow_cancellation,
                cancellation_duration: mini.value.cancellation_duration,
                auto_approve: mini.value.auto_approve,
                allow_participant_add_friends: mini.value.allow_participant_add_friends,
                recurring_schedule: mini.value.recurring_schedule,
                status: mini.value.status,
            }
        };

        const confirmRemoval = () => {
            // Kiểm tra xem kèo có thuộc chuỗi lặp lại không
            const seriesId = mini.value.recurrence_series_id
            if (seriesId) {
                showCancelRecurrenceModal.value = true;
            } else {
                showDeleteModal.value = true;
            }
        };

        const removeMiniTournament = async () => {
            const id = mini.value.id
            try {
                await MiniTournamnetService.deleteMiniTournament(id)
                toast.success('Xoá kèo đấu thành công!')
                setTimeout(() => {
                    router.push('/')
                }, 1500)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi xoá giải đấu.')
            }
        }

        const cancelRecurrenceSeries = async () => {
            const id = mini.value.id
            try {
                await MiniTournamnetService.cancelRecurrenceSeries(id)
                toast.success('Đã hủy toàn bộ chuỗi kèo đấu lặp lại!')
                setTimeout(() => {
                    router.push('/')
                }, 1500)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Đã xảy ra lỗi khi hủy chuỗi kèo đấu.')
            }
        }

        const openPaymentModal = () => {
            if (!mini.value?.id) return
            if (!mini.value.has_fee) {
                toast.error('Kèo này không thu phí tham gia.')
                return
            }
            if (mini.value.auto_split_fee && !isAutoSplitPaymentReady.value) {
                toast.info('Kèo chia phí tự động, chỉ thanh toán sau khi hệ thống chia khoản thu.')
                return
            }
            showPaymentModal.value = true
        }

        const openSubmitPaymentModal = () => {
            if (!mini.value?.id) return
            if (!mini.value.has_fee) {
                toast.error('Kèo này không thu phí tham gia.')
                return
            }
            if (mini.value.auto_split_fee && !isAutoSplitPaymentReady.value) {
                toast.info('Kèo chia phí tự động, chỉ thanh toán sau khi hệ thống chia khoản thu.')
                return
            }
            showSubmitPaymentModal.value = true
        }

        const openAddGuestModal = () => {
            if (!mini.value?.id) return
            showAddGuestModal.value = true
        }

        const handleAddGuestSuccess = async () => {
            await detailMiniTournament(id)
        }

        const handlePaymentButtonClick = () => {
            if (isCreator.value) {
                // Chủ kèo: mở modal quản lý thanh toán
                openPaymentModal()
            } else {
                // Member hoặc user bình thường: mở modal thanh toán
                openSubmitPaymentModal()
            }
        }


        const publicMiniTournament = async () => {
            const newStatus = mini.value.status === 1 ? 2 : 1;
            let res = null;

            try {
                const update = await baseSetColumnUpdateMiniTournament()
                update.status = newStatus

                res = await updateMiniTournament(mini.value.id, update);
                if (res && res.status) {
                    mini.value.status = res.status;
                } else {
                    mini.value.status = newStatus;
                }
            } catch (error) {
            }
        }

        const joinerMiniTournament = async () => {
            const miniTournamentId = mini.value.id;
            try {
                const res = await MiniParticipantService.joinMiniTournament(miniTournamentId);
                if (res) {
                    toast.success('Tham gia kèo đấu thành công, Bạn có thể cần chờ xác nhận trước khi được bổ nhiệm vào 1 đội')
                }
            } catch (error) {
                toast.error(error.response?.data?.message || 'Lỗi khi thực hiện yêu cầu này')
            }
        }

        const confirmMiniTournament = async () => {
            const miniParticipantId =
                mini.value?.participants?.find(
                    p => p.user.id === getUser.value.id
                )?.id ?? null;
            try {
                const res = await MiniParticipantService.acceptInviteMiniTournament(miniParticipantId)
                if (res) {
                    await detailMiniTournament(id)
                    isUserConfirmed.value = true
                    toast.success('Xác nhận tham gia kèo đấu thành công')
                }
            } catch (error) {
                toast.error(error.response?.data?.message || 'Lỗi khi thực hiện yêu cầu này')
            }
        }

        const confirmDelineMiniParticipant = () => {
            showDelineMiniParticipantModal.value = true;
        };

        const declineMiniTournament = async () => {
            const miniParticipantId =
                mini.value?.participants?.find(
                    p => p.user.id === getUser.value.id
                )?.id ?? null;
            try {
                const res = await MiniParticipantService.declineMiniTournament(miniParticipantId)
                if (res.status) {
                    await detailMiniTournament(id)
                    toast.success('Từ Chối tham gia kèo đấu thành công')
                }
            } catch (error) {
                toast.error(error.response?.data?.message || 'Lỗi khi thực hiện yêu cầu này')
            }
        }

        const handleApproveParticipant = async (participantId) => {
            try {
                await MiniParticipantService.confirmMiniParticipant(participantId)
                toast.success('Đã duyệt người chơi tham gia')
                await detailMiniTournament(id)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Duyệt người chơi thất bại')
            }
        }

        const handleRejectParticipant = async (participantId) => {
            try {
                await MiniParticipantService.deleteMiniParticipant(participantId)
                toast.success('Đã từ chối người chơi')
                await detailMiniTournament(id)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Từ chối người chơi thất bại')
            }
        }

        const handleCancelRequest = async (participantId) => {
            try {
                await MiniParticipantService.deleteMiniParticipant(participantId)
                toast.success('Đã hủy yêu cầu tham gia')
                await detailMiniTournament(id)
            } catch (error) {
                toast.error(error.response?.data?.message || 'Hủy yêu cầu thất bại')
            }
        }

        onMounted(async () => {
            activeTab.value = route.query.tab || 'detail'
            if (id) {
                await detailMiniTournament(id)
            }
            if (mini.value && mini.value.participants) {
                currentParticipant.value = mini.value.participants.find(
                    p => Number(p.user?.id) === Number(getUser.value.id)
                )

                if (currentParticipant.value) {
                    isUserConfirmed.value = currentParticipant.value.is_confirmed === true
                }
            }
            await getMyClubs();
            await getInviteGroupData();
        })

        return {
            ArrowTrendingUpIcon,
            ChevronRightIcon,
            LinkIcon,
            LockClosedIcon,
            LockOpenIcon,
            PaperAirplaneIcon,
            PhotoIcon,
            QrCodeIcon,
            CalendarDaysIcon,
            MapPinIcon,
            CircleStackIcon,
            PencilIcon,
            XCircleIcon,
            UserMultiple,
            UserIcon,
            UsersIcon,
            CreditCardIcon,
            ClipboardDocumentCheckIcon,
            FaceSmileIcon,
            MegaphoneIcon,
            ChatFormMiniTournament,
            DeleteConfirmationModal,
            QRcodeModal,
            UserCard,
            InviteGroup,
            activeTab,
            ShareAction,
            tabs,
            formatEventDate,
            id,
            mini,
            autoApprove,
            subActiveTab,
            showInviteModal,
            showCreateMatchModal,
            toggleAutoApprove,
            isCreator,
            inviteGroupData,
            clubs,
            activeScope,
            selectedClub,
            searchQuery,
            onSearchChange,
            showQRCodeModal,
            miniTournamentLink,
            descriptionModel,
            isEditingDescription,
            isDescriptionChanged,
            showDeleteModal,
            showCancelRecurrenceModal,
            isPromotionModalOpen,
            openPromotionModal,
            MiniMatchScheduleTab,
            isUserConfirmed,
            currentParticipant,
            handleInvite,
            getUserScore,
            getUser,
            copyLink,
            goToEditPage,
            openInviteModalDefault,
            onScopeChange,
            onClubChange,
            openInviteModalWithFriends,
            showQRCode,
            handleRemoveStaff,
            handleRemoveUser,
            setupDescription,
            saveDescription,
            confirmRemoval,
            removeMiniTournament,
            cancelRecurrenceSeries,
            publicMiniTournament,
            joinerMiniTournament,
            confirmMiniTournament,
            confirmDelineMiniParticipant,
            declineMiniTournament,
            showDelineMiniParticipantModal,
            onRadiusChange,
            showPaymentModal,
            openPaymentModal,
            showSubmitPaymentModal,
            openSubmitPaymentModal,
            showAddGuestModal,
            openAddGuestModal,
            showDeleteStaffModal,
            deleteStaffData,
            handleConfirmDeleteStaff,
            handlePaymentButtonClick,
            toast,
            allParticipants,
            checkedInParticipants,
            absentParticipants,
            displayedParticipants,
            pendingParticipants,
            invitedParticipants,
            waitingConfirmationParticipants,
            unpaidParticipants,
            isGuestSectionFull,
            handlePaymentSubmitSuccess,
            handleAddGuestSuccess,
            getPaymentStatusBadgeClass,
            getPaymentStatusLabel,
            canShowPaymentButton,
            canManagePayments,
            handleApproveParticipant,
            handleRejectParticipant,
            handleCancelRequest,
            showMemberActionModal,
            selectedMember,
            openMemberActionModal,
            handleMemberViewProfile,
            handleMemberCheckIn,
            handleMemberAbsent,
            handleMemberSelfCheckIn,
            handleMemberSelfAbsent,
            isCurrentUserParticipant
        }
    }
}


</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 0;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
