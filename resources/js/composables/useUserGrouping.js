/**
 * Bucket user suggest response (3 groups: played_tournament, same_club, nearby)
 * into per-tab views. Defensive against unknown/null categories and empty lists.
 *
 * Two modes:
 *  - groupByCategory(items)   — flat list with `category` on each item → buckets
 *  - groupByGroups(groups)    — pre-grouped {played_tournament, same_club, nearby}
 *                               from BE (preferred; preserves ordering)
 *
 * Backend contract (see UserSuggestService):
 *   played_tournament → "Hay chơi cùng kèo giải"
 *   same_club         → "Cùng CLB"
 *   nearby            → "Ở gần bạn"
 */

const KNOWN_CATEGORIES = ['played_tournament', 'same_club', 'nearby']

const SECTION_PRIORITY = [
    { key: 'played_tournament', title: 'Hay chơi cùng kèo giải' },
    { key: 'same_club',         title: 'Cùng CLB' },
    { key: 'nearby',            title: 'Ở gần bạn' },
]

let warnedUnknown = false

const bucketKey = (item) => {
    const c = item?.category
    if (KNOWN_CATEGORIES.includes(c)) return c
    if (c == null) return 'other'
    if (!warnedUnknown) {
        console.warn('[useUserGrouping] unknown category from suggest:', c)
        warnedUnknown = true
    }
    return 'other'
}

const safeItems = (items) => (Array.isArray(items) ? items : [])

/**
 * Returns { played_tournament, same_club, nearby, other }.
 * Each value is an array (possibly empty).
 */
export const groupByCategory = (items = []) => {
    const buckets = {
        played_tournament: [],
        same_club: [],
        nearby: [],
        other: [],
    }
    for (const item of safeItems(items)) {
        buckets[bucketKey(item)].push(item)
    }
    return buckets
}

/**
 * Build sections directly from the BE pre-grouped response.
 * `groups` is the 3-key object returned by /api/players/search?sub_tab=suggest.
 * Each section preserves BE ordering and category/category_text.
 */
export const sectionsFromGroups = (groups = {}) => {
    const out = []
    for (const { key, title } of SECTION_PRIORITY) {
        const items = safeItems(groups?.[key])
        if (items.length) {
            out.push({ key, title, items })
        }
    }
    // Unknown groups (defensive): still render them at the tail.
    for (const key of Object.keys(groups || {})) {
        if (KNOWN_CATEGORIES.includes(key)) continue
        const items = safeItems(groups[key])
        if (!items.length) continue
        const fallbackTitle = items[0]?.category_text || 'Khác'
        out.push({ key, title: fallbackTitle, items })
    }
    return out
}

/**
 * Sections in priority order, skipping empties. Each section has:
 *   { key, title, items }
 * title falls back to item.category_text when present, else the static label.
 */
export const orderedSections = (items = []) => {
    const grouped = groupByCategory(items)
    const out = []
    for (const { key, title } of SECTION_PRIORITY) {
        if (grouped[key].length) {
            out.push({ key, title, items: grouped[key] })
        }
    }
    if (grouped.other.length) {
        const fallbackTitle = grouped.other[0]?.category_text || 'Khác'
        out.push({ key: 'other', title: fallbackTitle, items: grouped.other })
    }
    return out
}
