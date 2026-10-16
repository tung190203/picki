<template>
    <div class="min-h-screen">
        <div class="flex items-center gap-4 p-4 bg-white">
            <ArrowLeftIcon class="w-6 h-6 text-gray-600 hover:text-[#D72D36] cursor-pointer" @click="goBack" />
            <h1 class="text-lg font-semibold">{{ data.name }}</h1>
        </div>

        <div class="bg-white px-4 pt-4">
            <div class="grid grid-cols-3 gap-3 mb-4">
                <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" :class="[
                    'flex flex-col items-center justify-center py-4 rounded-lg border-2 transition-all h-[120px] group',
                    activeTab === tab.id
                        ? 'bg-[#D72D36] border-[#D72D36] text-white icon-active'
                        : 'bg-white border-gray-200 text-gray-700 icon-inactive'
                ]">
                    <component :is="tab.icon" class="w-12 h-12 mb-2" />
                    <span class="text-sm font-medium">{{ tab.label }}</span>
                </button>
            </div>

            <div class="flex gap-2 pb-4">
                <button :class="[
                    'px-4 py-2 rounded-md text-sm font-medium',
                    format === '1' ? 'bg-[#D72D36] text-white' : 'bg-gray-100 text-gray-700'
                ]" @click="format = '1'">
                    1 Lượt
                </button>
                <button :class="[
                    'px-4 py-2 rounded-md text-sm font-medium',
                    format === '2' ? 'bg-[#D72D36] text-white' : 'bg-gray-100 text-gray-700'
                ]" @click="format = '2'">
                    2 Lượt (lượt đi / lượt về)
                </button>
            </div>
            <div class="pb-2">
                <p class="text-[#6B6F80] text-xs">Mỗi đội sẽ đấu với đội khác {{ format }} lần</p>
                <p class="text-[#4392E0] text-xs cursor-pointer">Tìm hiểu thêm về các thể thức thi đấu</p>
            </div>
        </div>

        <div class="py-4 space-y-6">
            <template v-if="activeTab === 'mixed'">
                <Section title="Quy tắc xếp đội đấu loại trực tiếp">
                    <template v-for="(rule, index) in displayRankingRules" :key="`seeding-${index}`">
                        <div class="relative">
                            <SettingItem :label="`Ưu tiên ${index + 1}`" :value="rule.value" :subtitle="rule.subtitle"
                                :is-open="openSettingId === `seeding-${index}`"
                                @click="toggleDropdown(`seeding-${index}`)" />
                            <div v-if="openSettingId === `seeding-${index}`"
                                class="absolute right-0 top-28 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                                <div class="space-y-1">
                                    <button v-for="(option, id) in SEEDING_RULES_MAP" :key="id"
                                        @click="selectRankingRule(`seeding-${index}`, index, id)"
                                        class="block w-full text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                        :class="{ 'font-semibold bg-red-50 text-[#D72D36]': seedingRules[index] === parseInt(id), 'hover:bg-gray-100': seedingRules[index] !== parseInt(id) }">
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </Section>

                <Section title="Cách tính xếp hạng">
                    <template v-for="(method, index) in displayCalculationMethods" :key="`ranking-${index}`">
                        <div class="relative">
                            <SettingItem :label="`Ưu tiên ${index + 1}`" :value="method.value"
                                :subtitle="method.subtitle" :is-open="openSettingId === `ranking-${index}`"
                                @click="toggleDropdown(`ranking-${index}`)" />
                            <div v-if="openSettingId === `ranking-${index}`"
                                class="absolute right-0 top-44 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                                <div class="space-y-1">
                                    <button v-for="(option, id) in RANKING_RULES_MAP" :key="id"
                                        @click="!isRankingRuleDisabled(index, id) && selectRankingRule(`ranking-${index}`, index, id)"
                                        class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                        :class="[
                                            calculationMethods[index] === parseInt(id)
                                                ? 'font-semibold bg-red-50 text-[#D72D36]'
                                                : isRankingRuleDisabled(index, id)
                                                    ? 'opacity-40 cursor-not-allowed'
                                                    : 'hover:bg-gray-100'
                                        ]" :disabled="isRankingRuleDisabled(index, id)">
                                        {{ option.label }}
                                    </button>

                                </div>
                            </div>
                        </div>
                    </template>
                </Section>

                <Section title="Vòng bảng">
                    <div class="space-y-4">
                        <Counter label="Số bảng đấu" :value="tables" @update="tables = $event" />

                        <!-- KHI tables === 1: hiển thị Top 2 / Top 4 -->
                        <div v-if="tables === 1" class="space-y-2">
                            <p class="text-sm text-gray-900">Số đội vào vòng loại mỗi bảng</p>
                            <div class="flex gap-2">
                                <button
                                    v-for="opt in [2, 4]" :key="opt"
                                    type="button"
                                    @click="teamsToKnockout = opt"
                                    :class="[
                                        'flex-1 py-2 px-3 rounded-lg border-2 text-sm font-medium transition-all',
                                        teamsToKnockout === opt
                                            ? 'bg-[#D72D36] border-[#D72D36] text-white'
                                            : 'bg-white border-gray-200 text-gray-700 hover:border-[#D72D36]'
                                    ]">
                                    Top {{ opt }}
                                </button>
                            </div>
                            <p v-if="singleGroupValidationError"
                               class="text-red-500 text-xs mt-1 font-medium">
                                {{ singleGroupValidationError }}
                            </p>
                        </div>

                        <!-- KHI tables >= 2: giữ nguyên Counter -->
                        <Counter v-else
                            label="Số đội vào vòng loại mỗi bảng"
                            :value="teamsToKnockout"
                            @update="teamsToKnockout = $event" />
                    </div>
                </Section>

                <Section title="Vòng loại trực tiếp">
                    <div class="space-y-3">
                        <Toggle label="Vòng Tái sinh"
                            description="Các đội không vào vòng trong (hạng 3, 4...) sẽ đấu loại trực tiếp với nhau ở 1 nhánh riêng"
                            :value="hasResurrectionBracket" @update="hasResurrectionBracket = $event" />

                        <div v-if="hasResurrectionBracket" class="mt-3 p-3 bg-red-50/50 border border-red-100 rounded-xl space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Tên nhánh chính</label>
                                <input type="text" v-model="mainBracketName" placeholder="VD: Giải chính"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#D72D36]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Tên nhánh Tái sinh</label>
                                <input type="text" v-model="subBracketName" placeholder="VD: Giải Tái sinh"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-[#D72D36]" />
                            </div>
                            <div class="flex gap-2 flex-wrap pt-1">
                                <button type="button" @click="setQuickBranchNames('Giải chính', 'Giải Tái sinh')"
                                    class="px-2.5 py-1 text-xs font-medium text-[#D72D36] bg-red-100/60 border border-red-200 rounded-full hover:bg-red-100 transition-colors">
                                    Giải chính / Giải Tái sinh
                                </button>
                                <button type="button" @click="setQuickBranchNames('Giải A', 'Giải B')"
                                    class="px-2.5 py-1 text-xs font-medium text-[#D72D36] bg-red-100/60 border border-red-200 rounded-full hover:bg-red-100 transition-colors">
                                    Giải A / Giải B
                                </button>
                            </div>
                        </div>

                        <Toggle label="Tranh hạng ba"
                            description="Thêm trận tranh hạng ba cho đội chơi thua vòng bán kết"
                            :value="thirdPlaceMatch" @update="thirdPlaceMatch = $event" />
                        <Toggle label="Chọn đội vào vòng trong (nếu thiếu)"
                            description="Đội thua có thành tích tốt nhất trên BXH" :value="selectBestLosers"
                            @update="selectBestLosers = $event" />
                    </div>
                </Section>

                <!-- Section "Xét đội Nhì/Ba" đã được ẩn: cross_group_ranking.enabled hiện drive
                     hoàn toàn bởi advanced_to_next_round (chọn đội vào vòng trong).
                     User không còn chọn riêng nữa. Logic tương ứng ở BE:
                     TournamentTypeController::update / store force enabled = advanced_to_next_round -->

                <Section title="Luật thi đấu">
                    <div class="relative">
                        <SettingItem label="Số set đấu" :value="`${setsPerMatch} Set`"
                            :is-open="openSettingId === 'set'" @click="toggleDropdown('set')" />
                        <div v-if="openSettingId === 'set'"
                            class="absolute right-0 top-28 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <div class="space-y-1">
                                <button v-for="n in 3" :key="n" @click="selectMatchRule('set', n)"
                                    class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="{ 'font-semibold bg-red-50 text-[#D72D36]': setsPerMatch === n, 'hover:bg-gray-100': setsPerMatch !== n }">
                                    {{ n }} Set
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="isRally" class="border border-dashed border-gray-200 rounded-lg p-3 bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-700">Mốc điểm Rally</p>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500">Số chặng:</span>
                                <select v-model.number="stageCount" @change="onStageCountChange"
                                    class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-[#D72D36] focus:border-[#D72D36]">
                                    <option v-for="n in [2, 3, 4, 5]" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-2"
                            :class="stageCount === 2 ? 'grid-cols-2' : (stageCount === 4 ? 'grid-cols-2' : 'grid-cols-3')">
                            <div v-for="(_, idx) in stagePoints" :key="idx">
                                <label class="block text-xs text-gray-500 mb-1">Chặng {{ idx + 1 }}</label>
                                <input type="number" v-model.number="stagePoints[idx]" min="1"
                                    class="w-full px-2 py-1 border rounded text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                    :class="!stageValidation.ok ? 'border-red-400' : 'border-gray-300'" />
                            </div>
                        </div>
                        <p v-if="!stageValidation.ok" class="text-xs text-red-500 mt-2">{{ stageValidation.error }}</p>
                        <p v-else class="text-xs text-gray-500 mt-2">
                            Mỗi mốc phải lớn hơn mốc trước ít nhất 5 điểm. Mặc định 10/20/30.
                        </p>
                    </div>

                    <div class="relative">
                        <SettingItem label="Điểm kết thúc mỗi trận" :value="`${pointsToWinSet} Điểm`"
                            :is-open="openSettingId === 'point'" @click="toggleDropdown('point')" />
                        <div v-if="openSettingId === 'point'"
                            class="absolute right-0 top-16 -translate-y-1/2 z-20 w-auto min-w-[150px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="pointsToWinSet"
                                @input="updateMatchPoint('point', $event.target.value)" min="1"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập số điểm" />
                        </div>
                    </div>

                    <div class="relative">
                        <SettingItem label="Quy tắc thắng"
                            :value="winningRule === 1 ? 'Cách biệt 1 điểm' : 'Cách biệt 2 điểm'"
                            :is-open="openSettingId === 'winrule'" @click="toggleDropdown('winrule')" />
                        <div v-if="openSettingId === 'winrule'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <div class="space-y-1">
                                <button v-for="option in winningRuleOptions" :key="option.id"
                                    @click="selectMatchRule('winrule', option.id)"
                                    class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="{ 'font-semibold bg-red-50 text-[#D72D36]': winningRule === option.id, 'hover:bg-gray-100': winningRule !== option.id }">
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <SettingItem label="Điểm tối đa" :value="`${maxPoints} Điểm`"
                            :is-open="openSettingId === 'maxpoint'" @click="toggleDropdown('maxpoint')" />
                        <div v-if="openSettingId === 'maxpoint'"
                            class="absolute right-0 top-20 -translate-y-1/2 z-20 w-auto min-w-[200px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="maxPoints"
                                @input="updateMatchPoint('maxpoint', $event.target.value)" :min="pointsToWinSet"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập điểm tối đa" />
                            <div class="text-xs text-gray-500 mt-2 p-1">Điểm tối đa phải ≥ Điểm kết thúc ({{
                                pointsToWinSet }} Điểm).
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <SettingItem label="Điểm đổi sân"
                            :value="serveChangeInterval > 0 ? `${serveChangeInterval} Điểm` : 'Không có'"
                            subtitle="Hệ thống sẽ tự động đổi sân ở set cuối dựa trên điểm đạp nhập"
                            :is-open="openSettingId === 'serve'" @click="toggleDropdown('serve')" />
                        <div v-if="openSettingId === 'serve'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto min-w-[200px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="serveChangeInterval"
                                @input="updateMatchPoint('serve', $event.target.value)" :min="0"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập số điểm (0 = Không có)" />
                            <div class="text-xs text-gray-500 mt-2 p-1">Phải nhỏ hơn Điểm kết thúc ({{ pointsToWinSet }}
                                Điểm).</div>
                        </div>
                    </div>
                </Section>

                <Section title="Thể lệ trận đấu">
                    <textarea v-model="matchNotes" placeholder="Thêm ghi chú luật chơi"
                        class="w-full min-h-[130px] p-3 border bg-[#EDEEF2] border-gray-200 rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-[#D72D36]" />
                </Section>
            </template>

            <template v-if="activeTab === 'direct'">
                <Section title="Quy tắc xếp đội đấu loại trực tiếp">
                    <template v-for="(rule, index) in displayRankingRules" :key="`seeding-${index}`">
                        <div class="relative">
                            <SettingItem :label="`Ưu tiên ${index + 1}`" :value="rule.value"
                                :is-open="openSettingId === `seeding-${index}`"
                                @click="toggleDropdown(`seeding-${index}`)" />
                            <div v-if="openSettingId === `seeding-${index}`"
                                class="absolute right-0 top-28 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                                <div class="space-y-1">
                                    <button v-for="(option, id) in SEEDING_RULES_MAP" :key="id"
                                        @click="selectRankingRule(`seeding-${index}`, index, id)"
                                        class="block w-full text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                        :class="{ 'font-semibold bg-red-50 text-[#D72D36]': seedingRules[index] === parseInt(id), 'hover:bg-gray-100': seedingRules[index] !== parseInt(id) }">
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </Section>

                <Section title="Cách tính xếp hạng">
                    <template v-for="(method, index) in displayCalculationMethods" :key="`ranking-${index}`">
                        <div class="relative">
                            <SettingItem :label="`Ưu tiên ${index + 1}`" :value="method.value"
                                :subtitle="method.subtitle" :is-open="openSettingId === `ranking-${index}`"
                                @click="toggleDropdown(`ranking-${index}`)" />
                            <div v-if="openSettingId === `ranking-${index}`"
                                class="absolute right-0 top-44 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                                <div class="space-y-1">
                                    <button v-for="(option, id) in RANKING_RULES_MAP" :key="id"
                                        @click="!isRankingRuleDisabled(index, id) && selectRankingRule(`ranking-${index}`, index, id)"
                                        class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                        :class="[
                                            calculationMethods[index] === parseInt(id)
                                                ? 'font-semibold bg-red-50 text-[#D72D36]'
                                                : isRankingRuleDisabled(index, id)
                                                    ? 'opacity-40 cursor-not-allowed'
                                                    : 'hover:bg-gray-100'
                                        ]" :disabled="isRankingRuleDisabled(index, id)">
                                        {{ option.label }}
                                    </button>

                                </div>
                            </div>
                        </div>
                    </template>
                </Section>

                <div class="space-y-3 bg-white rounded-lg px-4">
                    <Toggle label="Tranh hạng ba" description="Thêm trận tranh hạng ba cho đội chơi thua vòng bán kết"
                        :value="thirdPlaceMatch" @update="thirdPlaceMatch = $event" />
                    <Toggle label="Chọn đội vào vòng trong (nếu thiếu)"
                        description="Đội thua có thành tích tốt nhất trên BXH" :value="selectBestLosers"
                        @update="selectBestLosers = $event" />
                </div>

                <Section title="Luật thi đấu">
                    <div class="relative">
                        <SettingItem label="Số set đấu" :value="`${setsPerMatch} Set`"
                            :is-open="openSettingId === 'set'" @click="toggleDropdown('set')" />
                        <div v-if="openSettingId === 'set'"
                            class="absolute right-0 top-28 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <div class="space-y-1">
                                <button v-for="n in 3" :key="n" @click="selectMatchRule('set', n)"
                                    class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="{ 'font-semibold bg-red-50 text-[#D72D36]': setsPerMatch === n, 'hover:bg-gray-100': setsPerMatch !== n }">
                                    {{ n }} Set
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="isRally" class="border border-dashed border-gray-200 rounded-lg p-3 bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-700">Mốc điểm Rally</p>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500">Số chặng:</span>
                                <select v-model.number="stageCount" @change="onStageCountChange"
                                    class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-[#D72D36] focus:border-[#D72D36]">
                                    <option v-for="n in [2, 3, 4, 5]" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-2"
                            :class="stageCount === 2 ? 'grid-cols-2' : (stageCount === 4 ? 'grid-cols-2' : 'grid-cols-3')">
                            <div v-for="(_, idx) in stagePoints" :key="idx">
                                <label class="block text-xs text-gray-500 mb-1">Chặng {{ idx + 1 }}</label>
                                <input type="number" v-model.number="stagePoints[idx]" min="1"
                                    class="w-full px-2 py-1 border rounded text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                    :class="!stageValidation.ok ? 'border-red-400' : 'border-gray-300'" />
                            </div>
                        </div>
                        <p v-if="!stageValidation.ok" class="text-xs text-red-500 mt-2">{{ stageValidation.error }}</p>
                        <p v-else class="text-xs text-gray-500 mt-2">
                            Mỗi mốc phải lớn hơn mốc trước ít nhất 5 điểm. Mặc định 10/20/30.
                        </p>
                    </div>
                    <div class="relative">
                        <SettingItem label="Điểm kết thúc mỗi trận" :value="`${pointsToWinSet} Điểm`"
                            :is-open="openSettingId === 'point'" @click="toggleDropdown('point')" />
                        <div v-if="openSettingId === 'point'"
                            class="absolute right-0 top-20 -translate-y-1/2 z-20 w-auto min-w-[150px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="pointsToWinSet"
                                @input="updateMatchPoint('point', $event.target.value)" min="1"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập số điểm" />
                        </div>
                    </div>
                    <div class="relative">
                        <SettingItem label="Quy tắc thắng"
                            :value="winningRule === 1 ? 'Cách biệt 1 điểm' : 'Cách biệt 2 điểm'"
                            :is-open="openSettingId === 'winrule'" @click="toggleDropdown('winrule')" />
                        <div v-if="openSettingId === 'winrule'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <div class="space-y-1">
                                <button v-for="option in winningRuleOptions" :key="option.id"
                                    @click="selectMatchRule('winrule', option.id)"
                                    class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="{ 'font-semibold bg-red-50 text-[#D72D36]': winningRule === option.id, 'hover:bg-gray-100': winningRule !== option.id }">
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <SettingItem label="Điểm tối đa" :value="`${maxPoints} Điểm`"
                            :is-open="openSettingId === 'maxpoint'" @click="toggleDropdown('maxpoint')" />
                        <div v-if="openSettingId === 'maxpoint'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto min-w-[200px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="maxPoints"
                                @input="updateMatchPoint('maxpoint', $event.target.value)" :min="pointsToWinSet"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập điểm tối đa" />
                            <div class="text-xs text-gray-500 mt-2 p-1">Điểm tối đa phải ≥ Điểm kết thúc ({{
                                pointsToWinSet }} Điểm).
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <SettingItem label="Điểm đổi sân"
                            :value="serveChangeInterval > 0 ? `${serveChangeInterval} Điểm` : 'Không có'"
                            subtitle="Hệ thống sẽ tự động đổi sân ở set cuối dựa trên điểm đạp nhập"
                            :is-open="openSettingId === 'serve'" @click="toggleDropdown('serve')" />
                        <div v-if="openSettingId === 'serve'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto min-w-[200px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="serveChangeInterval"
                                @input="updateMatchPoint('serve', $event.target.value)" :min="0"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập số điểm (0 = Không có)" />
                            <div class="text-xs text-gray-500 mt-2 p-1">Phải nhỏ hơn Điểm kết thúc ({{ pointsToWinSet }}
                                Điểm).</div>
                        </div>
                    </div>
                </Section>

                <Section title="Thể lệ trận đấu">
                    <textarea v-model="matchNotes" placeholder="Thêm ghi chú luật chơi"
                        class="w-full min-h-[130px] p-3 border bg-[#EDEEF2] border-gray-200 rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-[#D72D36]" />
                </Section>
            </template>

            <template v-if="activeTab === 'roundRobin'">
                <Section title="Cách tính xếp hạng">
                    <template v-for="(method, index) in displayCalculationMethods" :key="`ranking-${index}`">
                        <div class="relative">
                            <SettingItem :label="`Ưu tiên ${index + 1}`" :value="method.value"
                                :subtitle="method.subtitle" :is-open="openSettingId === `ranking-${index}`"
                                @click="toggleDropdown(`ranking-${index}`)" />
                            <div v-if="openSettingId === `ranking-${index}`"
                                class="absolute right-0 top-44 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                                <div class="space-y-1">
                                    <button v-for="(option, id) in RANKING_RULES_MAP" :key="id"
                                        @click="!isRankingRuleDisabled(index, id) && selectRankingRule(`ranking-${index}`, index, id)"
                                        class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                        :class="[
                                            calculationMethods[index] === parseInt(id)
                                                ? 'font-semibold bg-red-50 text-[#D72D36]'
                                                : isRankingRuleDisabled(index, id)
                                                    ? 'opacity-40 cursor-not-allowed'
                                                    : 'hover:bg-gray-100'
                                        ]" :disabled="isRankingRuleDisabled(index, id)">
                                        {{ option.label }}
                                    </button>

                                </div>
                            </div>
                        </div>
                    </template>
                </Section>

                <Section title="Luật thi đấu">
                    <div class="relative">
                        <SettingItem label="Số set đấu" :value="`${setsPerMatch} Set`"
                            :is-open="openSettingId === 'set'" @click="toggleDropdown('set')" />
                        <div v-if="openSettingId === 'set'"
                            class="absolute right-0 top-28 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <div class="space-y-1">
                                <button v-for="n in 3" :key="n" @click="selectMatchRule('set', n)"
                                    class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="{ 'font-semibold bg-red-50 text-[#D72D36]': setsPerMatch === n, 'hover:bg-gray-100': setsPerMatch !== n }">
                                    {{ n }} Set
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="isRally" class="border border-dashed border-gray-200 rounded-lg p-3 bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-gray-700">Mốc điểm Rally</p>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-500">Số chặng:</span>
                                <select v-model.number="stageCount" @change="onStageCountChange"
                                    class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-[#D72D36] focus:border-[#D72D36]">
                                    <option v-for="n in [2, 3, 4, 5]" :key="n" :value="n">{{ n }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-2"
                            :class="stageCount === 2 ? 'grid-cols-2' : (stageCount === 4 ? 'grid-cols-2' : 'grid-cols-3')">
                            <div v-for="(_, idx) in stagePoints" :key="idx">
                                <label class="block text-xs text-gray-500 mb-1">Chặng {{ idx + 1 }}</label>
                                <input type="number" v-model.number="stagePoints[idx]" min="1"
                                    class="w-full px-2 py-1 border rounded text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                    :class="!stageValidation.ok ? 'border-red-400' : 'border-gray-300'" />
                            </div>
                        </div>
                        <p v-if="!stageValidation.ok" class="text-xs text-red-500 mt-2">{{ stageValidation.error }}</p>
                        <p v-else class="text-xs text-gray-500 mt-2">
                            Mỗi mốc phải lớn hơn mốc trước ít nhất 5 điểm. Mặc định 10/20/30.
                        </p>
                    </div>
                    <div class="relative">
                        <SettingItem label="Điểm kết thúc mỗi trận" :value="`${pointsToWinSet} Điểm`"
                            :is-open="openSettingId === 'point'" @click="toggleDropdown('point')" />
                        <div v-if="openSettingId === 'point'"
                            class="absolute right-0 top-20 -translate-y-1/2 z-20 w-auto min-w-[150px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="pointsToWinSet"
                                @input="updateMatchPoint('point', $event.target.value)" min="1"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập số điểm" />
                        </div>
                    </div>
                    <div class="relative">
                        <SettingItem label="Quy tắc thắng"
                            :value="winningRule === 1 ? 'Cách biệt 1 điểm' : 'Cách biệt 2 điểm'"
                            :is-open="openSettingId === 'winrule'" @click="toggleDropdown('winrule')" />
                        <div v-if="openSettingId === 'winrule'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <div class="space-y-1">
                                <button v-for="option in winningRuleOptions" :key="option.id"
                                    @click="selectMatchRule('winrule', option.id)"
                                    class="w-full block text-left px-3 py-2 text-sm rounded-lg transition-colors"
                                    :class="{ 'font-semibold bg-red-50 text-[#D72D36]': winningRule === option.id, 'hover:bg-gray-100': winningRule !== option.id }">
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <SettingItem label="Điểm tối đa" :value="`${maxPoints} Điểm`"
                            :is-open="openSettingId === 'maxpoint'" @click="toggleDropdown('maxpoint')" />
                        <div v-if="openSettingId === 'maxpoint'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto min-w-[200px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="maxPoints"
                                @input="updateMatchPoint('maxpoint', $event.target.value)" :min="pointsToWinSet"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập điểm tối đa" />
                            <div class="text-xs text-gray-500 mt-2 p-1">Điểm tối đa phải ≥ Điểm kết thúc ({{
                                pointsToWinSet }} Điểm).
                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <SettingItem label="Điểm đổi sân"
                            :value="serveChangeInterval > 0 ? `${serveChangeInterval} Điểm` : 'Không có'"
                            subtitle="Hệ thống sẽ tự động đổi sân ở set cuối dựa trên điểm đạp nhập"
                            :is-open="openSettingId === 'serve'" @click="toggleDropdown('serve')" />
                        <div v-if="openSettingId === 'serve'"
                            class="absolute right-0 top-24 -translate-y-1/2 z-20 w-auto min-w-[200px] p-2 bg-white border border-gray-200 rounded-lg shadow-2xl transition-all duration-300">
                            <input type="number" :value="serveChangeInterval"
                                @input="updateMatchPoint('serve', $event.target.value)" :min="0"
                                class="w-full p-2 border border-gray-300 rounded-lg text-sm focus:ring-[#D72D36] focus:border-[#D72D36]"
                                placeholder="Nhập số điểm (0 = Không có)" />
                            <div class="text-xs text-gray-500 mt-2 p-1">Phải nhỏ hơn Điểm kết thúc ({{ pointsToWinSet }}
                                Điểm).</div>
                        </div>
                    </div>
                </Section>

                <Section title="Thể lệ trận đấu">
                    <textarea v-model="matchNotes" placeholder="Thêm ghi chú luật chơi"
                        class="w-full min-h-[130px] p-3 border bg-[#EDEEF2] border-gray-200 rounded-lg resize-none focus:outline-none focus:ring-2 focus:ring-[#D72D36]" />
                </Section>
            </template>
        </div>

        <div class="px-4 mb-4">
            <label for="rules-file-upload" class="flex items-center gap-2 text-sm cursor-pointer transition-colors"
                :class="rulesFileName ? 'text-green-600 font-medium' : 'text-blue-600 hover:text-blue-700'">
                <ArrowUpTrayIcon class="w-4 h-4" />

                <input type="file" id="rules-file-upload" hidden @change="handleFileUpload"
                    accept=".pdf,.doc,.docx,.jpg,.png" />

                <span v-if="rulesFileName">
                    Đã đính kèm: {{ rulesFileName }} (Bấm để đổi)
                </span>
                <span v-else>
                    Tải lên tệp định kèm
                </span>
            </label>

            <button v-if="rulesFileName" @click="rulesFilePath = ''; rulesFileName = ''; actualFile = null;"
                class="text-xs text-red-500 mt-1 block hover:underline">
                Xóa tệp đính kèm
            </button>
        </div>
        <div class="right-0 bg-white px-4 pt-3">
            <div class="flex items-center justify-start mb-3 gap-8">
                <div class="text-sm text-gray-600">
                    <div>Tổng số trận</div>
                    <div class="font-semibold text-gray-900">{{ totalMatches }}</div>
                </div>
                <div class="text-sm text-gray-600">
                    <div>Số Đội</div>
                    <div class="font-semibold text-gray-900">{{ data.max_team ?? 0 }}</div>
                </div>
                <button @click="handleSubmit"
                    :disabled="!!singleGroupValidationError"
                    :class="[
                        'px-11 py-2 rounded font-medium transition-colors',
                        singleGroupValidationError
                            ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                            : 'bg-[#D72D36] text-white hover:bg-red-600'
                    ]">
                    Kiểm tra
                </button>
            </div>
        </div>
    </div>

    <!-- Manual Pairing Modal đã được chuyển ra ngoài tab Thể thức trong TournamentDetail.vue -->
</template>

<script setup>
import { ArrowLeftIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/solid';
import { ref, computed, watch, defineProps, defineEmits } from 'vue';
import Section from '@/components/atoms/Section.vue';
import SettingItem from '@/components/atoms/SettingItem.vue';
import Counter from '@/components/atoms/Counter.vue';
import Toggle from '@/components/atoms/Toggle.vue';
import MixedIcon from '@/assets/images/mixed.svg';
import DirectIcon from '@/assets/images/direct.svg';
import RoundRobinIcon from '@/assets/images/round-robin.svg';
import {
    MIN_STAGES,
    MAX_STAGES,
    DEFAULT_STAGES,
    DEFAULT_STAGE_VALUES,
    inferScoringType,
    defaultStagePoints,
    validateStagePoints,
    resizeStagePoints,
} from '@/composables/rallyScoring'

const props = defineProps({
    data: {
        type: Object,
        required: true
    }
});
const emit = defineEmits(['update:config', 'submit']);

// --- Dữ liệu tĩnh (Ánh xạ ID sang giá trị) ---
const SEEDING_RULES_MAP = {
    1: { label: 'Điểm trình độ (mạnh/yếu)', value: 'Điểm trình độ (mạnh/yếu)' },
    2: { label: 'Cùng CLB không gặp nhau', value: 'Cùng CLB không gặp nhau' },
    3: { label: 'Ngẫu nhiên', value: 'Ngẫu nhiên' }
};

const RANKING_RULES_MAP = {
    1: { label: 'Thắng / Hòa / Thua', subtitle: 'Thắng=3đ, Hòa=1đ, Thua=0đ', value: 'Thắng / Hòa / Thua' },
    2: { label: '% Thắng', subtitle: '', value: '% Thắng' },
    3: { label: 'Số hiệp thắng', subtitle: '', value: 'Số hiệp thắng' },
    4: { label: 'Số điểm thắng', subtitle: '', value: 'Số điểm thắng' },
    5: { label: 'Đối đầu', subtitle: '', value: 'Đối đầu' },
    6: { label: 'Bốc thăm', subtitle: '', value: 'Bốc thăm' }
};

const winningRuleOptions = [
    { id: 1, label: 'Cách biệt 1 điểm' },
    { id: 2, label: 'Cách biệt 2 điểm' }
];
const serveOptions = [
    { id: 0, label: 'Không có' },
    { id: 2, label: 'Mỗi 2 điểm' },
    { id: 5, label: 'Mỗi 5 điểm' }
];

// --- State (Dữ liệu UI & Logic) ---
const activeTab = ref('mixed');
const format = ref('1');
const tables = ref(2);
const teamsToKnockout = ref(1);
const thirdPlaceMatch = ref(true);
const selectBestLosers = ref(true);
const hasResurrectionBracket = ref(false);
const mainBracketName = ref('Giải chính');
const subBracketName = ref('Giải Tái sinh');
// ✅ Cross-group ranking ENABLED được drive trực tiếp bởi advanced_to_next_round (selectBestLosers).
// Khi advanced_to_next_round = true  → cross_group_ranking.enabled = true
// Khi advanced_to_next_round = false → cross_group_ranking.enabled = false
// KHÔNG còn toggle riêng nữa — chỉ là computed để phản ánh trạng thái.
const crossGroupRankingEnabled = computed(() => selectBestLosers.value);

const setQuickBranchNames = (mainName, subName) => {
    mainBracketName.value = mainName;
    subBracketName.value = subName;
};

watch(hasResurrectionBracket, (val) => {
    if (val && !mainBracketName.value) {
        mainBracketName.value = 'Giải chính';
        subBracketName.value = 'Giải Tái sinh';
    }
});

// Tự động hạ Top 8+ xuống Top 4 khi đổi về 1 bảng
watch(tables, (newVal) => {
    if (newVal === 1 && teamsToKnockout.value > 4) {
        teamsToKnockout.value = 4;
    }
});

const matchNotes = ref('');
const setsPerMatch = ref(1);
const pointsToWinSet = ref(11);
const winningRule = ref(1);
const maxPoints = ref(11);
const serveChangeInterval = ref(0);
const seedingRules = ref([1, 2, 3]);
const calculationMethods = ref([1, 4, 5, 2, 3]);

// ✅ Phase 1 — Rally Scoring stage points
const stageCount = ref(DEFAULT_STAGES);
const stagePoints = ref(defaultStagePoints(DEFAULT_STAGES));
const isRally = computed(() => inferScoringType(props.data?.player_per_team) === 'rally');
const stageValidation = computed(() => validateStagePoints(stagePoints.value));

function onStageCountChange() {
    stagePoints.value = resizeStagePoints(stagePoints.value, stageCount.value);
}

// Pairing mode được quản lý ở TournamentDetail.vue (tab Thể thức), không gửi khi tạo mới thể thức
// Chỉ gửi PUT riêng qua API PUT tournament-types/{id} để cập nhật pairing_mode

const rulesFilePath = ref('');
const rulesFileName = ref('');
const actualFile = ref(null);

const openSettingId = ref(null);

const handleFileUpload = (event) => {
    const file = event.target.files[0];

    if (file) {
        actualFile.value = file;
        rulesFilePath.value = 'attached';
        rulesFileName.value = file.name;
        openSettingId.value = null;
    }

    event.target.value = '';
};

const toggleDropdown = (id) => {
    openSettingId.value = openSettingId.value === id ? null : id;
};

const selectMatchRule = (id, value) => {
    const numValue = Number(value);

    if (id === 'set') setsPerMatch.value = numValue;
    else if (id === 'winrule') winningRule.value = numValue;
    else if (id === 'serve') serveChangeInterval.value = numValue;

    openSettingId.value = null;
};

const updateMatchPoint = (id, value) => {
    const numValue = Number(value);
    if (id === 'point' && numValue > 0) {
        pointsToWinSet.value = numValue;

        if (maxPoints.value < pointsToWinSet.value) {
            maxPoints.value = pointsToWinSet.value;
        }
        if (serveChangeInterval.value >= pointsToWinSet.value) {
            serveChangeInterval.value = 0;
        }
    } else if (id === 'maxpoint' && numValue >= pointsToWinSet.value) {
        maxPoints.value = numValue;
    } else if (id === 'serve' && numValue >= 0 && numValue < pointsToWinSet.value) {
        serveChangeInterval.value = numValue;
    } else if (id === 'serve' && numValue >= pointsToWinSet.value) {
        serveChangeInterval.value = 0;
    }
};

const selectRankingRule = (id, index, value) => {
    const [type] = id.split('-');
    if (type === 'seeding') {
        seedingRules.value[index] = Number(value);
    } else if (type === 'ranking') {
        calculationMethods.value[index] = Number(value);
    }
    openSettingId.value = null;
};

const tabs = [
    { id: 'mixed', label: 'Hỗn hợp', icon: MixedIcon },
    { id: 'direct', label: 'Loại trực tiếp', icon: DirectIcon },
    { id: 'roundRobin', label: 'Vòng tròn', icon: RoundRobinIcon }
];

const displayRankingRules = computed(() => {
    return seedingRules.value.map(id => ({
        value: SEEDING_RULES_MAP[id].value,
        subtitle: ''
    }));
});

const displayCalculationMethods = computed(() => {
    return calculationMethods.value.map(id => ({
        value: RANKING_RULES_MAP[id].value,
        subtitle: RANKING_RULES_MAP[id].subtitle
    }));
});

/**
 * Cross-group ranking: only applicable when MIXED + >= 2 groups.
 */
const isMixedMultiGroup = computed(() => {
    return activeTab.value === 'mixed' && tables.value >= 2;
});

/**
 * Whether all groups will have the same number of teams
 * (totalTeams divisible evenly by tables count).
 */
const isGroupsUniform = computed(() => {
    if (tables.value < 2) return true;
    return totalTeams.value % tables.value === 0;
});

const FORMAT_MIXED = 1;
const FORMAT_ELIMINATION = 2;
const FORMAT_ROUND_ROBIN = 3;
const totalTeams = computed(() => props.data.max_team ?? 0);
const totalMatches = computed(() => {
    const numLegs = parseInt(format.value);
    const teams = totalTeams.value;

    if (!teams || teams < 2) {
        return 0;
    }

    let currentFormat;
    if (activeTab.value === 'roundRobin') {
        currentFormat = FORMAT_ROUND_ROBIN;
    } else if (activeTab.value === 'direct') {
        currentFormat = FORMAT_ELIMINATION;
    } else {
        currentFormat = FORMAT_MIXED;
    }

    let matches = 0;

    switch (currentFormat) {
        case FORMAT_ROUND_ROBIN:
            matches = (teams * (teams - 1) / 2);
            return Math.floor(matches * numLegs);

        case FORMAT_ELIMINATION:
            const hasThirdDirect = thirdPlaceMatch.value;
            matches = teams - 1;
            if (hasThirdDirect) {
                matches += 1;
            }
            return Math.floor(matches * numLegs);

        case FORMAT_MIXED:
        default:
            const numGroups = parseInt(tables.value);
            const baseTeamsPerGroup = Math.floor(teams / numGroups);
            const remainder = teams % numGroups;
            let totalPoolMatches = 0;

            for (let i = 0; i < numGroups; i++) {
                const groupSize = baseTeamsPerGroup + (i < remainder ? 1 : 0);

                if (groupSize >= 2) {
                    const matchesInGroup = (groupSize * (groupSize - 1) / 2);
                    totalPoolMatches += matchesInGroup;
                }
            }
            totalPoolMatches = totalPoolMatches * numLegs;
            const numAdvancingTeamsPerGroup = parseInt(teamsToKnockout.value) || 0;
            const qualifiedTeams = numAdvancingTeamsPerGroup * numGroups;

            let knockoutMatches = 0;
            if (qualifiedTeams >= 2) {
                const hasThirdMixed = thirdPlaceMatch.value;
                knockoutMatches = qualifiedTeams - 1;

                if (hasThirdMixed) {
                    knockoutMatches += 1;
                }
            }
            const totalKnockoutMatches = knockoutMatches * numLegs;

            return Math.floor(totalPoolMatches + totalKnockoutMatches);
    }
});

// Validation real-time cho 1 bảng
const singleGroupValidationError = computed(() => {
    if (tables.value !== 1) return null;
    const total = totalTeams.value;
    if (teamsToKnockout.value === 2 && total < 3) {
        return 'Top 2 cần tối thiểu 3 đội.';
    }
    if (teamsToKnockout.value === 4 && total < 5) {
        return 'Top 4 cần tối thiểu 5 đội.';
    }
    return null;
});

const tournamentConfigJson = computed(() => {
    const matchRules = {
        sets_per_match: setsPerMatch.value,
        points_to_win_set: pointsToWinSet.value,
        winning_rule: winningRule.value,
        max_points: maxPoints.value,
        serve_change_interval: serveChangeInterval.value,
        // Phase 1 — Rally Scoring: server is source of truth, but we send
        // scoring_type + stage_points to keep payload explicit (the server
        // will still overwrite scoring_type from `player_per_team`).
        scoring_type: isRally.value ? 'rally' : 'side_out',
    };
    if (isRally.value && stageValidation.value.ok) {
        matchRules.stage_points = stagePoints.value.slice();
    }

    const baseConfig = {
        format: activeTab.value === 'mixed' ? 1 : (activeTab.value === 'direct' ? 2 : 3),
        num_legs: parseInt(format.value),

        match_rules: [matchRules],

        rules: matchNotes.value,
        rules_file_path: rulesFileName.value,
        format_specific_config: []
    };

    let specificConfig = {};

    if (activeTab.value === 'mixed' || activeTab.value === 'direct') {
        specificConfig = {
            seeding_rules: seedingRules.value,
            ranking: calculationMethods.value,
            has_third_place_match: thirdPlaceMatch.value,
            advanced_to_next_round: selectBestLosers.value,
        };
        if (activeTab.value === 'mixed') {
            specificConfig.pool_stage = {
                number_competing_teams: tables.value,
                num_advancing_teams: teamsToKnockout.value
            };
            specificConfig.has_resurrection_bracket = hasResurrectionBracket.value;
            specificConfig.main_bracket_name = mainBracketName.value || 'Giải chính';
            specificConfig.sub_bracket_name = subBracketName.value || 'Giải Tái sinh';
            // Cross-group ranking: only include when MIXED + >= 2 groups
            if (tables.value >= 2) {
                specificConfig.cross_group_ranking = {
                    enabled: crossGroupRankingEnabled.value,
                    apply_to: ['runner_up', 'third_place'],
                    exclude_bottom_team_matches: true
                };
            }
        }
    } else if (activeTab.value === 'roundRobin') {
        specificConfig = {
            ranking: calculationMethods.value
        };
    }

    // SỬA: GÁN format_specific_config LÀ MẢNG [object] nếu có data
    if (Object.keys(specificConfig).length > 0) {
        baseConfig.format_specific_config = [specificConfig];
    }

    return baseConfig;
});

const buildFormData = (formData, data, parentKey) => {
    if (data && typeof data === 'object' && !(data instanceof File)) {
        Object.keys(data).forEach(key => {
            const value = data[key];
            const newKey = parentKey ? `${parentKey}[${key}]` : key;
            buildFormData(formData, value, newKey);
        });
    } else {
        const finalValue = data === null || data === undefined ? '' : data;
        formData.append(parentKey, finalValue);
    }
};

const getFormDataForSubmit = () => {
    const formData = new FormData();
    const config = tournamentConfigJson.value;
    for (const key in config) {
        if (Object.prototype.hasOwnProperty.call(config, key)) {
            const value = config[key];
            buildFormData(formData, value, key);
        }
    }
    if (actualFile.value) {
        formData.append('rules_file', actualFile.value, actualFile.value.name);
    }

    return formData;
};

watch(tournamentConfigJson, (newConfig) => {
    emit('update:config', newConfig);
}, { deep: true, immediate: true });

const handleSubmit = () => {
    const dataToSend = getFormDataForSubmit();
    emit('submit', dataToSend);
};

const goBack = () => {
    emit('back');
};

const isRankingRuleDisabled = (currentIndex, ruleId) => {
    const usedRules = calculationMethods.value.filter((_, i) => i !== currentIndex);
    return usedRules.includes(Number(ruleId));
};
</script>
<style scoped>
.icon-inactive .icon-color {
    filter: none;
}

.icon-active .icon-color {
    filter: brightness(0) invert(1);
}
</style>