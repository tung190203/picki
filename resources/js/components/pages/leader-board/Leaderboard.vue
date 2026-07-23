<template>
    <div class="min-h-screen bg-gray-50 px-4 py-8">
      <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Bộ lọc -->
        <aside class="bg-white shadow-md rounded-xl p-6 space-y-6">
          <h2 class="text-xl font-semibold text-gray-800">Bộ lọc</h2>
  
          <!-- Tìm kiếm -->
          <div>
            <label class="block text-sm text-gray-600 mb-1">Tìm kiếm</label>
            <input v-model="searchQuery" type="text" placeholder="Nhập tên hoặc CLB..." class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500" />
          </div>
  
          <!-- CLB -->
          <div>
            <label class="block text-sm text-gray-600 mb-1">CLB</label>
            <select v-model="selectedClub" class="w-full px-4 py-2 border rounded-md">
              <option value="">Tất cả</option>
              <option v-for="club in uniqueClubs" :key="club" :value="club">
                {{ club }}
              </option>
            </select>
          </div>
  
          <!-- Khu vực -->
          <div>
            <label class="block text-sm text-gray-600 mb-1">Khu vực</label>
            <select v-model="selectedRegion" class="w-full px-4 py-2 border rounded-md">
              <option value="">Tất cả</option>
              <option v-for="region in uniqueRegions" :key="region" :value="region">
                {{ region }}
              </option>
            </select>
          </div>
  
          <!-- PICKI -->
          <div>
            <label class="block text-sm text-gray-600 mb-2">PICKI</label>
            <div class="space-y-1">
              <label class="flex items-center gap-2"><input type="checkbox" value="1-2" v-model="selectedVNDUPR" />1.0 - 2.0</label>
              <label class="flex items-center gap-2"><input type="checkbox" value="2-3" v-model="selectedVNDUPR" />2.0 - 3.0</label>
              <label class="flex items-center gap-2"><input type="checkbox" value="3-4" v-model="selectedVNDUPR" />3.0 - 4.0</label>
            </div>
          </div>
  
          <!-- Tier -->
          <div>
            <label class="block text-sm text-gray-600 mb-1">Tier</label>
            <select v-model="selectedTier" class="w-full px-4 py-2 border rounded-md">
              <option value="">Tất cả</option>
              <option v-for="tier in uniqueTiers" :key="tier" :value="tier">
                {{ tier }}
              </option>
            </select>
          </div>
        </aside>
  
        <!-- Bảng xếp hạng -->
        <main class="lg:col-span-3 bg-white shadow-lg rounded-xl overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-800">Bảng xếp hạng người chơi</h1>
            <p class="text-sm text-gray-500">Xếp hạng theo điểm PICKI</p>
          </div>
  
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left text-gray-700">
              <thead class="bg-gray-100 uppercase text-xs font-semibold text-gray-600">
                <tr>
                  <th class="px-6 py-3">Rank</th>
                  <th class="px-6 py-3">Tên</th>
                  <th class="px-6 py-3">CLB</th>
                  <th class="px-6 py-3">Khu vực</th>
                  <th class="px-6 py-3">Tier</th>
                  <th class="px-6 py-3">PICKI</th>
                  <th class="px-6 py-3">Tuần</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(player, index) in filteredPlayers" :key="player.id" class="border-b hover:bg-gray-50">
                  <td class="px-6 py-4 font-semibold">{{ index + 1 }}</td>
                  <td class="px-6 py-4 flex items-center space-x-3">
                    <img :src="player.avatar" alt="Avatar" class="w-9 h-9 rounded-full object-cover" />
                    <span class="font-medium truncate max-w-[140px]">{{ player.name }}</span>
                  </td>
                  <td class="px-6 py-4 truncate max-w-[150px]">{{ player.club }}</td>
                  <td class="px-6 py-4">{{ player.region }}</td>
                  <td class="px-6 py-4 font-bold text-blue-600">{{ player.tier }}</td>
                  <td class="px-6 py-4 font-bold text-blue-600">{{ player.vndupr }}</td>
                  <td class="px-6 py-4">
                    <span v-if="player.weekly_change !== undefined && player.weekly_change !== null"
                      class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-semibold"
                      :class="getWeeklyChangeClass(player.weekly_change)">
                      <span v-if="player.weekly_change > 0">↑</span>
                      <span v-else-if="player.weekly_change < 0">↓</span>
                      <span v-else>-</span>
                      {{ Math.abs(player.weekly_change) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
  
          <div class="p-4 text-sm text-gray-500 text-center">
            Tổng số người chơi: {{ filteredPlayers.length }}
          </div>
        </main>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, computed } from 'vue'
  
  const players = ref([
    { id: 1, name: 'Nguyễn Văn A', club: 'Pickleball Hà Nội', region: 'Hà Nội', tier: 'S1', avatar: 'https://i.pravatar.cc/150?img=3', vndupr: 4.0 },
    { id: 2, name: 'Trần Thị B', club: 'CLB Saigon Pickle', region: 'TP.HCM', tier: 'S2', avatar: 'https://i.pravatar.cc/150?img=5', vndupr: 3.8 },
    { id: 3, name: 'Lê Văn C', club: 'Đà Nẵng Smashers', region: 'Đà Nẵng', tier: 'S3', avatar: 'https://i.pravatar.cc/150?img=7', vndupr: 3.2 },
    { id: 4, name: 'Phạm Thị D', club: 'Hải Phòng Storm', region: 'Hải Phòng', tier: 'B2', avatar: 'https://i.pravatar.cc/150?img=9', vndupr: 2.5 },
    { id: 5, name: 'Ngô Văn E', club: 'Cần Thơ Warriors', region: 'Cần Thơ', tier: 'C1', avatar: 'https://i.pravatar.cc/150?img=11', vndupr: 2.0 },
    { id: 6, name: 'Trương Thị F', club: 'Hồ Chí Minh City Aces', region: 'TP.HCM', tier: 'D5', avatar: 'https://i.pravatar.cc/150?img=13', vndupr: 1.8 },
    { id: 7, name: 'Đỗ Văn G', club: 'Nha Trang Titans', region: 'Khánh Hòa', tier: 'F8', avatar: 'https://i.pravatar.cc/150?img=15', vndupr: 1.5 }
  ])
  
  const searchQuery = ref('')
  const selectedClub = ref('')
  const selectedTier = ref('')
  const selectedRegion = ref('')
  const selectedVNDUPR = ref([])
  
  const uniqueClubs = computed(() => [...new Set(players.value.map(p => p.club))])
  const uniqueTiers = computed(() => [...new Set(players.value.map(p => p.tier))])
  const uniqueRegions = computed(() => [...new Set(players.value.map(p => p.region))])
  
  const filteredPlayers = computed(() => {
    return players.value.filter(player => {
      const matchesName = player.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        player.club.toLowerCase().includes(searchQuery.value.toLowerCase())
  
      const matchesClub = selectedClub.value ? player.club === selectedClub.value : true
      const matchesTier = selectedTier.value ? player.tier === selectedTier.value : true
      const matchesRegion = selectedRegion.value ? player.region === selectedRegion.value : true
  
      const matchesVNDUPR = selectedVNDUPR.value.length === 0 ||
        selectedVNDUPR.value.some(range => {
          const [min, max] = range.split('-').map(Number)
          return player.vndupr >= min && player.vndupr < max
        })
  
      return matchesName && matchesClub && matchesTier && matchesRegion && matchesVNDUPR
    })
  })

  const getWeeklyChangeClass = (change) => {
    if (change > 0) return 'bg-green-100 text-green-700';
    if (change < 0) return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-500';
  }
  </script>
  