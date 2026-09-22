# Map Provider (Goong) - Setup Guide

## Tong quan

He thong map da duoc migrate sang **Goong Maps** (`goong.io`). Tat ca cac provider (Google, OSM, ...) deu duoc abstraction hoa, de dang them provider moi trong tuong lai.

## Cau truc

### Backend (Laravel)

```
app/Services/Geocoding/
  Contracts/
    GeocodingProvider.php       # Interface
  GoongProvider.php             # Driver Goong (chinh)
  OsmProvider.php               # Driver OSM Nominatim (fallback)
  GeocodingManager.php          # Chon driver theo config
  GoongKeyResolver.php          # DB > env, cache 60s

app/Services/GeocodingService.php   # Facade backward-compat

app/Http/Controllers/
  Admin/SettingsController.php      # Them endpoint map-provider
  MapProviderConfigController.php   # Public endpoint tra map key cho frontend
```

### Frontend (Vue 3)

```
resources/js/composables/useMap.js      # Goong JS thay the Leaflet
resources/js/service/mapProvider.js    # Service abstraction (search/detail/admin)
```

## Key configuration

Key Goong duoc luu trong bang `system_settings` (group = `map_provider`):
- `goong.api_key` - REST API key (server-side only)
- `goong.map_key` - Map tiles key (public, gui cho trinh duyet)

### Do uu tien

**DB > env.** Khi `system_settings` co gia tri thi lay tu DB. Neu khong co thi fallback `env('GOONG_API_KEY')` / `env('GOONG_MAP_KEY')`.

Cache 60 giay qua Laravel Cache. Khi admin save qua endpoint `/api/admin/settings`, cache tu clear -> key moi co hieu luc ngay lap tuc.

### Cach them key (admin)

1. Vao admin UI, mo trang Settings.
2. Dien `goong_api_key` va `goong_map_key`.
3. Luu. Backend tu clear cache va audit log.

Hoac goi API:
```bash
PUT /api/admin/settings
{
  "goong_api_key": "your-rest-key",
  "goong_map_key": "your-tiles-key"
}
```

### Lay key (read-only, admin)

```bash
GET /api/admin/settings/map-provider
# Response: { "goong_api_key": "abcd****wxyz", "goong_map_key": "1234****5678" }
```

Key tra ve da duoc **mask** de an toan.

### Public endpoint cho frontend

```bash
GET /api/map/public-config
# Response: { "map_key": "...", "style_url": "https://tiles.goong.io/assets/goong_map_web.json" }
```

Khong can auth. Endpoint nay chi tra `map_key` (public) + `style_url`, khong bao gio tra `api_key`.

## Switch driver

Driver mac dinh: `goong`.

Doi sang OSM qua env:
```
GEOCODER_DRIVER=osm
```

Sau do restart server. He thong se su dung `OsmProvider` thay the.

## Tao provider moi

1. Implement `App\Services\Geocoding\Contracts\GeocodingProvider`:
```php
class MyProvider implements GeocodingProvider
{
    public function search(string $query, array $options = []): array { /* ... */ }
    public function placeDetail(string $placeId): ?array { /* ... */ }
}
```

2. Dang ky trong `GeocodingManager::$providers`:
```php
protected array $providers = [
    'goong' => GoongProvider::class,
    'osm'   => OsmProvider::class,
    'mine'  => MyProvider::class,
];
```

3. Cap nhat config va env.

## Frontend env wiring

Frontend **KHONG** dung `VITE_GOONG_MAP_KEY` nua. Map key duoc lay qua API runtime (`/api/map/public-config`).

Neu can style URL khac (vi du dark mode, navigation), sua o `MapProviderConfigController::config()`.

## Luu y bao mat

1. **REST API key** (`goong.api_key`) tuyet doi khong gui xuong frontend. Dung backend de goi Goong REST API.
2. **Map tiles key** (`goong.map_key`) la public, nhung nen **restrict referrer** tren dashboard Goong (vi du: `*.picki.vn/*`) de tranh bi su dung trai phep.
3. Tuyet doi khong commit key vao git. Su dung admin UI hoac env.
4. Khi rotate key, cap nhat qua admin UI de audit log va tranh downtime.

## Test

```bash
php artisan test --filter=GeocodingServiceTest
```

## Troubleshooting

### Map hien "Ban do chua duoc cau hinh"
- Admin chua dien key, hoac ca DB va env deu rong.
- Kiem tra `GET /api/map/public-config` tra ve gi.

### Search place tra ve rong
- Kiem tra `goong.api_key` co dung khong (test bang `curl` voi Goong REST truc tiep).
- Kiem tra rate limit (Goong free: 5 req/s, 30k req/month).

### Marker khong cluster
- Kiem tra zoom level (cluster chi hien thi khi zoom < 16).
- Kiem tra console browser xem co loi JavaScript khong.

### Restore tu env fallback
Neu DB loi va admin muon revert, dat env:
```
GOONG_API_KEY=fallback-key
GOONG_MAP_KEY=fallback-map-key
```
Backend tu dong su dung env khi DB NULL.

## Cleanup da lam

- Xoa package `leaflet`, `leaflet.markercluster`.
- Xoa code Google Places (`GeocodingService::googleSearch`, `getGooglePlaceDetail`).
- Xoa env `GOOGLE_MAPS_API_KEY`.
- Xoa env `VITE_GOONG_MAP_KEY` (neu co).

## TODO tuong lai

- Directions / Distance Matrix: chua implement, can them vao interface khi can.
- Static Map: chua implement.
- Multi-language autocomplete: hien chi tieng Viet/Anh (Goong mac dinh).
