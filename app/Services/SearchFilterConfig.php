<?php

namespace App\Services;

use App\Enums\TimelineFilter;

class SearchFilterConfig
{
    public const TAB_MATCH      = 'match';
    public const TAB_TOURNAMENT = 'tournament';
    public const TAB_CLUB       = 'club';
    public const TAB_USER       = 'user';
    public const TAB_COURT      = 'court';

    public const ALL_TABS = [
        self::TAB_MATCH,
        self::TAB_TOURNAMENT,
        self::TAB_CLUB,
        self::TAB_USER,
        self::TAB_COURT,
    ];

    public const TAB_LABELS = [
        self::TAB_MATCH      => 'Kèo đấu',
        self::TAB_TOURNAMENT => 'Giải đấu',
        self::TAB_CLUB       => 'Câu lạc bộ',
        self::TAB_USER       => 'Người chơi',
        self::TAB_COURT      => 'Sân bãi',
    ];

    /**
     * Timeline chips available per tab.
     * Null = tab doesn't support timeline filter.
     */
    public const TIMELINE_PER_TAB = [
        self::TAB_MATCH      => [TimelineFilter::ALL, TimelineFilter::MINE, TimelineFilter::TODAY, TimelineFilter::THIS_WEEK, TimelineFilter::THIS_MONTH],
        self::TAB_TOURNAMENT => [TimelineFilter::ALL, TimelineFilter::MINE, TimelineFilter::TODAY, TimelineFilter::THIS_WEEK, TimelineFilter::THIS_MONTH],
        self::TAB_CLUB       => [TimelineFilter::ALL, TimelineFilter::MINE],
        self::TAB_USER        => [TimelineFilter::ALL, TimelineFilter::MINE],
        self::TAB_COURT       => [TimelineFilter::ALL],
    ];

    /**
     * Filter definitions per tab.
     * Each filter: ['key' => string, 'label' => string, 'type' => string, 'options' => array|null]
     * type: range | multi_select | single_select | boolean
     */
    public const FILTERS_PER_TAB = [
        self::TAB_MATCH => [
            ['key' => 'distance',     'label' => 'Khoảng cách',  'type' => 'range',        'options' => null],
            ['key' => 'rating',       'label' => 'Điểm rating',   'type' => 'range',        'options' => null],
            ['key' => 'time_of_day',  'label' => 'Thời gian',      'type' => 'multi_select', 'options' => ['morning' => 'Sáng', 'afternoon' => 'Chiều', 'evening' => 'Tối']],
            ['key' => 'slot_status',  'label' => 'Tình trạng',   'type' => 'multi_select', 'options' => ['con_trong' => 'Còn trống', 'da_day' => 'Đã đầy']],
            ['key' => 'fee',          'label' => 'Phí',             'type' => 'multi_select', 'options' => ['free' => 'Miễn phí', 'paid' => 'Có phí']],
            ['key' => 'type',         'label' => 'Loại',           'type' => 'multi_select', 'options' => ['single' => 'Đánh đơn', 'double' => 'Đánh đôi']],
        ],
        self::TAB_TOURNAMENT => [
            ['key' => 'distance',     'label' => 'Khoảng cách',  'type' => 'range',        'options' => null],
            ['key' => 'rating',       'label' => 'Điểm rating',   'type' => 'range',        'options' => null],
            ['key' => 'time_of_day',  'label' => 'Thời gian',      'type' => 'multi_select', 'options' => ['morning' => 'Sáng', 'afternoon' => 'Chiều', 'evening' => 'Tối']],
            ['key' => 'slot_status',  'label' => 'Tình trạng',    'type' => 'multi_select', 'options' => ['con_trong' => 'Còn trống', 'da_day' => 'Đã đầy']],
            ['key' => 'fee',          'label' => 'Phí',             'type' => 'multi_select', 'options' => ['free' => 'Miễn phí', 'paid' => 'Có phí']],
        ],
        self::TAB_CLUB => [
            ['key' => 'distance',    'label' => 'Khoảng cách', 'type' => 'range',   'options' => null],
            ['key' => 'joined_only', 'label' => 'Đã tham gia',  'type' => 'boolean', 'options' => null],
        ],
        self::TAB_USER => [
            ['key' => 'distance',         'label' => 'Khoảng cách',    'type' => 'range',        'options' => null],
            ['key' => 'rating',           'label' => 'Điểm rating',     'type' => 'range',        'options' => null],
            ['key' => 'gender',           'label' => 'Giới tính',        'type' => 'multi_select', 'options' => ['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác']],
            ['key' => 'same_club_id',     'label' => 'Cùng câu lạc bộ', 'type' => 'boolean',      'options' => null],
        ],
        self::TAB_COURT => [
            ['key' => 'distance',  'label' => 'Khoảng cách', 'type' => 'range', 'options' => null],
        ],
    ];

    public static function getTabs(): array
    {
        return self::ALL_TABS;
    }

    public static function getTabLabel(string $tab): string
    {
        return self::TAB_LABELS[$tab] ?? $tab;
    }

    public static function getFilters(string $tab): array
    {
        return self::FILTERS_PER_TAB[$tab] ?? [];
    }

    public static function getTimelineOptions(string $tab): array
    {
        $options = self::TIMELINE_PER_TAB[$tab] ?? [];
        return array_map(fn($enum) => [
            'value' => $enum->value,
            'label' => $enum->label(),
            'badge' => $enum->badge(),
        ], $options);
    }

    public static function availableTabs(): array
    {
        return collect(self::ALL_TABS)->map(fn($tab) => [
            'key'   => $tab,
            'label' => self::TAB_LABELS[$tab],
        ])->values()->toArray();
    }

    public static function getConfig(string $tab): array
    {
        return [
            'tab'     => $tab,
            'label'   => self::TAB_LABELS[$tab] ?? $tab,
            'filters' => self::getFilters($tab),
            'timeline' => self::getTimelineOptions($tab),
        ];
    }
}
