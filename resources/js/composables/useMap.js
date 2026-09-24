// src/composables/useMap.js
import { onMounted, onUnmounted, watch } from 'vue';
import goongjs from '@goongmaps/goong-js';
import '@goongmaps/goong-js/dist/goong-js.css';
import Supercluster from 'supercluster';
import { toast } from 'vue3-toastify';
import axiosInstance from '@/utils/httpRequest.js';
import { isDark } from '@/utils/theme.js';

// --- CONSTANTS ---
const DEFAULT_CENTER = [105.8542, 21.0285]; // [lng, lat] for Goong JS
const DEFAULT_ZOOM = 10;
const VIETNAM_BOUNDS = [
    [102.0, 8.0],  // SW [lng, lat]
    [109.5, 23.5],  // NE [lng, lat]
];
const VIETNAM_CENTER = [105.8542, 21.0285];

// --- UTILITY FUNCTIONS ---
const escapeHtml = (text) => {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
};

const formatDateText = (date) => {
  if (!date) return '';
  const d = new Date(date);
  const days = ['Chu nhat', 'Thu hai', 'Thu ba', 'Thu tu', 'Thu nam', 'Thu sau', 'Thu bay'];
  const dayName = days[d.getDay()];
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  return `${dayName}, ${day}/${month}`;
};

const formatTimeRange = (start, duration_minutes) => {
  if (!start || !duration_minutes) return '';
  const startTime = new Date(start);
  const endTime = new Date(startTime.getTime() + duration_minutes * 60000);

  const formatTime = (date) => {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${hours}:${minutes}`;
  };

  return `${formatTime(startTime)} - ${formatTime(endTime)}`;
};

// --- ICONS (SVG strings reused verbatim from old implementation) ---
const clockIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>`;
const phoneIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>`;
const mapPinIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>`;
const calendarIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>`;
const calendarDaysIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9 6V9m0 0h-3.75m3.75 0h3.75" /></svg>`;
const clockIconSmall = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>`;
const mapPinIconSmall = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>`;
const lockIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>`;
const lockOpenIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>`;
const flagIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path fill-rule="evenodd" d="M3 2.25a.75.75 0 0 1 .75.75v16.5a.75.75 0 0 1-1.5 0V3A.75.75 0 0 1 3 2.25ZM19.5 3a.75.75 0 0 0-1.5 0v7.5a.75.75 0 0 0 1.5 0V3Zm-5.25 0a.75.75 0 0 0-1.5 0v16.5a.75.75 0 0 0 1.5 0V3ZM12 9a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-1.5 0v-7.5A.75.75 0 0 1 12 9Zm-3.75-6a.75.75 0 0 0-1.5 0v16.5a.75.75 0 0 0 1.5 0V3Z" clip-rule="evenodd" /></svg>`;
const starIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>`;
const userGroupIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>`;

// Track current layer style so theme watcher and layer switcher don't fight each other
let activeLayerId = 'street';

// Map styles available
const MAP_STYLES = {
  street: 'https://tiles.goong.io/assets/goong_map_web.json',
  light: 'https://tiles.goong.io/assets/goong_light_v2.json',
  dark: 'https://tiles.goong.io/assets/goong_map_dark.json',
  navigation: 'https://tiles.goong.io/assets/navigation_day.json',
  navigationNight: 'https://tiles.goong.io/assets/navigation_night.json',
};

// Supercluster options matching old MarkerClusterGroup behaviour
const CLUSTER_OPTIONS = {
  radius: 80,
  maxZoom: 15,   // disableClusteringAtZoom equivalent (16 -> 15 for 0-indexed)
  minZoom: 0,
};

// --- COMPOSABLE ---
export function useMap() {
  let map = null;
  let currentLocationMarker = null;
  let resizeHandler = null;
  let loadDataCallback = null;
  let isUserInteraction = false;
  let isFocusing = false;

  // Supercluster instances for different marker types
  const clusters = Object.create(null);

  // Track goongjs markers (key: markerId -> goongjs marker instance)
  const goongMarkers = Object.create(null);

  // --- LOAD MAP CONFIG ---
  let configLoaded = false;
  let configError = false;

  async function loadMapConfig() {
    if (configLoaded || configError) return;
    try {
      const res = await axiosInstance.get('/map/public-config');
      goongjs.accessToken = res.data.data.map_key;
      configLoaded = true;
    } catch (err) {
      configError = true;
      console.error('[useMap] Failed to load map config:', err);
    }
  }

  // --- SUPERCLUSTER HELPERS ---
  function getCluster(type) {
    if (!clusters[type]) {
      clusters[type] = new Supercluster({
        ...CLUSTER_OPTIONS,
        // Use 'id' as point property for marker identification
        map: (props) => props,
        reduce: (accumulated, props) => { /* accumulate markers */ },
      });
    }
    return clusters[type];
  }

  function clusterIconCreate(childCount) {
    let bgColor;
    if (childCount < 10) {
      bgColor = '#4392E0';
    } else if (childCount < 99) {
      bgColor = '#F59E0B';
    } else {
      bgColor = '#D72D36';
    }
    return `<div style="
      background: ${bgColor};
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-weight: 700;
      font-size: 17px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.4), 0 2px 4px rgba(0,0,0,0.3);
      border: 2px solid white;
    ">${childCount}</div>`;
  }

  function renderClusterMarkers(mapInstance, sourceId) {
    const bounds = mapInstance.getBounds();
    if (!bounds) return;

    const zoom = Math.floor(mapInstance.getZoom());

    // Remove old cluster markers (those with cluster-* id)
    Object.keys(goongMarkers).forEach((key) => {
      if (key.startsWith('cluster-')) {
        const m = goongMarkers[key];
        m.remove();
        delete goongMarkers[key];
      }
    });

    const bbox = [
      bounds.getWest(),
      bounds.getSouth(),
      bounds.getEast(),
      bounds.getNorth(),
    ];

    const cluster = getCluster(sourceId);
    if (!cluster.getClusters) return;

    const rawClusters = cluster.getClusters(bbox, zoom);

    rawClusters.forEach((item) => {
      if (item.properties.cluster) {
        // It's a cluster
        const [lng, lat] = item.geometry.coordinates;
        const count = item.properties.point_count;

        const el = document.createElement('div');
        el.innerHTML = clusterIconCreate(count);
        el.style.cursor = 'pointer';

        const marker = new goongjs.Marker({ element: el })
          .setLngLat([lng, lat])
          .addTo(mapInstance);

        el.onclick = () => {
          const expansionZoom = Math.min(
            cluster.getClusterExpansionZoom(item.properties.cluster_id),
            18
          );
          mapInstance.flyTo({ center: [lng, lat], zoom: expansionZoom });
        };

        goongMarkers[`cluster-${item.id}`] = marker;
      }
      // Individual points are handled by addCourtMarkers etc. via updateMarkers
    });
  }

  // --- INIT MAP ---
  const initMap = (initialLoadCallback, onMapMoveCallback) => {
    onMounted(async () => {
      await loadMapConfig();

      if (configError) {
        const mapEl = document.getElementById('map');
        if (mapEl) {
          mapEl.innerHTML = `
            <div style="
              display: flex;
              flex-direction: column;
              align-items: center;
              justify-content: center;
              height: 100%;
              font-family: system-ui;
              color: #6b7280;
              gap: 12px;
            ">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 48px; height: 48px; color: #9ca3af;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
              </svg>
              <p style="margin:0; font-size: 14px;">Ban do chua duoc cau hinh.<br>Vui long lien he admin de cau hinh Goong Map.</p>
            </div>
          `;
        }
        return;
      }

      map = new goongjs.Map({
        container: 'map',
        style: isDark.value ? MAP_STYLES.dark : MAP_STYLES.street,
        center: DEFAULT_CENTER,
        zoom: DEFAULT_ZOOM,
        maxBounds: VIETNAM_BOUNDS,
        minZoom: 9,
        maxZoom: 18,
      });

      loadDataCallback = onMapMoveCallback;

      // Wait for map to load
      map.on('load', () => {
        setupCustomControls();

        // Initial data load
        if (initialLoadCallback) {
          setTimeout(() => {
            const bounds = map.getBounds();
            initialLoadCallback(bounds);
          }, 200);
        }
      });

      // Track user interaction
      map.on('dragstart', () => {
        isUserInteraction = true;
        isFocusing = false;
      });
      map.on('zoomstart', () => {
        if (!isFocusing) isUserInteraction = true;
      });

      // Debounced moveend
      let moveEndTimeout;
      map.on('moveend', () => {
        clearTimeout(moveEndTimeout);
        moveEndTimeout = setTimeout(() => {
          if (loadDataCallback && map && isUserInteraction && !isFocusing) {
            const bounds = map.getBounds();
            loadDataCallback(bounds);
          }
          isUserInteraction = false;
          isFocusing = false;
        }, 800);
      });

      // Resize handler
      resizeHandler = () => {
        map.resize();
      };
      window.addEventListener('resize', resizeHandler);
    });

    // Auto-switch map style when theme changes
    watch(isDark, (dark) => {
      if (!map) return;
      // Skip if user explicitly picked a locked dark/night layer
      if (activeLayerId === 'dark' || activeLayerId === 'navigationNight') return;

      const newStyle = dark ? MAP_STYLES.dark : MAP_STYLES.street;
      map.setStyle(newStyle);
      map.once('styledata', () => {
        Object.values(goongMarkers).forEach((m) => m.addTo(map));
      });
    });

    // Cleanup
    onUnmounted(() => {
      if (resizeHandler) window.removeEventListener('resize', resizeHandler);
      if (map) {
        map.remove();
        map = null;
      }
      // Clear clusters and markers
      Object.values(clusters).forEach((c) => {
        if (c.clear) c.clear();
      });
      Object.keys(goongMarkers).forEach((k) => delete goongMarkers[k]);
      configLoaded = false;
      configError = false;
    });
  };

  // --- CUSTOM CONTROLS ---
  const buttonBaseStyle = 'background-color: white; width: 44px; height: 44px; cursor: pointer; display: flex; align-items: center; justify-content: center; margin: 10px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); border: 1px solid #e5e7eb; transition: background-color 0.15s;';
  const buttonBaseNoMargin = 'background-color: white; width: 44px; height: 44px; cursor: pointer; display: flex; align-items: center; justify-content: center; margin: 10px; margin-top: 0; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); border: 1px solid #e5e7eb; transition: background-color 0.15s;';

  function makeIconButton(svg, title, onClick, withTopMargin = true) {
    const btn = document.createElement('button');
    btn.innerHTML = svg;
    btn.title = title;
    btn.style.cssText = withTopMargin ? buttonBaseStyle : buttonBaseNoMargin;
    btn.onmouseenter = () => (btn.style.backgroundColor = '#f1f5f9');
    btn.onmouseleave = () => (btn.style.backgroundColor = 'white');
    btn.onclick = onClick;
    return btn;
  }

  const zoomInSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#334155" style="width: 20px; height: 20px; margin: auto; display: block;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>`;
  const zoomOutSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#334155" style="width: 20px; height: 20px; margin: auto; display: block;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>`;
  const resetSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#334155" style="width: 20px; height: 20px; margin: auto; display: block;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>`;
  const locationSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#334155" style="width: 20px; height: 20px; margin: auto; display: block;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>`;

  function setupCustomControls() {
    if (!map) return;

    // Zoom / Reset / Location grouped control (top-right)
    class ToolsControl {
      onAdd() {
        this._container = document.createElement('div');
        this._container.className = 'mapboxgl-ctrl';
        this._container.style.cssText = 'background: transparent; border-radius: 8px; display: flex; flex-direction: column;';
        this._container.appendChild(makeIconButton(zoomInSvg, 'Phong to', () => map.zoomIn(), false));
        this._container.appendChild(makeIconButton(zoomOutSvg, 'Thu nho', () => map.zoomOut(), false));
        this._container.appendChild(makeIconButton(resetSvg, 'Dat lai ban do', () => resetMap(), false));
        this._container.appendChild(makeIconButton(locationSvg, 'Vi tri hien tai cua toi', () => getCurrentLocation(), false));
        return this._container;
      }
      onRemove() {
        if (this._container) this._container.remove();
      }
    }
    map.addControl(new ToolsControl(), 'top-right');

    setupLayerSwitcher();
  }

  function setupLayerSwitcher() {
    if (!map) return;
    const layers = [
      { id: 'street', label: 'Mặc định' },
      { id: 'navigation', label: 'Đường ban ngày' },
      { id: 'navigationNight', label: 'Đường ban đêm' },
      { id: 'dark', label: 'Ban đêm' },
    ].map((l) => ({ ...l, icon: '', style: () => MAP_STYLES[l.id] }));

    let open = false;
    let menuEl = null;

    const fab = document.createElement('button');
    fab.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#334155" style="width: 20px; height: 20px; margin: auto; display: block;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>`;
    fab.title = 'Chọn lớp bản đồ';
    fab.style.cssText = buttonBaseStyle;
    fab.onmouseenter = () => (fab.style.backgroundColor = '#f1f5f9');
    fab.onmouseleave = () => (fab.style.backgroundColor = 'white');

    const closeMenu = () => {
      open = false;
      if (menuEl) {
        menuEl.remove();
        menuEl = null;
      }
    };

    const renderMenu = () => {
      menuEl = document.createElement('div');
      menuEl.style.cssText = 'position: absolute; top: 54px; left: 0; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.18); overflow: hidden; min-width: 180px; z-index: 1;';

      const title = document.createElement('div');
      title.textContent = 'Lớp bản đồ';
      title.style.cssText = 'padding: 10px 14px; font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb;';
      menuEl.appendChild(title);

      layers.forEach((l) => {
        const row = document.createElement('button');
        const isActive = l.id === activeLayerId;
        row.style.cssText = `display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 14px; background: ${isActive ? '#fef2f2' : 'white'}; border: none; cursor: pointer; font-size: 13px; color: ${isActive ? '#af101a' : '#374151'}; text-align: left; font-weight: ${isActive ? '600' : '500'};`;
        row.innerHTML = `<span>${l.label}</span>${isActive ? '<span style="margin-left: auto; color: #af101a;">✓</span>' : ''}`;
        row.onmouseenter = () => { if (!isActive) row.style.backgroundColor = '#f1f5f9'; };
        row.onmouseleave = () => { row.style.backgroundColor = isActive ? '#fef2f2' : 'white'; };
        row.onclick = () => {
          activeLayerId = l.id;
          if (map) {
            map.setStyle(l.style());
            map.once('styledata', () => {
              Object.values(goongMarkers).forEach((m) => m.addTo(map));
            });
          }
          closeMenu();
        };
        menuEl.appendChild(row);
      });

      // Append next to fab (sibling inside same .mapboxgl-ctrl wrapper)
      fab.parentElement.appendChild(menuEl);
    };

    fab.onclick = (e) => {
      e.stopPropagation();
      if (open) {
        closeMenu();
      } else {
        open = true;
        renderMenu();
      }
    };

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!open) return;
      if (fab.contains(e.target) || (menuEl && menuEl.contains(e.target))) return;
      closeMenu();
    });

    // Layer switcher IControl (top-left)
    class LayerSwitcherControl {
      onAdd() {
        this._container = document.createElement('div');
        this._container.className = 'mapboxgl-ctrl';
        this._container.style.cssText = 'position: relative;';
        this._container.appendChild(fab);
        return this._container;
      }
      onRemove() {
        if (this._container) this._container.remove();
        closeMenu();
      }
    }
    map.addControl(new LayerSwitcherControl(), 'top-left');
  }

  // --- MAP CONTROLS ---
  const resetMap = () => {
    if (currentLocationMarker) {
      currentLocationMarker.remove();
      currentLocationMarker = null;
    }
    if (map) {
      map.flyTo({ center: DEFAULT_CENTER, zoom: DEFAULT_ZOOM, duration: 800 });
    }
  };

  const getCurrentLocation = () => {
    if (!navigator.geolocation) {
      toast.error('Trinh duyet khong ho tro dinh vi dia ly');
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const lng = position.coords.longitude;
        const lat = position.coords.latitude;

        if (map) map.flyTo({ center: [lng, lat], zoom: 16, duration: 800 });

        if (currentLocationMarker) {
          currentLocationMarker.remove();
        }

        currentLocationMarker = new goongjs.Marker({ color: '#ef4444' })
          .setLngLat([lng, lat])
          .setPopup(new goongjs.Popup({ offset: 25 }).setHTML('Vi tri hien tai cua ban'))
          .addTo(map)
          .togglePopup();
      },
      () => {
        toast.error('Khong the lay vi tri hien tai');
      },
      { timeout: 10000, maximumAge: 60000, enableHighAccuracy: true }
    );
  };

  // --- MARKER MANAGEMENT ---
  const clearAllMarkers = () => {
    if (map) {
      Object.values(goongMarkers).forEach((m) => {
        m.remove();
      });
    }
    Object.keys(goongMarkers).forEach((k) => delete goongMarkers[k]);
    Object.keys(clusters).forEach((k) => delete clusters[k]);
  };

  const updateMarkers = (newMarkersArray, sourceId) => {
    if (!map) return [];

    const existingIds = new Set(Object.keys(goongMarkers));
    const newIds = new Set(newMarkersArray.map((m) => m.id));

    // Remove stale markers
    existingIds.forEach((id) => {
      if (!newIds.has(id)) {
        const marker = goongMarkers[id];
          if (marker) {
            marker.remove();
            delete goongMarkers[id];
        }
      }
    });

    // Load into supercluster
    const cluster = getCluster(sourceId);
    const points = newMarkersArray.map((m) => ({
      type: 'Feature',
      properties: { id: m.id, ...m },
      geometry: {
        type: 'Point',
        coordinates: [m.lng ?? m.longitude, m.lat ?? m.latitude],
      },
    }));

    cluster.load(points);

    // Remove old cluster markers (they'll be re-rendered below)
    Object.keys(goongMarkers).forEach((key) => {
      if (key.startsWith('cluster-')) {
        const m = goongMarkers[key];
        m.remove();
        delete goongMarkers[key];
      }
    });

    renderClusterMarkers(map, sourceId);

    return newMarkersArray.filter((item) => !existingIds.has(item.id));
  };

  // --- FOCUS ITEM ---
  const focusItem = (itemId, sourceId) => {
    if (!map) return;
    const marker = goongMarkers[itemId];
    if (marker) {
      isFocusing = true;
      isUserInteraction = false;
      const lngLat = marker.getLngLat();
      map.flyTo({ center: [lngLat.lng, lngLat.lat], zoom: 17, duration: 800 });
      setTimeout(() => {
        if (marker.getPopup()) marker.togglePopup();
      }, 900);
    }
  };

  // Re-export focusItem that accepts old signature (itemId only)
  const focusItemLegacy = (itemId) => {
    // Try to find in all marker sets
    const marker = goongMarkers[itemId];
    if (marker) {
      isFocusing = true;
      isUserInteraction = false;
      const lngLat = marker.getLngLat();
      if (map) map.flyTo({ center: [lngLat.lng, lngLat.lat], zoom: 17, duration: 800 });
      setTimeout(() => {
        if (marker.getPopup()) marker.togglePopup();
      }, 900);
    }
  };

  // --- ADD MARKERS ---
  const addCourtMarkers = (
    courtsData,
    toHourMinute,
    defaultImage,
    onMarkerClick,
    shouldUpdate = false
  ) => {
    const normalized = courtsData.map((c) => {
      const cl = c.competition_location ?? c;
      return {
        id: cl.id ?? c.id,
        lat: parseFloat(cl.latitude),
        lng: parseFloat(cl.longitude),
        name: cl.name,
        address: cl.address,
        phone: cl.phone,
        opening_time: cl.opening_time,
        closing_time: cl.closing_time,
        image: cl.image,
        _original: c,
      };
    }).filter((cl) => !isNaN(cl.lat) && !isNaN(cl.lng));

    const toAdd = shouldUpdate ? updateMarkers(normalized, 'courts') : normalized;

    toAdd.forEach((cl) => {
      const popupContent = `
        <div style="min-width: 220px; font-family: system-ui; margin-top: 20px;">
          <img src="${cl.image || defaultImage}" alt="Court Image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;" onerror="this.onerror=null;this.src='${defaultImage}'" />
          <h3 style="margin: 0 0 10px 0; font-weight: 600; font-size: 16px; color: #1f2937;">${escapeHtml(cl.name)}</h3>
          <div style="display: flex; flex-direction: column; gap: 6px;">
            <p style="margin: 0; display:flex; justify-content:start; align-items:center; gap:6px; font-size: 14px; color: #4b5563;">
              <span style="color: #4392E0; font-weight: 500;">${clockIcon}</span>
              Gio Mo cua: ${toHourMinute(cl.opening_time)} - ${toHourMinute(cl.closing_time)}
            </p>
            ${cl.phone ? `
            <p style="margin: 0; display:flex; justify-content:start; align-items:center; gap:6px; font-size: 14px; color: #4b5563;">
              <span style="color: #4392E0; font-weight: 500;">${phoneIcon}</span>
              ${escapeHtml(cl.phone)}
            </p>` : ''}
            <p style="margin: 0; display:flex; justify-content:start; align-items:baseline; gap:6px; font-size: 14px; color: #4b5563; line-height: 1.4;">
              <span style="color: #4392E0; font-weight: 500;">${mapPinIcon}</span>
              ${escapeHtml(cl.address || '')}
            </p>
          </div>
        </div>
      `;

      const marker = new goongjs.Marker()
        .setLngLat([cl.lng, cl.lat])
        .setPopup(new goongjs.Popup({ maxWidth: 300, closeButton: true, closeOnClick: true }).setHTML(popupContent))
        .addTo(map);

      if (onMarkerClick) {
        marker.on('click', () => onMarkerClick(cl._original));
      }

      goongMarkers[cl.id] = marker;
    });

    // Update clusters if shouldUpdate
    if (shouldUpdate) {
      renderClusterMarkers(map, 'courts');
    }
  };

  const addUserMarkers = (
    usersData,
    defaultImage,
    maleIcon,
    femaleIcon,
    getVisibilityText,
    getUserRating,
    router,
    onMarkerClick,
    shouldUpdate = false
  ) => {
    const normalized = usersData.map((u) => ({
      id: u.id,
      lat: parseFloat(u.latitude),
      lng: parseFloat(u.longitude),
      _original: u,
    })).filter((u) => !isNaN(u.lat) && !isNaN(u.lng));

    const toAdd = shouldUpdate ? updateMarkers(normalized, 'users') : normalized;

    toAdd.forEach((u) => {
      const user = u._original;
      const rating = getUserRating(user);
      const genderText = user.gender_text || 'Khac';

      let statsHtml = '';
      if (user.distance != null) {
        statsHtml += `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; white-space: nowrap; background-color: #dbeafe; color: #1d4ed8; margin-right: 4px;">${mapPinIconSmall} ${user.distance} km</span>`;
      }
      if (user.vndupr_score != null) {
        statsHtml += `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; white-space: nowrap; background-color: #fff7ed; color: #c2410c; margin-right: 4px;">VNDUPR ${Number(user.vndupr_score).toFixed(1)}</span>`;
      }
      if (user.win_rate != null) {
        statsHtml += `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; white-space: nowrap; background-color: #f0fdf4; color: #15803d; margin-right: 4px;">${Number(user.win_rate).toFixed(1)}%</span>`;
      }
      if (user.total_matches != null && user.total_matches > 0) {
        statsHtml += `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; white-space: nowrap; background-color: #f5f3ff; color: #6d28d9; margin-right: 4px;">${user.total_matches} tran</span>`;
      }

      const popupContent = `
        <div id="user-popup-${user.id}" data-user-id="${user.id}" style="min-width: 250px; max-width: 300px; font-family: system-ui; cursor: pointer;">
          <div style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #e0e0e0; border-radius: 6px 6px 0 0; background: white;">
            <div style="position: relative; flex-shrink: 0; margin-right: 15px;">
              <img src="${user.avatar_url || defaultImage}" alt="Avatar" style="width: 60px; height: 60px; object-fit: cover; border-radius: 50%; border: 2px solid #ffffff; box-shadow: 0 0 0 2px #4392E0;" onerror="this.onerror=null;this.src='${defaultImage}'" />
              <div style="position: absolute; bottom: -3px; right: -3px; background-color: #f59e0b; color: white; border-radius: 10px; padding: 2px 6px; font-size: 11px; font-weight: 700; box-shadow: 0 1px 4px rgba(0,0,0,0.4); width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;">${rating}</div>
            </div>
            <div style="flex: 1; min-width: 0;">
              <h3 style="margin: 0; font-weight: 700; font-size: 16px; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(user.full_name)}</h3>
              <p style="margin: 3px 0 0 0; font-size: 13px; color: #6b7280;">Nguoi choi Pickleball</p>
            </div>
          </div>
          <div style="padding: 15px; background: white;">
            ${statsHtml ? `<div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6;">${statsHtml}</div>` : ''}
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #4b5563;">
                <span style="color: #4392E0;">${mapPinIcon}</span>
                <span>${escapeHtml(genderText)}</span>
              </div>
              <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 14px; color: #4b5563;">
                <span style="color: #4392E0;">${mapPinIcon}</span>
                <p style="margin: 0; line-height: 1.4; color: #374151;">${escapeHtml(user.address || '')}</p>
              </div>
            </div>
          </div>
        </div>
      `;

      const marker = new goongjs.Marker()
        .setLngLat([u.lng, u.lat])
        .setPopup(new goongjs.Popup({ maxWidth: 350, closeButton: true, closeOnClick: true }).setHTML(popupContent))
        .addTo(map);

      marker.on('click', () => {
        if (onMarkerClick) onMarkerClick(user);
        const el = document.getElementById(`user-popup-${user.id}`);
        if (el) el.onclick = () => router.push(`/profile/${user.id}`);
      });

      goongMarkers[user.id] = marker;
    });

    if (shouldUpdate) {
      renderClusterMarkers(map, 'users');
    }
  };

  const addMiniTournamentMarkers = (
    miniTournamentsData,
    router,
    onMarkerClick,
    shouldUpdate = false,
    defaultImage = ''
  ) => {
    const normalized = miniTournamentsData.map((m) => {
      const cl = m.competition_location;
      return {
        id: m.id,
        lat: parseFloat(m.lat ?? cl?.latitude),
        lng: parseFloat(m.lng ?? cl?.longitude),
        _original: m,
      };
    }).filter((m) => !isNaN(m.lat) && !isNaN(m.lng));

    const toAdd = shouldUpdate ? updateMarkers(normalized, 'mini-tournaments') : normalized;

    toAdd.forEach((m) => {
      const mini = m._original;
      const locationName = mini.competition_location?.name || mini.competition_location?.address || '';
      const dateText = formatDateText(mini.starts_at);
      const timeRange = formatTimeRange(mini.starts_at, mini.duration_minutes);

      let badgesHtml = '';
      if (mini.is_private !== undefined) {
        badgesHtml += `<span style="display:inline-flex;align-items:center;border-radius:9999px;padding:2px 8px;gap:4px;font-size:10px;font-weight:500;color:white;background:#1f2937;white-space:nowrap;margin-right:8px;">${mini.is_private ? lockIcon : lockOpenIcon} ${mini.is_private ? 'Private' : 'Public'}</span>`;
      }
      if (mini.min_rating || mini.max_rating) {
        badgesHtml += `<span style="display:inline-flex;align-items:center;gap:4px;border-radius:9999px;background:#1f2937;padding:2px 8px;font-size:10px;font-weight:500;color:white;white-space:nowrap;margin-right:8px;">${flagIcon} ${mini.min_rating || ''} - ${mini.max_rating || ''}</span>`;
      }
      if (mini.is_dupr) {
        badgesHtml += `<span style="display:inline-flex;align-items:center;gap:4px;border-radius:9999px;background:#1f2937;padding:2px 8px;font-size:10px;font-weight:500;color:white;white-space:nowrap;margin-right:8px;">${starIcon} DUPR</span>`;
      }
      if (mini.max_players) {
        badgesHtml += `<span style="display:inline-flex;align-items:center;gap:4px;border-radius:9999px;background:#1f2937;padding:2px 8px;font-size:10px;font-weight:500;color:white;white-space:nowrap;margin-right:8px;">${userGroupIcon} Max ${mini.max_players}</span>`;
      }

      const participants = mini.participants || [];
      let participantsHtml = '';
      if (participants.length > 0) {
        const displayParticipants = participants.slice(0, 2);
        participantsHtml = `<div style="display:flex;overflow:hidden;">${displayParticipants.map((p, idx) => `
          <img src="${p.avatar_url || defaultImage}" onerror="this.onerror=null;this.src='${defaultImage}'"
            style="width:40px;height:40px;border-radius:50%;border:2px solid white;object-fit:cover;${idx > 0 ? 'margin-left:-8px;' : ''}" />
        `).join('')}</div>`;
      } else if (mini.joined_count > 0) {
        participantsHtml = `<div style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#fef2f2;color:#D72D36;font-size:12px;font-weight:700;border:1px solid #fee2e2;">+${mini.joined_count}</div>`;
      }

      const popupContent = `
        <div id="mini-popup-${mini.id}" style="overflow:hidden;background:white;cursor:pointer;font-family:system-ui,sans-serif;min-width:280px;max-width:320px;">
          <div style="padding:12px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
              <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:8px;">
                <h3 style="font-weight:700;color:#111827;font-size:16px;margin:0;white-space:normal;">${escapeHtml(mini.name)}</h3>
                ${locationName ? `<div style="font-size:12px;color:#3b82f6;font-weight:500;">${mapPinIconSmall} ${escapeHtml(locationName)}</div>` : ''}
                <div style="font-size:12px;color:#4b5563;">${calendarDaysIcon} ${dateText} ${clockIconSmall} ${timeRange}</div>
              </div>
              ${participantsHtml ? `<div>${participantsHtml}</div>` : ''}
            </div>
            <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f3f4f6;overflow:hidden;">${badgesHtml}</div>
          </div>
        </div>
      `;

      const marker = new goongjs.Marker()
        .setLngLat([m.lng, m.lat])
        .setPopup(new goongjs.Popup({ maxWidth: 350, closeButton: true, closeOnClick: true }).setHTML(popupContent))
        .addTo(map);

      marker.on('click', () => {
        if (onMarkerClick) onMarkerClick(mini);
        const el = document.getElementById(`mini-popup-${mini.id}`);
        if (el) el.onclick = () => router.push(`/mini-tournament-detail/${mini.original_id || mini.id}`);
      });

      goongMarkers[mini.id] = marker;
    });

    if (shouldUpdate) {
      renderClusterMarkers(map, 'mini-tournaments');
    }
  };

  const addTournamentMarkers = (
    tournamentsData,
    router,
    onMarkerClick,
    shouldUpdate = false,
    defaultImage = ''
  ) => {
    const normalized = tournamentsData.map((t) => ({
      id: t.id,
      lat: parseFloat(t.lat ?? t.competition_location?.latitude),
      lng: parseFloat(t.lng ?? t.competition_location?.longitude),
      _original: t,
    })).filter((t) => !isNaN(t.lat) && !isNaN(t.lng));

    const toAdd = shouldUpdate ? updateMarkers(normalized, 'tournaments') : normalized;

    toAdd.forEach((t) => {
      const tournament = t._original;
      const locationName = tournament.competition_location?.name || tournament.competition_location?.address || '';
      const dateText = formatDateText(tournament.start_date || tournament.starts_at);
      const description = tournament.description || tournament.rules || '';

      const popupContent = `
        <div id="tournament-popup-${tournament.id}" style="overflow:hidden;background:white;cursor:pointer;font-family:system-ui,sans-serif;min-width:280px;max-width:320px;">
          <div style="display:flex;align-items:flex-start;padding:12px;gap:12px;">
            <div style="width:112px;height:112px;flex-shrink:0;background:#f3f4f6;border-radius:6px;overflow:hidden;border:1px solid #f3f4f6;">
              <img src="${tournament.poster || defaultImage}" onerror="this.onerror=null;this.src='${defaultImage}'" style="width:100%;height:100%;object-fit:cover;" />
            </div>
            <div style="flex:1;min-width:0;">
              <h4 style="font-weight:700;color:#111827;font-size:14px;line-height:1.25;margin:0 0 4px 0;white-space:normal;">${escapeHtml(tournament.name)}</h4>
              ${locationName ? `<div style="font-size:12px;color:#4b5563;">${mapPinIconSmall} ${escapeHtml(locationName)}</div>` : ''}
              <div style="font-size:12px;color:#4b5563;">${calendarIcon} ${dateText}</div>
              ${description ? `<p style="font-size:12px;color:#6b7280;font-weight:500;margin:4px 0 0 0;white-space:normal;">${escapeHtml(description)}</p>` : ''}
            </div>
          </div>
        </div>
      `;

      const marker = new goongjs.Marker()
        .setLngLat([t.lng, t.lat])
        .setPopup(new goongjs.Popup({ maxWidth: 350, closeButton: true, closeOnClick: true }).setHTML(popupContent))
        .addTo(map);

      marker.on('click', () => {
        if (onMarkerClick) onMarkerClick(tournament);
        const el = document.getElementById(`tournament-popup-${tournament.id}`);
        if (el) el.onclick = () => router.push(`/tournament-detail/${tournament.original_id || tournament.id}`);
      });

      goongMarkers[tournament.id] = marker;
    });

    if (shouldUpdate) {
      renderClusterMarkers(map, 'tournaments');
    }
  };

  const addMatchMarkers = addMiniTournamentMarkers;

  const addClubMarkers = (
    clubsData,
    router,
    onMarkerClick,
    shouldUpdate = false,
    defaultImage = ''
  ) => {
    const normalized = clubsData.map((c) => ({
      id: c.id,
      lat: parseFloat(c.latitude),
      lng: parseFloat(c.longitude),
      _original: c,
    })).filter((c) => !isNaN(c.lat) && !isNaN(c.lng));

    const toAdd = shouldUpdate ? updateMarkers(normalized, 'clubs') : normalized;

    toAdd.forEach((c) => {
      const club = c._original;

      const popupContent = `
        <div id="club-popup-${club.id}" style="min-width: 250px; max-width: 300px; font-family: system-ui; cursor: pointer;">
          <div style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #e0e0e0; border-radius: 6px 6px 0 0;">
            <img src="${club.logo_url || defaultImage}" alt="Club Logo" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 2px solid #4392E0; flex-shrink: 0; margin-right: 12px;" onerror="this.onerror=null;this.src='${defaultImage}'" />
            <div style="flex: 1; min-width: 0;">
              <h3 style="margin: 0; font-weight: 700; font-size: 16px; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(club.name)}</h3>
              ${club.members_count !== undefined ? `<p style="margin: 3px 0 0 0; font-size: 13px; color: #6b7280;">${club.members_count} thanh vien</p>` : ''}
            </div>
          </div>
          <div style="padding: 15px; background: white;">
            ${club.address ? `
              <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 14px; color: #4b5563; margin-bottom: 8px;">
                <span style="color: #4392E0; flex-shrink: 0;">${mapPinIcon}</span>
                <p style="margin: 0; line-height: 1.4; color: #374151;">${escapeHtml(club.address)}</p>
              </div>
            ` : ''}
          </div>
        </div>
      `;

      const marker = new goongjs.Marker()
        .setLngLat([c.lng, c.lat])
        .setPopup(new goongjs.Popup({ maxWidth: 350, closeButton: true, closeOnClick: true }).setHTML(popupContent))
        .addTo(map);

      marker.on('click', () => {
        if (onMarkerClick) onMarkerClick(club);
        const el = document.getElementById(`club-popup-${club.id}`);
        if (el) el.onclick = () => router.push(`/club/${club.id}`);
      });

      goongMarkers[club.id] = marker;
    });

    if (shouldUpdate) {
      renderClusterMarkers(map, 'clubs');
    }
  };

  // Expose markers ref for external access
  const markers = goongMarkers;

  return {
    initMap,
    clearAllMarkers,
    updateMarkers,
    focusItem: focusItemLegacy,
    addCourtMarkers,
    addUserMarkers,
    addMiniTournamentMarkers,
    addTournamentMarkers,
    addMatchMarkers,
    addClubMarkers,
    markers,
    // Also export named focusItem for new callers
    focusItem,
  };
}
