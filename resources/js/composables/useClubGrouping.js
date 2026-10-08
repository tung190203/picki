/**
 * Bucket a flat suggest response (each item has `category` + `category_text`)
 * into per-tab views. Defensive against unknown/null categories and empty lists.
 *
 * Backend contract (see ClubSuggestService):
 *   friend_in_club → "CLB có bạn bè của bạn"
 *   following      → "CLB bạn đang theo dõi"
 *   suit_level     → "CLB hợp trình độ của bạn"
 *   nearby         → "CLB gần bạn"
 */

const KNOWN_CATEGORIES = ['friend_in_club', 'following', 'suit_level', 'nearby']

const SECTION_PRIORITY = [
    { key: 'friend_in_club', title: 'CLB có bạn bè của bạn' },
    { key: 'following',      title: 'CLB bạn đang theo dõi' },
    { key: 'suit_level',     title: 'CLB hợp trình độ của bạn' },
    { key: 'nearby',         title: 'CLB gần bạn' },
]

let warnedUnknown = false

const bucketKey = (item) => {
    const c = item?.category
    if (KNOWN_CATEGORIES.includes(c)) return c
    if (c == null) return 'other'
    if (!warnedUnknown) {
        // ponytail: log once to catch backend drift, no test needed for 1-liner
        console.warn('[useClubGrouping] unknown category from suggest:', c)
        warnedUnknown = true
    }
    return 'other'
}

const safeItems = (items) => (Array.isArray(items) ? items : [])

/**
 * Returns { friend_in_club, following, suit_level, nearby, other }.
 * Each value is an array (possibly empty).
 */
export const groupByCategory = (items = []) => {
    const buckets = {
        friend_in_club: [],
        following: [],
        suit_level: [],
        nearby: [],
        other: [],
    }
    for (const item of safeItems(items)) {
        buckets[bucketKey(item)].push(item)
    }
    return buckets
}

/**
 * Filtered list for a single sub-tab.
 *   tabKey: 'suggest' | 'following' | 'suit_level'
 */
export const forTab = (tabKey, items = []) => {
    const list = safeItems(items)
    if (tabKey === 'following') {
        return list.filter((it) => it?.category === 'friend_in_club' || it?.category === 'following')
    }
    if (tabKey === 'suit_level') {
        return list.filter((it) => it?.category === 'suit_level')
    }
    // 'suggest' → all (the caller groups by section)
    return list
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
    // Any unknown category → render as a single trailing section
    if (grouped.other.length) {
        const fallbackTitle = grouped.other[0]?.category_text || 'Khác'
        out.push({ key: 'other', title: fallbackTitle, items: grouped.other })
    }
    return out
}
