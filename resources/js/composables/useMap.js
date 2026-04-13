// src/composables/useMap.js
import { onMounted, onUnmounted } from 'vue';
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "leaflet.markercluster/dist/MarkerCluster.css";
import "leaflet.markercluster/dist/MarkerCluster.Default.css";
import "leaflet.markercluster";
import { toast } from 'vue3-toastify';

// --- CONSTANTS ---
const DEFAULT_CENTER = [21.0285, 105.8542];
const DEFAULT_ZOOM = 10;
const VIETNAM_BOUNDS = L.latLngBounds([8.0, 102.0], [23.5, 109.5]);

// --- UTILITY FUNCTIONS ---
const escapeHtml = (text) => {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
};

const formatDateText = (date) => {
  if (!date) return '';
  const d = new Date(date);
  const days = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
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

const createMarkerIcon = (iconUrl) => {
  return new L.Icon({
    iconUrl,
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });
};

// --- ICONS ---
const redIcon = createMarkerIcon('https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png');
const defaultMarkerIcon = createMarkerIcon('https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png');

// SVG Icons
const clockIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>`;
const phoneIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>`;
const mapPinIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>`;

// Match Icons (for match popup)
const calendarIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>`;
const calendarDaysIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9 6V9m0 0h-3.75m3.75 0h3.75" /></svg>`;
const clockIconSmall = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>`;
const mapPinIconSmall = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>`;
const lockIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>`;
const lockOpenIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>`;
const flagIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path fill-rule="evenodd" d="M3 2.25a.75.75 0 0 1 .75.75v16.5a.75.75 0 0 1-1.5 0V3A.75.75 0 0 1 3 2.25ZM19.5 3a.75.75 0 0 0-1.5 0v7.5a.75.75 0 0 0 1.5 0V3Zm-5.25 0a.75.75 0 0 0-1.5 0v16.5a.75.75 0 0 0 1.5 0V3ZM12 9a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-1.5 0v-7.5A.75.75 0 0 1 12 9Zm-3.75-6a.75.75 0 0 0-1.5 0v16.5a.75.75 0 0 0 1.5 0V3Z" clip-rule="evenodd" /></svg>`;
const starIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" /></svg>`;
const userGroupIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>`;

// --- COMPOSABLE ---
export function useMap() {
  let map = null;
  let markers = Object.create(null);
  let markerClusterGroup = null;
  let currentLocationMarker = null;
  let resizeHandler = null;
  let resetControl = null;
  let locationControl = null;
  let moveEndHandler = null;
  let loadDataCallback = null;
  let isUserInteraction = false;
  let isFocusing = false;

  // --- MARKER MANAGEMENT ---
  const clearAllMarkers = () => {
    if (map && markerClusterGroup) {
      markerClusterGroup.clearLayers();
      markers = {};
    }
  };

  const updateMarkers = (newMarkersArray) => {
    if (!map || !markerClusterGroup) return;

    const existingIds = new Set(Object.keys(markers));
    const newIds = new Set(newMarkersArray.map(m => m.id));

    // Xóa markers không còn trong danh sách mới
    existingIds.forEach(id => {
      if (!newIds.has(id)) {
        const marker = markers[id];
        if (marker) {
          marker.off();
          markerClusterGroup.removeLayer(marker);
          delete markers[id];
        }
      }
    });

    // Chỉ trả về markers mới (chưa có trên map)
    return newMarkersArray.filter(item => !existingIds.has(item.id));
  };

  const setupMarkerClusterGroup = () => {
    markerClusterGroup = L.markerClusterGroup({
      iconCreateFunction: function (cluster) {
        const childCount = cluster.getChildCount();
        let bgColor;

        if (childCount < 10) {
          bgColor = '#4392E0';
        } else if (childCount < 99) {
          bgColor = '#F59E0B';
        } else {
          bgColor = '#D72D36';
        }

        return new L.DivIcon({
          html: `
            <div style="
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
            ">
              ${childCount}
            </div>
          `,
          className: 'custom-cluster-icon',
          iconSize: L.point(50, 50)
        });
      },
      spiderfyOnMaxZoom: true,
      showCoverageOnHover: true,
      zoomToBoundsOnClick: true,
      maxClusterRadius: 80,
      disableClusteringAtZoom: 16,
      chunkedLoading: true,
      chunkDelay: 30,
      chunkInterval: 200,
      removeOutsideVisibleBounds: true
    });
    map.addLayer(markerClusterGroup);
  };

  // --- MAP CONTROLS ---
  const resetMap = () => {
    if (currentLocationMarker) {
      map.removeLayer(currentLocationMarker);
      currentLocationMarker = null;
    }
    if (map) {
      map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    }
  };

  const getCurrentLocation = () => {
    if (!navigator.geolocation) {
      toast.error('Trình duyệt không hỗ trợ định vị địa lý');
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        const latLng = [position.coords.latitude, position.coords.longitude];
        map.setView(latLng, 16);

        if (currentLocationMarker) {
          map.removeLayer(currentLocationMarker);
        }
        currentLocationMarker = L.marker(latLng, { icon: redIcon })
          .addTo(map)
          .bindPopup("Vị trí hiện tại của bạn")
          .openPopup();
      },
      () => {
        toast.error('Không thể lấy vị trí hiện tại');
      },
      {
        timeout: 10000,
        maximumAge: 60000,
        enableHighAccuracy: true
      }
    );
  };

  const setupCustomControls = () => {
    if (!map) return;

    const isMobile = window.innerWidth <= 1024;
    const position = isMobile ? 'topright' : 'bottomright';

    // Reset Control
    resetControl = L.control({ position });
    resetControl.onAdd = function () {
      const btn = L.DomUtil.create('button', 'leaflet-bar leaflet-control leaflet-control-custom');
      btn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; margin: auto;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
      `;
      btn.title = 'Đặt lại bản đồ';
      btn.style.cssText = 'background-color: white; width: 48px; height: 48px; cursor: pointer; font-size: 18px; line-height: 30px; text-align: center; margin: 10px;';
      L.DomEvent.disableClickPropagation(btn);
      btn.onclick = resetMap;
      return btn;
    };
    resetControl.addTo(map);

    // Location Control
    locationControl = L.control({ position });
    locationControl.onAdd = function () {
      const btn = L.DomUtil.create('button', 'leaflet-bar leaflet-control leaflet-control-custom');
      btn.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px; margin: auto;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
        </svg>
      `;
      btn.title = 'Vị trí hiện tại của tôi';
      btn.style.cssText = 'background-color: white; width: 48px; height: 48px; cursor: pointer; font-size: 18px; line-height: 30px; text-align: center; margin: 10px; margin-bottom: 0;';
      L.DomEvent.disableClickPropagation(btn);
      btn.onclick = getCurrentLocation;
      return btn;
    };
    locationControl.addTo(map);

    // Resize handler
    resizeHandler = () => {
      const newPosition = window.innerWidth <= 1024 ? 'topright' : 'bottomright';
      if (resetControl) resetControl.setPosition(newPosition);
      if (locationControl) locationControl.setPosition(newPosition);
    };
    window.addEventListener('resize', resizeHandler);
  };

  const initMap = (initialLoadCallback, onMapMoveCallback) => {
    onMounted(() => {
      // Base Layers
      const defaults = L.tileLayer('https://api.maptiler.com/maps/outdoor-v2/{z}/{x}/{y}.png?key=8cBUtiYp86dx1jzzYd5J');
      const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
      const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}');
      const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png');

      const baseLayers = {
        "Bản đồ mặc định": defaults,
        "Bản đồ giao thông": streets,
        "Bản đồ vệ tinh": satellite,
        "Bản đồ địa hình": topo
      };

      // Initialize Map
      map = L.map('map', {
        center: DEFAULT_CENTER,
        zoom: DEFAULT_ZOOM,
        layers: [defaults],
        maxBounds: VIETNAM_BOUNDS,
        maxBoundsViscosity: 1.0,
        minZoom: 9,
        maxZoom: 18,
        attributionControl: false
      });

      L.control.layers(baseLayers).addTo(map);
      setupMarkerClusterGroup();
      setupCustomControls();

      loadDataCallback = onMapMoveCallback;

      // Load data lần đầu với bounds hiện tại
      if (initialLoadCallback) {
        setTimeout(() => {
          const bounds = map.getBounds();
          initialLoadCallback(bounds);
        }, 100);
      }

      // Setup moveend event với debounce
      let moveEndTimeout;
      moveEndHandler = () => {
        clearTimeout(moveEndTimeout);
        moveEndTimeout = setTimeout(() => {
          // Chỉ load data khi user thực sự pan/zoom map, không phải khi focus item
          if (loadDataCallback && map && isUserInteraction && !isFocusing) {
            const bounds = map.getBounds();
            loadDataCallback(bounds);
          }
          // Reset flag sau khi xử lý xong
          isUserInteraction = false;
          isFocusing = false;
        }, 800);
      };

      // Track khi user bắt đầu tương tác với map
      map.on('dragstart', () => {
        isUserInteraction = true;
        isFocusing = false;
      });

      map.on('zoomstart', () => {
        // Chỉ set isUserInteraction = true nếu không đang focus
        if (!isFocusing) {
          isUserInteraction = true;
        }
      });

      map.on('moveend', moveEndHandler);
    });

    // Cleanup
    onUnmounted(() => {
      if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler);
      }
      if (map) {
        if (moveEndHandler) {
          map.off('moveend', moveEndHandler);
          map.off('dragstart');
          map.off('zoomstart');
        }
        map.remove();
        map = null;
      }
    });
  };

  // --- FOCUS ITEM ---
  const focusItem = (itemId) => {
    if (!map) return;

    const marker = markers[itemId];
    if (marker) {
      // Set flag trước khi setView để moveend biết đây là focus, không phải user pan
      isFocusing = true;
      isUserInteraction = false;

      map.setView(marker.getLatLng(), 17, {
        animate: true,
        duration: 0.8,
        easeLinearity: 0.5
      });

      setTimeout(() => {
        if (markerClusterGroup && markerClusterGroup.hasLayer(marker)) {
          markerClusterGroup.zoomToShowLayer(marker, () => {
            marker.openPopup();
          });
        } else {
          marker.openPopup();
        }
      }, 900);
    }
  };

  // --- ADD MARKERS ---
  const addCourtMarkers = (courtsData, toHourMinute, defaultImage, onMarkerClick, shouldUpdate = false) => {
    const dataToAdd = shouldUpdate ? updateMarkers(courtsData) : courtsData;
    const batchMarkers = [];

    dataToAdd.forEach(c => {
      if (!c.latitude || !c.longitude || isNaN(c.latitude) || isNaN(c.longitude)) return;

      const popupContent = `
        <div style="min-width: 220px; font-family: system-ui; margin-top: 20px;">
          <img src="${c.image || defaultImage}" alt="Court Image" style="width: 100%; height: 120px; object-fit: cover; border-radius: 4px; margin-bottom: 10px;" onerror="this.onerror=null;this.src='${defaultImage}'" />
          <h3 style="margin: 0 0 10px 0; font-weight: 600; font-size: 16px; color: #1f2937;">${escapeHtml(c.name)}</h3>
          <div style="display: flex; flex-direction: column; gap: 6px;">
            <p style="margin: 0;display:flex; justify-content:start; align-items:center; gap:6px; font-size: 14px; color: #4b5563;">
              <span style="color: #4392E0; font-weight: 500;">${clockIcon}</span>
              Giờ Mở cửa: ${toHourMinute(c.opening_time)} - ${toHourMinute(c.closing_time)}
            </p>
            <p style="margin: 0;display:flex; justify-content:start; align-items:center; gap:6px; font-size: 14px; color: #4b5563;">
              <span style="color: #4392E0; font-weight: 500;">${phoneIcon}</span>
              ${escapeHtml(c.phone)}
            </p>
            <p style="margin: 0;display:flex; justify-content:start; align-items:baseline; gap:6px; font-size: 14px; color: #4b5563; line-height: 1.4;">
              <span style="color: #4392E0; font-weight: 500;">${mapPinIcon}</span>
              ${escapeHtml(c.address)}
            </p>
          </div>
        </div>
      `;

      const m = L.marker([c.latitude, c.longitude], { icon: defaultMarkerIcon })
        .bindPopup(popupContent, { maxWidth: 300 });

      markers[c.id] = m;
      batchMarkers.push(m);

      if (onMarkerClick) {
        m.on('click', () => onMarkerClick(c));
      }
    });

    if (batchMarkers.length) {
      markerClusterGroup.addLayers(batchMarkers);
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
    const dataToAdd = shouldUpdate ? updateMarkers(usersData) : usersData;
    const batchMarkers = [];

    const getGenderIconHtml = (gender) => {
      const style = 'width: 16px; height: 16px; display: inline-block; vertical-align: middle;';
      if (gender == 1 && maleIcon) return maleIcon.replace('<svg', `<svg style="${style}"`);
      if (gender == 2 && femaleIcon) return femaleIcon.replace('<svg', `<svg style="${style}"`);
      return '';
    };

    const getVisibilityBadgeHtml = (visibility) => {
      const styles = {
        open: { bg: '#dcfce7', text: '#15803d' },
        'friend-only': { bg: '#fef9c3', text: '#a16207' },
        private: { bg: '#fee2e2', text: '#b91c1c' }
      };
      const style = styles[visibility] || { bg: '#e5e7eb', text: '#4b5563' };

      return `
        <span style="
          padding: 2px 8px;
          border-radius: 4px;
          font-size: 10px;
          font-weight: 500;
          text-transform: capitalize;
          white-space: nowrap;
          background-color: ${style.bg};
          color: ${style.text};
        ">
          ${escapeHtml(getVisibilityText(visibility))}
        </span>
      `;
    };

    dataToAdd.forEach(user => {
      if (!user.latitude || !user.longitude || isNaN(user.latitude) || isNaN(user.longitude)) return;

      const rating = getUserRating(user);
      const genderIconHtml = getGenderIconHtml(user.gender);
      const visibilityBadgeHtml = getVisibilityBadgeHtml(user.visibility);
      const genderText = user.gender_text || 'Khác';
      const ageGroup = user.age_group ? ' • ' + user.age_group : '';

      const popupContent = `
        <div id="user-popup-${user.id}" data-user-id="${user.id}" style="
          min-width: 250px; max-width: 300px; font-family: system-ui; padding: 0; margin: 0; cursor: pointer;
        ">
          <div class="popup-header" style="
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            border-radius: 6px 6px 0 0;
          ">
            <div style="position: relative; flex-shrink: 0; margin-right: 15px;">
              <img src="${user.avatar_url || defaultImage}" alt="Avatar" style="
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 50%;
                border: 2px solid #ffffff;
                box-shadow: 0 0 0 2px #4392E0;
              " onerror="this.onerror=null;this.src='${defaultImage}'" />
              <div style="
                position: absolute;
                bottom: -3px;
                right: -3px;
                background-color: #f59e0b;
                color: white;
                border-radius: 10px;
                padding: 2px 6px;
                font-size: 11px;
                line-height: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                box-shadow: 0 1px 4px rgba(0,0,0,0.4);
                width: 20px;
                height: 20px;
              ">
                ${rating}
              </div>
            </div>

            <div style="flex: 1; min-width: 0;">
              <div style="display: flex; align-items: center; gap: 6px;">
                <h3 style="margin: 0; font-weight: 700; font-size: 16px; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(user.full_name)}">
                  ${escapeHtml(user.full_name)}
                </h3>
                ${visibilityBadgeHtml}
              </div>
              <p style="margin: 3px 0 0 0; font-size: 13px; color: #6b7280;">Người chơi Pickleball</p>
            </div>
          </div>

          <div class="popup-body" style="padding: 15px; background-color: white;">
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #4b5563;">
                <span style="color: #4392E0; flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 20px; height: 20px;">
                  ${genderIconHtml}
                </span>
                <span>${escapeHtml(genderText)}${escapeHtml(ageGroup)}</span>
              </div>
              <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 14px; color: #4b5563;">
                <span style="color: #4392E0; flex-shrink: 0; width: 20px; height: 20px;">
                  ${mapPinIcon}
                </span>
                <p style="margin: 0; line-height: 1.4; color: #374151;" title="${escapeHtml(user.address)}">
                  ${escapeHtml(user.address)}
                </p>
              </div>
            </div>
          </div>
        </div>
      `;

      const m = L.marker([user.latitude, user.longitude], { icon: defaultMarkerIcon })
        .bindPopup(popupContent, { maxWidth: 350 });

      markers[user.id] = m;
      batchMarkers.push(m);

      if (onMarkerClick) {
        m.on('click', () => onMarkerClick(user));
      }

      m.on('popupopen', () => {
        const el = document.getElementById(`user-popup-${user.id}`);
        if (el) {
          el.onclick = () => router.push(`/profile/${user.id}`);
        }
      });
    });

    if (batchMarkers.length) {
      markerClusterGroup.addLayers(batchMarkers);
    }
  };

  const addMatchMarkers = (matchesData, router, onMarkerClick, shouldUpdate = false, defaultImage = '') => {
    const dataToAdd = shouldUpdate ? updateMarkers(matchesData) : matchesData;
    const batchMarkers = [];

    dataToAdd.forEach(match => {
      // Handle both direct lat/lng and nested in competition_location
      const lat = match.competition_location?.latitude || match.latitude;
      const lng = match.competition_location?.longitude || match.longitude;

      if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

      const locationName = match.competition_location?.name || match.competition_location?.address || '';
      const matchImage = match.poster || defaultImage;

      let popupContent = '';

      // Tournament Layout
      if (match.type === 'tournament') {
        const dateText = formatDateText(match.start_date || match.starts_at);
        const description = match.description || match.rules || '';

        popupContent = `
          <div id="match-popup-${match.id}" style="
            overflow: hidden;
            background: white;
            cursor: pointer;
            font-family: system-ui, -apple-system, sans-serif;
            min-width: 280px;
            max-width: 320px;
          ">
            <div style="display: flex; align-items: flex-start; padding: 12px; gap: 12px;">
              <div style="
                width: 112px;
                height: 112px;
                flex-shrink: 0;
                background: #f3f4f6;
                border-radius: 6px;
                overflow: hidden;
                border: 1px solid #f3f4f6;
              ">
                <img src="${matchImage}"
                  onerror="this.onerror=null;this.src='${defaultImage}'"
                  style="width: 100%; height: 100%; object-fit: cover;" />
              </div>

              <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: space-between; height: 80px;">
                <div>
                  <h4 style="
                    font-weight: 700;
                    color: #111827;
                    font-size: 14px;
                    line-height: 1.25;
                    margin: 0 0 4px 0;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                  ">${escapeHtml(match.name)}</h4>
                </div>

                <div style="display: flex; flex-direction: column; gap: 4px; padding-top: 8px;">
                  ${locationName ? `
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #4b5563;">
                      <span style="color: #4392e0; display: flex; align-items: center;">${mapPinIconSmall}</span>
                      <span style="
                        font-weight: 500;
                        display: -webkit-box;
                        -webkit-line-clamp: 1;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                      ">${escapeHtml(locationName)}</span>
                    </div>
                  ` : ''}
                  <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #4b5563;">
                    <span style="color: #4392e0; display: flex; align-items: center;">${calendarIcon}</span>
                    <span style="font-weight: 500; text-transform: capitalize;">${escapeHtml(dateText)}</span>
                  </div>

                  ${description ? `
                    <p style="
                      font-size: 12px;
                      color: #6b7280;
                      font-weight: 500;
                      margin: 4px 0 0 0;
                      display: -webkit-box;
                      -webkit-line-clamp: 2;
                      -webkit-box-orient: vertical;
                      overflow: hidden;
                    ">${escapeHtml(description)}</p>
                  ` : ''}
                </div>
              </div>
            </div>
          </div>
        `;
      }
      // Mini Layout
      else {
        const dateText = formatDateText(match.starts_at);
        const timeRange = formatTimeRange(match.starts_at, match.duration_minutes);
        const organizer = match.staff?.organizer?.[0]?.user || match.creator || match.user;
        const organizerName = organizer?.full_name || organizer?.name || '';
        const organizerAvatar = organizer?.avatar_url || organizer?.avatar || defaultImage;
        const participants = match.participants || [];
        const joinedCount = match.joined_count || 0;

        // Build badges HTML
        let badgesHtml = '';
        if (match.is_private !== undefined) {
          badgesHtml += `
            <span style="
              display: inline-flex;
              align-items: center;
              border-radius: 9999px;
              padding: 2px 8px;
              gap: 4px;
              font-size: 10px;
              font-weight: 500;
              color: white;
              background: #1f2937;
              white-space: nowrap;
              margin-right: 8px;
            ">
              ${match.is_private ? lockIcon : lockOpenIcon}
              ${match.is_private ? 'Private' : 'Public'}
            </span>
          `;
        }
        if (match.min_rating !== null || match.max_rating !== null) {
          badgesHtml += `
            <span style="
              display: inline-flex;
              align-items: center;
              gap: 4px;
              border-radius: 9999px;
              background: #1f2937;
              padding: 2px 8px;
              font-size: 10px;
              font-weight: 500;
              color: white;
              white-space: nowrap;
              margin-right: 8px;
            ">
              ${flagIcon}
              ${match.min_rating || ''} - ${match.max_rating || ''}
            </span>
          `;
        }
        if (match.is_dupr) {
          badgesHtml += `
            <span style="
              display: inline-flex;
              align-items: center;
              gap: 4px;
              border-radius: 9999px;
              background: #1f2937;
              padding: 2px 8px;
              font-size: 10px;
              font-weight: 500;
              color: white;
              white-space: nowrap;
              margin-right: 8px;
            ">
              ${starIcon}
              DUPR
            </span>
          `;
        }
        if (match.max_players) {
          badgesHtml += `
            <span style="
              display: inline-flex;
              align-items: center;
              gap: 4px;
              border-radius: 9999px;
              background: #1f2937;
              padding: 2px 8px;
              font-size: 10px;
              font-weight: 500;
              color: white;
              white-space: nowrap;
              margin-right: 8px;
            ">
              ${userGroupIcon}
              Max ${match.max_players}
            </span>
          `;
        }

        // Build participants avatars HTML
        let participantsHtml = '';
        if (participants.length > 0) {
          const displayParticipants = participants.slice(0, 2);
          participantsHtml = `
            <div style="display: flex; overflow: hidden; padding: 4px 0;">
              ${displayParticipants.map((p, idx) => `
                <img src="${p.user?.avatar_url || defaultImage}"
                  onerror="this.onerror=null;this.src='${defaultImage}'"
                  style="
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    border: 2px solid white;
                    object-fit: cover;
                    ${idx > 0 ? 'margin-left: -8px;' : ''}
                  " />
              `).join('')}
              ${participants.length > 3 ? `
                <div style="
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  width: 32px;
                  height: 32px;
                  border-radius: 50%;
                  border: 2px solid white;
                  background: #fef2f2;
                  color: #D72D36;
                  font-size: 12px;
                  font-weight: 700;
                  margin-left: -8px;
                ">
                  +${participants.length - 3}
                </div>
              ` : ''}
            </div>
          `;
        } else if (joinedCount > 0) {
          participantsHtml = `
            <div style="
              display: flex;
              align-items: center;
              justify-content: center;
              width: 32px;
              height: 32px;
              border-radius: 50%;
              background: #fef2f2;
              color: #D72D36;
              font-size: 12px;
              font-weight: 700;
              border: 1px solid #fee2e2;
            ">
              +${joinedCount}
            </div>
          `;
        }

        popupContent = `
          <div id="match-popup-${match.id}" style="
            overflow: hidden;
            background: white;
            cursor: pointer;
            font-family: system-ui, -apple-system, sans-serif;
            min-width: 280px;
            max-width: 320px;
          ">
            <div style="padding-top: 12px;padding-bottom: 12px;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <!-- Left Content -->
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px;">
                  <div>
                    <h3 style="
                      font-weight: 700;
                      color: #111827;
                      font-size: 16px;
                      line-height: 1.375;
                      margin: 0;
                      display: -webkit-box;
                      -webkit-line-clamp: 2;
                      -webkit-box-orient: vertical;
                      overflow: hidden;
                    ">${escapeHtml(match.name)}</h3>
                    ${locationName ? `
                      <div style="display: flex; align-items: center; gap: 4px; margin-top: 4px; font-size: 12px; color: #3b82f6; font-weight: 500;">
                        ${mapPinIconSmall}
                        <span style="
                          display: -webkit-box;
                          -webkit-line-clamp: 1;
                          -webkit-box-orient: vertical;
                          overflow: hidden;
                        ">${escapeHtml(locationName)}</span>
                      </div>
                    ` : ''}
                  </div>

                  <!-- Date & Time -->
                  <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #4b5563;">
                      <span style="color: #3b82f6; display: flex; align-items: center;">${calendarDaysIcon}</span>
                      <span style="font-weight: 500; text-transform: capitalize;">${escapeHtml(dateText)}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #4b5563;">
                      <span style="color: #3b82f6; display: flex; align-items: center;">${clockIconSmall}</span>
                      <span style="font-weight: 500;">${escapeHtml(timeRange)}</span>
                    </div>
                  </div>
                </div>

                <!-- Right: Participants -->
                ${participantsHtml ? `
                  <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 12px;">
                    ${participantsHtml}
                  </div>
                ` : ''}
              </div>

              <!-- Creator & Badges Footer -->
              <div style="display: flex; align-items: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f3f4f6; height: 40px;">
                <div style="display: flex; align-items: center; gap: 8px; padding-right: 8px; height: 100%; overflow: hidden;">
                  ${organizerName ? `
                    <img src="${organizerAvatar}"
                      onerror="this.onerror=null;this.src='${defaultImage}'"
                      style="
                        width: 24px;
                        height: 24px;
                        border-radius: 50%;
                        object-fit: cover;
                        border: 1px solid #e5e7eb;
                      " />
                    <span style="
                      font-size: 12px;
                      color: #374151;
                      font-weight: 500;
                      white-space: nowrap;
                      overflow: hidden;
                      text-overflow: ellipsis;
                    ">${escapeHtml(organizerName)}</span>
                  ` : ''}
                </div>
              </div>
              <div style="overflow: hidden; display: flex; align-items: center; gap: 4px; padding-top:4px">
                ${badgesHtml}
              </div>
            </div>
          </div>
        `;
      }

      const m = L.marker([lat, lng], { icon: defaultMarkerIcon })
        .bindPopup(popupContent, { maxWidth: 350, className: 'match-popup' });

      markers[match.id] = m;
      batchMarkers.push(m);

      if (onMarkerClick) {
        m.on('click', () => onMarkerClick(match));
      }

      m.on('popupopen', () => {
        const el = document.getElementById(`match-popup-${match.id}`);
        if (el) {
            el.onclick = () => {
                if (match.type === 'mini') {
                    router.push(`/mini-tournament-detail/${match.original_id || match.id}`);
                } else if (match.type === 'tournament') {
                    router.push(`/tournament-detail/${match.original_id || match.id}`);
                }
            };
        }
      });
    });
    if (batchMarkers.length) {
      markerClusterGroup.addLayers(batchMarkers);
    }
  };

  return {
    initMap,
    clearAllMarkers,
    updateMarkers,
    focusItem,
    addCourtMarkers,
    addUserMarkers,
    addMatchMarkers,
    markers
  };
}
