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
                        <Counter label="Số đội vào vòng loại mỗi bảng" :value="teamsToKnockout"
                            @update="teamsToKnockout = $event" />
                    </div>
                </Section>

                <Section title="Vòng loại trực tiếp">
                    <div class="space-y-3">
                        <Toggle label="Tranh hạng ba"
                            description="Thêm trận tranh hạng ba cho đội chơi thua vòng bán kết"
                            :value="thirdPlaceMatch" @update="thirdPlaceMatch = $event" />
                        <Toggle label="Chọn đội vào vòng trong (nếu thiếu)"
                            description="Đội thua có thành tích tốt nhất trên BXH" :value="selectBestLosers"
                            @update="selectBestLosers = $event" />
                    </div>
                </Section>

                <Section title="Ghép cặp đấu vòng loại trực tiếp">
                    <div class="space-y-2">
                        <p class="text-sm text-gray-500 mb-3">Chọn cách ghép cặp đội từ vòng bảng vào vòng loại trực tiếp</p>
                        <div class="grid grid-cols-3 gap-3">
                            <button v-for="option in PAIRING_MODE_OPTIONS.slice(0, 2)" :key="option.id"
                                @click="selectPairingMode(option.id)" :class="[
                                    'p-3 rounded-lg border-2 text-center transition-all',
                                    pairingMode === option.id
                                        ? 'border-[#D72D36] bg-red-50'
                                        : 'border-gray-200 hover:border-gray-300'
                                ]">
                                <div class="font-medium text-sm" :class="pairingMode === option.id ? 'text-[#D72D36]' : 'text-gray-700'">
                                    {{ option.label }}
                                </div>
                                <div class="text-xs text-gray-400 mt-1">{{ option.subtitle }}</div>
                            </button>
                            <button @click="openManualPairingModal"
                                :class="[
                                    'p-3 rounded-lg border-2 text-center transition-all',
                                    pairingMode === 'manual'
                                        ? 'border-[#D72D36] bg-red-50'
                                        : 'border-gray-200 hover:border-gray-300'
                                ]">
                                <div class="font-medium text-sm" :class="pairingMode === 'manual' ? 'text-[#D72D36]' : 'text-gray-700'">
                                    Tự chọn
                                </div>
                                <div class="text-xs text-gray-400 mt-1">Tự thiết kế cặp đấu</div>
                            </button>
                        </div>

                        <!-- Preview when manual is configured -->
                        <div v-if="pairingMode === 'manual' && manualPairings.length > 0" class="mt-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="text-xs text-gray-500 mb-2 font-medium">
                                Đã cấu hình {{ manualPairings.length / 2 }} cặp đấu
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <div v-for="(pairing, idx) in manualPairings.filter((_, i) => i % 2 === 0)" :key="idx"
                                    class="text-xs text-center bg-white border border-gray-200 rounded px-2 py-1">
                                    {{ getGroupName(pairing.group_id) }}{{ pairing.rank === 1 ? '1' : '2' }}
                                    <span class="text-gray-400">vs</span>
                                    {{ getGroupName(manualPairings[idx * 2 + 1]?.group_id) }}{{ manualPairings[idx * 2 + 1]?.rank === 1 ? '1' : '2' }}
                                </div>
                            </div>
                            <button @click="openManualPairingModal" class="mt-2 text-xs text-[#D72D36] hover:underline font-medium">
                                Chỉnh sửa ghép cặp
                            </button>
                        </div>
                    </div>
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
                    class="bg-[#D72D36] text-white px-11 py-2 rounded font-medium hover:bg-red-600 transition-colors">
                    Kiểm tra
                </button>
            </div>
        </div>
    </div>

    <!-- Manual Pairing Modal -->
    <ManualPairingModal
        v-model="showManualPairingModal"
        :num-groups="tables"
        :existing-pairings="manualPairings"
        @apply="handleManualPairingApply"
    />
</template>

<script setup>
import { ArrowLeftIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/solid';
import { ref, computed, watch, defineProps, defineEmits } from 'vue';
import Section from '@/components/atoms/Section.vue';
import SettingItem from '@/components/atoms/SettingItem.vue';
import Counter from '@/components/atoms/Counter.vue';
import Toggle from '@/components/atoms/Toggle.vue';
import ManualPairingModal from '@/components/molecules/ManualPairingModal.vue';
import MixedIcon from '@/assets/images/mixed.svg';
import DirectIcon from '@/assets/images/direct.svg';
import RoundRobinIcon from '@/assets/images/round-robin.svg';

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
const matchNotes = ref('');
const setsPerMatch = ref(1);
const pointsToWinSet = ref(11);
const winningRule = ref(1);
const maxPoints = ref(11);
const serveChangeInterval = ref(0);
const seedingRules = ref([1, 2, 3]);
const calculationMethods = ref([1, 2, 3]);

// Pairing mode for mixed format (knockout stage)
const pairingMode = ref('sequential');
const manualPairings = ref([]);
const showManualPairingModal = ref(false);

const PAIRING_MODE_OPTIONS = [
    { id: 'sequential', label: 'Tuần tự', subtitle: 'A-B, B-A, C-D, D-C...' },
    { id: 'symmetric', label: 'Đối xứng', subtitle: 'A-H, B-G, C-F, D-E...' },
    { id: 'manual', label: 'Tự chọn', subtitle: 'Tự thiết kế cặp đấu' },
];

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

const tournamentConfigJson = computed(() => {
    const baseConfig = {
        format: activeTab.value === 'mixed' ? 1 : (activeTab.value === 'direct' ? 2 : 3),
        num_legs: parseInt(format.value),

        match_rules: [
            {
                sets_per_match: setsPerMatch.value,
                points_to_win_set: pointsToWinSet.value,
                winning_rule: winningRule.value,
                max_points: maxPoints.value,
                serve_change_interval: serveChangeInterval.value,
            }
        ],

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
            specificConfig.knockout_stage = {
                pairing_mode: pairingMode.value
            };
            if (pairingMode.value === 'manual' && manualPairings.value.length > 0) {
                specificConfig.knockout_stage.manual_pairings = manualPairings.value;
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

const selectPairingMode = (mode) => {
    pairingMode.value = mode;
};

const openManualPairingModal = () => {
    showManualPairingModal.value = true;
};

const handleManualPairingApply = (pairings) => {
    manualPairings.value = pairings;
    pairingMode.value = 'manual';
};

const getGroupName = (groupId) => {
    if (!groupId) return '?';
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    return groupNames[groupId - 1] || groupId;
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