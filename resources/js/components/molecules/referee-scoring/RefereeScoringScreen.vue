<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                class="fixed inset-0 bg-black backdrop-blur-[1px] bg-opacity-50 flex items-center justify-center z-[60] p-4"
                @click.self="goBack"
            >
                <div class="bg-white w-full max-w-2xl lg:max-w-3xl h-[96vh] max-h-[96vh] flex flex-col shadow-2xl rounded-2xl overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                        <button @click="goBack" class="text-gray-600 hover:text-gray-900 transition-colors p-2 rounded-lg hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="flex-1 text-center px-4">
                            <h2 class="text-lg font-bold text-gray-900 leading-7">Nhập điểm trọng tài</h2>
                            <p class="text-xs text-gray-500 truncate uppercase font-medium tracking-wide">TỨ KẾT: {{ tournamentLabel }}</p>
                        </div>
                        <button @click="handleTimeout" class="text-gray-600 hover:text-gray-900 transition-colors p-2 rounded-lg hover:bg-gray-100" title="Chia sẻ">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 11.342A3 3 0 109 12c0-.23-.026-.454-.075-.67M15 8a3 3 0 10-2.316-4.9M15 16a3 3 0 10-2.316 4.9M8.684 12.658l6.632 3.684M15.316 7.658L8.684 11.342"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto bg-gray-50">
                        <!-- Rules / info -->
                        <div class="px-6 pt-4 pb-3 bg-white">
                            <div class="flex items-center justify-center gap-6 text-xs text-gray-500">
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20l9-5-9-5-9 5 9 5z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 12l9-5-9-5-9 5 9 5z"/>
                                    </svg>
                                    <span class="font-medium">{{ basePoints }} Điểm</span>
                                </div>
                                <div class="w-px h-4 bg-gray-200"></div>
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <svg class="w-4 h-4 text-green-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c1.657 0 3-1.343 3-3S13.657 2 12 2 9 3.343 9 5s1.343 3 3 3z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                    </svg>
                                    <span class="font-medium">Cách {{ pointsDifference }}</span>
                                </div>
                                <div class="w-px h-4 bg-gray-200"></div>
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <svg class="w-4 h-4 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M5 6h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                                    </svg>
                                    <span class="font-medium">{{ setNumber }} Sets</span>
                                </div>
                            </div>
                        </div>

                        <!-- Set Tabs -->
                        <div class="flex items-center gap-2 px-6 py-2.5 bg-white border-b border-gray-100 overflow-x-auto">
                            <div
                                v-for="(set, idx) in completedSets"
                                :key="'completed-' + idx"
                                class="px-3 py-1 rounded-md text-[11px] font-semibold bg-gray-100 text-gray-700 whitespace-nowrap"
                            >
                                SET {{ idx + 1 }}: {{ set.team1 }}-{{ set.team2 }}
                            </div>
                            <div class="px-3 py-1 rounded-md text-[11px] font-semibold bg-gray-100 text-gray-800 whitespace-nowrap">
                                SET {{ currentSetIndex + 1 }}: {{ team1Score }}-{{ team2Score }}
                            </div>
                            <div class="px-3 py-1 rounded-md text-[11px] font-bold bg-red-500 text-white flex items-center gap-1.5 whitespace-nowrap">
                                <span class="inline-block w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                LIVE
                            </div>
                            <button
                                type="button"
                                class="ml-auto w-9 h-9 rounded-lg bg-red-500 text-white font-bold shadow-sm hover:bg-red-600 active:bg-red-700 transition-colors flex items-center justify-center text-lg"
                                title="Thao tác nhanh"
                                @click="handleTimeout"
                            >
                                +
                            </button>
                        </div>

                        <!-- Scoreboard -->
                        <div class="px-6 pt-5 pb-4 bg-white">
                            <div class="flex items-center justify-center gap-4">
                                <div class="flex-1 rounded-2xl bg-blue-50 border-2 border-blue-100 p-5 text-center">
                                    <div class="text-6xl font-black text-blue-600 leading-none tracking-tight">
                                        {{ team1Score }}
                                    </div>
                                    <div class="text-xs font-semibold text-gray-500 mt-3 uppercase tracking-wide">
                                        {{ team1.name || 'TEAM A' }}
                                    </div>
                                </div>
                                <div class="text-sm font-bold text-gray-400 px-1">VS</div>
                                <div class="flex-1 rounded-2xl bg-red-50 border-2 border-red-100 p-5 text-center">
                                    <div class="text-6xl font-black text-red-600 leading-none tracking-tight">
                                        {{ team2Score }}
                                    </div>
                                    <div class="text-xs font-semibold text-gray-500 mt-3 uppercase tracking-wide">
                                        {{ team2.name || 'TEAM B' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Court -->
                        <div class="px-6 py-5 flex flex-col items-center justify-center">
                            <div class="w-full max-w-[420px]">
                                <div class="relative w-full rounded-2xl bg-white shadow-md border border-gray-200 overflow-hidden" style="aspect-ratio: 9/11;">
                                    <!-- court lines -->
                                    <div class="absolute inset-0 pointer-events-none">
                                        <!-- Outer border -->
                                        <div class="absolute inset-3 border-2 border-gray-300 rounded-xl"></div>
                                        <!-- Center line -->
                                        <div class="absolute left-1/2 top-3 bottom-3 w-px bg-gray-200"></div>
                                        <!-- Net (thick line) -->
                                        <div class="absolute top-1/2 left-3 right-3 h-0.5 bg-gray-400"></div>
                                        <!-- Net shadow (second line) -->
                                        <div class="absolute top-[calc(50%+3px)] left-3 right-3 h-px bg-gray-300 opacity-50"></div>
                                        <!-- Center circle -->
                                        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-9 h-9 rounded-full border-2 border-gray-300 bg-white"></div>
                                        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-gray-300"></div>
                                    </div>

                                    <!-- players - using grid layout for precise centering -->
                                    <div class="absolute inset-0 grid" :class="isDoubles ? 'grid-cols-2 grid-rows-2' : 'grid-cols-2 grid-rows-1'">
                                        <!-- Team1 Left Side -->
                                        <div
                                            class="relative flex flex-col items-center justify-center p-2"
                                            :class="isDoubles ? '' : 'row-span-2'"
                                        >
                                            <div class="relative flex flex-col items-center">
                                                <span class="absolute -top-1.5 -left-1.5 w-6 h-6 rounded-full bg-blue-50 border-2 border-blue-300 text-blue-600 text-xs font-bold flex items-center justify-center shadow-sm">1</span>
                                                <span
                                                    v-if="isServingPosition('team1', 0)"
                                                    class="absolute -bottom-1.5 -right-1.5 w-8 h-8 rounded-full text-white text-sm font-bold flex items-center justify-center shadow-lg z-10"
                                                    :class="matchStarted ? 'bg-blue-600' : 'bg-gray-900'"
                                                    :title="matchStarted ? 'Đang giao bóng' : 'Đã chọn người giao bóng'"
                                                >
                                                    🏐
                                                </span>
                                                <img
                                                    v-if="courtPositions.team1[0]"
                                                    :src="courtPositions.team1[0].avatar_url || '/images/default-avatar.png'"
                                                    :alt="courtPositions.team1[0].full_name"
                                                    class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full border-3 shadow-md object-cover"
                                                    :class="isServingPosition('team1', 0) ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200'"
                                                />
                                                <div v-else class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full bg-gray-100 border-2 border-gray-200 flex items-center justify-center">
                                                    <span class="text-gray-400 text-xs">—</span>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[100px] md:max-w-[130px] text-center leading-tight">
                                                {{ courtPositions.team1[0]?.full_name || '—' }}
                                            </div>
                                        </div>

                                        <!-- Team2 Right Side -->
                                        <div
                                            class="relative flex flex-col items-center justify-center p-2"
                                            :class="isDoubles ? '' : 'row-span-2'"
                                        >
                                            <div class="relative flex flex-col items-center">
                                                <span class="absolute -top-1.5 -right-1.5 w-6 h-6 rounded-full bg-red-50 border-2 border-red-300 text-red-600 text-xs font-bold flex items-center justify-center shadow-sm">1</span>
                                                <span
                                                    v-if="isServingPosition('team2', 0)"
                                                    class="absolute -bottom-1.5 -left-1.5 w-8 h-8 rounded-full text-white text-sm font-bold flex items-center justify-center shadow-lg z-10"
                                                    :class="matchStarted ? 'bg-blue-600' : 'bg-gray-900'"
                                                    :title="matchStarted ? 'Đang giao bóng' : 'Đã chọn người giao bóng'"
                                                >
                                                    🏐
                                                </span>
                                                <img
                                                    v-if="courtPositions.team2[0]"
                                                    :src="courtPositions.team2[0].avatar_url || '/images/default-avatar.png'"
                                                    :alt="courtPositions.team2[0].full_name"
                                                    class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full border-3 shadow-md object-cover"
                                                    :class="isServingPosition('team2', 0) ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-200'"
                                                />
                                                <div v-else class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full bg-gray-100 border-2 border-gray-200 flex items-center justify-center">
                                                    <span class="text-gray-400 text-xs">—</span>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[100px] md:max-w-[130px] text-center leading-tight">
                                                {{ courtPositions.team2[0]?.full_name || '—' }}
                                            </div>
                                        </div>

                                        <!-- Team1 Player 2 (Doubles only) -->
                                        <div
                                            v-if="isDoubles"
                                            class="relative flex flex-col items-center justify-center p-2"
                                        >
                                            <div class="relative flex flex-col items-center">
                                                <span class="absolute -top-1.5 -left-1.5 w-6 h-6 rounded-full bg-blue-50 border-2 border-blue-300 text-blue-600 text-xs font-bold flex items-center justify-center shadow-sm">2</span>
                                                <span
                                                    v-if="isServingPosition('team1', 1)"
                                                    class="absolute -bottom-1.5 -right-1.5 w-8 h-8 rounded-full text-white text-sm font-bold flex items-center justify-center shadow-lg z-10"
                                                    :class="matchStarted ? 'bg-blue-600' : 'bg-gray-900'"
                                                    :title="matchStarted ? 'Đang giao bóng' : 'Đã chọn người giao bóng'"
                                                >
                                                    🏐
                                                </span>
                                                <img
                                                    v-if="courtPositions.team1[1]"
                                                    :src="courtPositions.team1[1].avatar_url || '/images/default-avatar.png'"
                                                    :alt="courtPositions.team1[1].full_name"
                                                    class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full border-3 shadow-md object-cover"
                                                    :class="isServingPosition('team1', 1) ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200'"
                                                />
                                                <div v-else class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full bg-gray-100 border-2 border-gray-200 flex items-center justify-center">
                                                    <span class="text-gray-400 text-xs">—</span>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[100px] md:max-w-[130px] text-center leading-tight">
                                                {{ courtPositions.team1[1]?.full_name || '—' }}
                                            </div>
                                        </div>

                                        <!-- Team2 Player 2 (Doubles only) -->
                                        <div
                                            v-if="isDoubles"
                                            class="relative flex flex-col items-center justify-center p-2"
                                        >
                                            <div class="relative flex flex-col items-center">
                                                <span class="absolute -top-1.5 -right-1.5 w-6 h-6 rounded-full bg-red-50 border-2 border-red-300 text-red-600 text-xs font-bold flex items-center justify-center shadow-sm">2</span>
                                                <span
                                                    v-if="isServingPosition('team2', 1)"
                                                    class="absolute -bottom-1.5 -left-1.5 w-8 h-8 rounded-full text-white text-sm font-bold flex items-center justify-center shadow-lg z-10"
                                                    :class="matchStarted ? 'bg-blue-600' : 'bg-gray-900'"
                                                    :title="matchStarted ? 'Đang giao bóng' : 'Đã chọn người giao bóng'"
                                                >
                                                    🏐
                                                </span>
                                                <img
                                                    v-if="courtPositions.team2[1]"
                                                    :src="courtPositions.team2[1].avatar_url || '/images/default-avatar.png'"
                                                    :alt="courtPositions.team2[1].full_name"
                                                    class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full border-3 shadow-md object-cover"
                                                    :class="isServingPosition('team2', 1) ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-200'"
                                                />
                                                <div v-else class="w-16 h-16 md:w-18 md:h-18 lg:w-20 lg:h-20 rounded-full bg-gray-100 border-2 border-gray-200 flex items-center justify-center">
                                                    <span class="text-gray-400 text-xs">—</span>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[100px] md:max-w-[130px] text-center leading-tight">
                                                {{ courtPositions.team2[1]?.full_name || '—' }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- center actions (doubles only) -->
                                    <div
                                        v-if="isDoubles"
                                        class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-10 pointer-events-auto"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div class="flex flex-col items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-200 text-gray-800 shadow-sm hover:bg-gray-200 hover:border-gray-300 transition-all"
                                                    title="Timeout"
                                                    @click="handleTimeout"
                                                >
                                                    ⌛
                                                </button>
                                                <button
                                                    v-if="!matchStarted"
                                                    type="button"
                                                    class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-200 text-gray-800 shadow-sm hover:bg-gray-200 hover:border-gray-300 transition-all"
                                                    title="Đổi vị trí đội trái"
                                                    @click="swapPositions('team1')"
                                                >
                                                    ⇅
                                                </button>
                                            </div>

                                            <div class="relative flex flex-col items-center">
                                                <button
                                                    v-if="!matchStarted"
                                                    type="button"
                                                    class="absolute -top-14 w-11 h-11 rounded-full bg-gray-900 text-white shadow-lg flex items-center justify-center hover:bg-gray-800 transition-colors"
                                                    title="Chọn người giao bóng đầu tiên"
                                                    @click="toggleSelectingServer"
                                                >
                                                    🏐
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="swapTeams"
                                                    class="w-12 h-12 rounded-full bg-white border-2 border-gray-300 text-gray-900 shadow-md hover:bg-gray-50 hover:border-gray-400 transition-all"
                                                    title="Đổi bên hai đội"
                                                >
                                                    ⇄
                                                </button>
                                            </div>

                                            <div class="flex flex-col items-center gap-2">
                                                <button
                                                    v-if="!matchStarted"
                                                    type="button"
                                                    class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-200 text-gray-800 shadow-sm hover:bg-gray-200 hover:border-gray-300 transition-all"
                                                    title="Đổi vị trí đội phải"
                                                    @click="swapPositions('team2')"
                                                >
                                                    ⇅
                                                </button>
                                                <button
                                                    type="button"
                                                    class="w-10 h-10 rounded-full bg-gray-100 border-2 border-gray-200 text-gray-800 shadow-sm hover:bg-gray-200 hover:border-gray-300 transition-all"
                                                    title="Timeout"
                                                    @click="handleTimeout"
                                                >
                                                    ⌛
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- selecting server overlay (setup only) -->
                                    <div
                                        v-if="!matchStarted && selectingServer"
                                        class="absolute inset-0 bg-white/80 backdrop-blur-[2px] z-20 flex items-center justify-center"
                                    >
                                        <div class="w-full h-full p-5">
                                            <button
                                                type="button"
                                                class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white border-2 border-gray-200 shadow-lg text-gray-700 hover:bg-gray-100 hover:border-gray-300 transition-all"
                                                title="Đóng"
                                                @click="toggleSelectingServer"
                                            >
                                                ✕
                                            </button>
                                            <div class="w-full h-full grid" :class="isDoubles ? 'grid-cols-2 grid-rows-2 gap-3' : 'grid-cols-2 gap-3'">
                                                <button
                                                    type="button"
                                                    class="rounded-2xl border-2 border-gray-200 bg-white shadow-md hover:border-blue-300 hover:bg-blue-50 transition-all flex flex-col items-center justify-center"
                                                    @click="setInitialServer('team1', 0)"
                                                >
                                                    <div class="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-sm">🏐</div>
                                                    <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[140px] text-center">{{ courtPositions.team1[0]?.full_name || 'Team A - 1' }}</div>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-2xl border-2 border-gray-200 bg-white shadow-md hover:border-red-300 hover:bg-red-50 transition-all flex flex-col items-center justify-center"
                                                    @click="setInitialServer('team2', 0)"
                                                >
                                                    <div class="w-12 h-12 rounded-full bg-red-600 text-white flex items-center justify-center shadow-sm">🏐</div>
                                                    <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[140px] text-center">{{ courtPositions.team2[0]?.full_name || 'Team B - 1' }}</div>
                                                </button>
                                                <button
                                                    v-if="isDoubles"
                                                    type="button"
                                                    class="rounded-2xl border-2 border-gray-200 bg-white shadow-md hover:border-blue-300 hover:bg-blue-50 transition-all flex flex-col items-center justify-center"
                                                    @click="setInitialServer('team1', 1)"
                                                >
                                                    <div class="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-sm">🏐</div>
                                                    <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[140px] text-center">{{ courtPositions.team1[1]?.full_name || 'Team A - 2' }}</div>
                                                </button>
                                                <button
                                                    v-if="isDoubles"
                                                    type="button"
                                                    class="rounded-2xl border-2 border-gray-200 bg-white shadow-md hover:border-red-300 hover:bg-red-50 transition-all flex flex-col items-center justify-center"
                                                    @click="setInitialServer('team2', 1)"
                                                >
                                                    <div class="w-12 h-12 rounded-full bg-red-600 text-white flex items-center justify-center shadow-sm">🏐</div>
                                                    <div class="mt-2 text-xs md:text-sm font-semibold text-gray-700 truncate max-w-[140px] text-center">{{ courtPositions.team2[1]?.full_name || 'Team B - 2' }}</div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Singles: actions row below court -->
                                <div
                                    v-if="!isDoubles"
                                    class="mt-4 flex items-center justify-center gap-4"
                                >
                                    <button
                                        type="button"
                                        class="w-11 h-11 rounded-full bg-gray-100 border-2 border-gray-200 text-gray-800 shadow-sm hover:bg-gray-200 hover:border-gray-300 transition-all"
                                        title="Timeout"
                                        @click="handleTimeout"
                                    >
                                        ⌛
                                    </button>
                                    <button
                                        v-if="!matchStarted"
                                        type="button"
                                        class="w-11 h-11 rounded-full bg-gray-900 text-white shadow-lg flex items-center justify-center hover:bg-gray-800 transition-colors"
                                        title="Chọn người giao bóng đầu tiên"
                                        @click="toggleSelectingServer"
                                    >
                                        🏐
                                    </button>
                                    <button
                                        type="button"
                                        @click="swapTeams"
                                        class="w-12 h-12 rounded-full bg-white border-2 border-gray-300 text-gray-900 shadow-md hover:bg-gray-50 hover:border-gray-400 transition-all"
                                        title="Đổi bên hai đội"
                                    >
                                        ⇄
                                    </button>
                                </div>

                                <div class="mt-4 text-center text-sm text-gray-600">
                                    <template v-if="matchStarted">
                                        Giao bóng: <span class="font-bold" :class="servingTeam === 'team1' ? 'text-blue-600' : 'text-red-600'">{{ currentServerName }}</span>
                                        <span class="ml-2 text-gray-400 text-xs">(Tay {{ isFirstServe ? '1*' : serverNumber }})</span>
                                    </template>
                                    <template v-else>
                                        <span>Bấm <span class="font-bold">POINT +1</span> để bắt đầu trận</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white px-6 py-4 border-t border-gray-100 flex-shrink-0 space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="handleSideOut"
                                :disabled="!canStartMatch"
                                class="py-4 rounded-xl font-bold text-base transition-all disabled:opacity-40 shadow-sm"
                                :class="canStartMatch ? 'bg-red-600 hover:bg-red-700 active:bg-red-800 text-white' : 'bg-red-100 text-red-300'"
                            >
                                SIDE OUT
                                <span class="block text-xs font-normal opacity-80 mt-0.5">ĐỔI GIAO BÓNG</span>
                            </button>
                            <button
                                type="button"
                                @click="handlePoint"
                                :disabled="!canStartMatch"
                                class="py-4 rounded-xl font-bold text-base transition-all disabled:opacity-40 shadow-sm"
                                :class="canStartMatch ? 'bg-green-600 hover:bg-green-700 active:bg-green-800 text-white' : 'bg-green-100 text-green-300'"
                            >
                                POINT +1
                                <span class="block text-xs font-normal opacity-80 mt-0.5">GHI ĐIỂM</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="handleUndo"
                                :disabled="actionHistory.length === 0"
                                class="py-3.5 rounded-xl font-medium text-sm transition-all disabled:opacity-50 border-2"
                                :class="actionHistory.length > 0 ? 'bg-gray-50 hover:bg-gray-100 text-gray-800 border-gray-200 hover:border-gray-300' : 'bg-gray-50 text-gray-400 border-gray-100'"
                            >
                                ↩ Hoàn tác
                            </button>
                            <button
                                type="button"
                                @click="handleFinishSet"
                                class="py-3.5 bg-white hover:bg-gray-50 text-gray-800 rounded-xl font-semibold text-sm transition-all disabled:opacity-50 border-2 border-gray-200 hover:border-gray-300"
                                :disabled="!matchStarted"
                            >
                                ✓ Xong Set
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
import { ref, computed, onMounted } from 'vue'

export default {
    name: 'RefereeScoringScreen',
    props: {
        team1: { type: Object, required: true },
        team2: { type: Object, required: true },
        miniTournament: { type: Object, required: true },
        initialScores: { type: Array, default: () => [] }
    },
    emits: ['done', 'back'],

    setup(props, { emit }) {
        const basePoints = computed(() => props.miniTournament?.base_points || 11)
        const pointsDifference = computed(() => props.miniTournament?.points_difference || 2)
        const maxPoints = computed(() => props.miniTournament?.max_points || 15)
        const setNumber = computed(() => props.miniTournament?.set_number || 3)

        const isDoubles = computed(() => {
            const t1 = props.team1?.members?.length || 0
            const t2 = props.team2?.members?.length || 0
            return t1 >= 2 && t2 >= 2
        })

        const tournamentLabel = computed(() => props.miniTournament?.name || '')
        const rulesLabel = computed(() =>
            `${basePoints.value} Điểm · Cách ${pointsDifference.value} · ${setNumber.value} Sets`
        )

        const currentSetIndex = ref(0)
        const completedSets = ref([])
        const team1Score = ref(0)
        const team2Score = ref(0)

        const servingTeam = ref('team1')
        const serverNumber = ref(1)
        const isFirstServe = ref(true)
        const matchStarted = ref(false)

        // handIndex: người đang cầm giao bóng trong đội (tay 1 / tay 2)
        const serverHandIndex = ref(0)
        // serveBoxIndex: ô phát bóng (đổi ô khi ghi điểm), KHÔNG đổi người
        const serveBoxIndex = ref(0)
        const selectingServer = ref(false)

        const courtPositions = ref({
            team1: [],
            team2: []
        })

        const actionHistory = ref([])

        const canStartMatch = computed(() => {
            const team = servingTeam.value
            const pos = serverHandIndex.value
            return Boolean(courtPositions.value?.[team]?.[pos])
        })

        const canScore = computed(() => {
            if (!matchStarted.value) return false
            return canStartMatch.value
        })

        const initCourt = () => {
            const normalize = (members) => {
                return (members || []).map(m => {
                    if (!m) return null
                    const user = m?.user ?? m
                    return {
                        id: user?.id ?? m?.id,
                        full_name: user?.full_name ?? m?.full_name ?? 'N/A',
                        avatar_url: user?.avatar_url ?? m?.avatar_url ?? '',
                    }
                }).filter(Boolean)
            }
            courtPositions.value = {
                team1: normalize(props.team1?.members),
                team2: normalize(props.team2?.members)
            }
        }

        const safeClone = (obj) => {
            // structuredClone có thể tồn tại nhưng vẫn throw (DataCloneError) nếu object chứa ref không clone được (vd: window)
            if (typeof structuredClone === 'function') {
                try {
                    return structuredClone(obj)
                } catch (e) {
                    // eslint-disable-next-line no-unused-vars
                    const _ignored = e
                    // fallback bên dưới
                }
            }

            const seen = new WeakSet()
            const json = JSON.stringify(obj, (key, value) => {
                if (typeof value === 'function') return undefined
                if (typeof value === 'symbol') return undefined
                if (value && typeof value === 'object') {
                    // loại bỏ các object không serialize được / gây vòng lặp
                    // eslint-disable-next-line no-undef
                    if (typeof globalThis !== 'undefined' && value === globalThis) return undefined
                    // eslint-disable-next-line no-undef
                    if (typeof document !== 'undefined' && value === document) return undefined
                    if (seen.has(value)) return undefined
                    seen.add(value)
                }
                return value
            })

            return JSON.parse(json)
        }

        const getSnapshot = () => safeClone({
            team1Score: team1Score.value,
            team2Score: team2Score.value,
            servingTeam: servingTeam.value,
            serverNumber: serverNumber.value,
            isFirstServe: isFirstServe.value,
            serverHandIndex: serverHandIndex.value,
            serveBoxIndex: serveBoxIndex.value,
            selectingServer: selectingServer.value,
            courtPositions: courtPositions.value,
            completedSets: completedSets.value,
            currentSetIndex: currentSetIndex.value,
            matchStarted: matchStarted.value,
        })

        const restoreSnapshot = (snap) => {
            team1Score.value = snap.team1Score
            team2Score.value = snap.team2Score
            servingTeam.value = snap.servingTeam
            serverNumber.value = snap.serverNumber
            isFirstServe.value = snap.isFirstServe
            serverHandIndex.value = snap.serverHandIndex ?? 0
            serveBoxIndex.value = snap.serveBoxIndex ?? 0
            selectingServer.value = Boolean(snap.selectingServer)
            courtPositions.value = snap.courtPositions
            completedSets.value = snap.completedSets
            currentSetIndex.value = snap.currentSetIndex
            matchStarted.value = Boolean(snap.matchStarted)
        }

        const pushHistory = () => {
            actionHistory.value.push(getSnapshot())
        }

        const isServingPosition = (team, posIdx) => {
            if (team !== servingTeam.value) return false
            return posIdx === serverHandIndex.value
        }

        const getPositionLabel = (team, posIdx) => {
            return `Vị trí ${posIdx + 1}`
        }

        const currentServerName = computed(() => {
            const team = servingTeam.value
            const positions = courtPositions.value[team]
            const player = positions[serverHandIndex.value]
            return player?.full_name || 'N/A'
        })

        const chooseBall = () => {
            // Trước khi bắt đầu: dùng để chọn đội cầm bóng đầu tiên
            if (!matchStarted.value) {
                servingTeam.value = servingTeam.value === 'team1' ? 'team2' : 'team1'
                serverNumber.value = 1
                serverHandIndex.value = 0
                serveBoxIndex.value = 0
                isFirstServe.value = true
            }
        }

        const toggleSelectingServer = () => {
            if (matchStarted.value) return
            selectingServer.value = !selectingServer.value
        }

        const setInitialServer = (team, posIdx) => {
            if (matchStarted.value) return
            const p = courtPositions.value?.[team]?.[posIdx]
            if (!p) return
            servingTeam.value = team
            serverHandIndex.value = posIdx
            serveBoxIndex.value = 0
            serverNumber.value = 1
            isFirstServe.value = true
            selectingServer.value = false
        }

        const swapPositions = (team) => {
            if (matchStarted.value) return
            if (!isDoubles.value) return
            const arr = courtPositions.value[team]
            if (arr.length >= 2) {
                courtPositions.value[team] = [arr[1], arr[0]]
                if (servingTeam.value === team) {
                    serverHandIndex.value = serverHandIndex.value === 0 ? 1 : 0
                }
            }
        }

        const swapTeams = () => {
            const current = courtPositions.value
            courtPositions.value = {
                team1: current.team2,
                team2: current.team1
            }
            if (!matchStarted.value) {
                const prevServing = servingTeam.value
                servingTeam.value = prevServing === 'team1' ? 'team2' : 'team1'
            }
        }

        // checkSetWin đã bỏ dùng vì không auto sang set nữa

        const handlePoint = () => {
            // Cho phép bấm POINT để bắt đầu trận (khi đã có người cầm bóng)
            if (!matchStarted.value) {
                if (!canStartMatch.value) return
                matchStarted.value = true
            }
            if (!canScore.value) return
            pushHistory()

            if (servingTeam.value === 'team1') {
                team1Score.value++
            } else {
                team2Score.value++
            }

            if (isDoubles.value) {
                // Ghi điểm khi đang giao:
                // - người giao (tay 1 / tay 2) KHÔNG đổi
                // - chỉ đổi ô phát bóng (giống cầu lông)
                serveBoxIndex.value = serveBoxIndex.value === 0 ? 1 : 0
            }

            if (isFirstServe.value) {
                isFirstServe.value = false
            }
            // Không tự động sang set mới khi đủ điểm.
            // Trọng tài sẽ chủ động bấm "Xong Set" để chốt điểm và chuyển set.
        }

        const startMatchIfPossible = () => {
            if (matchStarted.value) return true
            if (!canStartMatch.value) return false
            matchStarted.value = true
            return true
        }

        const switchToOpponentFirstServer = () => {
            servingTeam.value = servingTeam.value === 'team1' ? 'team2' : 'team1'
            serverNumber.value = 1
            serverHandIndex.value = 0
            serveBoxIndex.value = 0
        }

        const handleSideOut = () => {
            // Cho phép bấm SIDE OUT để bắt đầu trận (khi đã có người cầm bóng)
            if (!startMatchIfPossible()) return
            if (!canScore.value) return

            pushHistory()

            if (isFirstServe.value) {
                switchToOpponentFirstServer()
                isFirstServe.value = false
                return
            }

            if (!isDoubles.value) {
                switchToOpponentFirstServer()
                return
            }

            if (serverNumber.value === 1) {
                serverNumber.value = 2
                // Đổi người giao trong cùng đội (tay 2), KHÔNG đổi ô (điểm không đổi)
                serverHandIndex.value = serverHandIndex.value === 0 ? 1 : 0
                return
            }

            switchToOpponentFirstServer()
        }

        const handleUndo = () => {
            if (actionHistory.value.length === 0) return
            const snap = actionHistory.value.pop()
            restoreSnapshot(snap)
        }

        const finishCurrentSet = () => {
            completedSets.value.push({
                team1: team1Score.value,
                team2: team2Score.value
            })

            team1Score.value = 0
            team2Score.value = 0
            currentSetIndex.value++

            const loser = completedSets.value[completedSets.value.length - 1].team1 <
                          completedSets.value[completedSets.value.length - 1].team2
                          ? 'team1' : 'team2'
            servingTeam.value = loser
            serverNumber.value = 1
            serverHandIndex.value = 0
            serveBoxIndex.value = 0
            isFirstServe.value = true
            actionHistory.value = []

            initCourt()
        }

        const handleFinishSet = () => {
            if (!matchStarted.value) return

            if (team1Score.value === 0 && team2Score.value === 0) return

            finishCurrentSet()
        }

        const handleTimeout = () => {
            // Placeholder for timeout functionality
        }

        const goBack = () => {
            const allScores = [
                ...completedSets.value,
            ]
            if (team1Score.value > 0 || team2Score.value > 0) {
                allScores.push({
                    team1: team1Score.value,
                    team2: team2Score.value
                })
            }

            if (allScores.length > 0) {
                emit('done', allScores)
            } else {
                emit('back')
            }
        }

        onMounted(() => {
            initCourt()

            if (props.initialScores && props.initialScores.length > 0) {
                const nonEmpty = props.initialScores.filter(s => s.team1 > 0 || s.team2 > 0)
                if (nonEmpty.length > 0) {
                    completedSets.value = nonEmpty.map(s => ({
                        team1: Number(s.team1),
                        team2: Number(s.team2)
                    }))
                    currentSetIndex.value = completedSets.value.length
                }
            }
        })

        return {
            basePoints,
            pointsDifference,
            maxPoints,
            setNumber,
            isDoubles,
            tournamentLabel,
            rulesLabel,
            currentSetIndex,
            completedSets,
            team1Score,
            team2Score,
            servingTeam,
            serverNumber,
            isFirstServe,
            matchStarted,
            serverHandIndex,
            serveBoxIndex,
            selectingServer,
            canStartMatch,
            canScore,
            courtPositions,
            actionHistory,
            currentServerName,
            isServingPosition,
            getPositionLabel,
            chooseBall,
            toggleSelectingServer,
            setInitialServer,
            swapPositions,
            swapTeams,
            handlePoint,
            handleSideOut,
            handleUndo,
            handleFinishSet,
            handleTimeout,
            goBack,
        }
    }
}
</script>
