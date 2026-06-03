<?php

namespace App\Services;

use App\Enums\SubTabFilter;

class SearchFilterConfig
{
    public const TAB_MATCH      = 'mini-tournament';
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
     * Sub-tab chips available per tab.
     * Null = tab doesn't support sub-tab filter.
     */
    public const SUB_TAB_PER_TAB = [
        self::TAB_MATCH      => [SubTabFilter::ALL, SubTabFilter::MINE, SubTabFilter::TODAY, SubTabFilter::THIS_WEEK, SubTabFilter::THIS_MONTH],
        self::TAB_TOURNAMENT => [SubTabFilter::ALL, SubTabFilter::MINE, SubTabFilter::TODAY, SubTabFilter::THIS_WEEK, SubTabFilter::THIS_MONTH],
        self::TAB_CLUB       => [SubTabFilter::ALL, SubTabFilter::MINE, SubTabFilter::FRIENDS],
        self::TAB_USER        => [SubTabFilter::ALL, SubTabFilter::FRIENDS, SubTabFilter::SAME_CLUB],
        self::TAB_COURT       => [SubTabFilter::ALL],
    ];

    /**
     * Filter definitions per tab.
     * Each filter: ['key' => string, 'label' => string, 'type' => string, 'options' => array|null]
     * type: range | multi_select | single_select | boolean
     */
    public const FILTERS_PER_TAB = [
        self::TAB_MATCH => [
            ['key' => 'distance',     'label' => 'Khoảng cách',  'type' => 'range',        'options' => null],
            ['key' => 'rating',        'label' => 'Điểm rating',   'type' => 'range', 'options' => null],
            ['key' => 'min_level',     'label' => 'Từ rating',     'type' => 'range', 'options' => null],
            ['key' => 'max_level',     'label' => 'Đến rating',    'type' => 'range', 'options' => null],
            ['key' => 'time_of_day',  'label' => 'Thời gian',      'type' => 'multi_select', 'options' => ['morning' => 'Sáng', 'afternoon' => 'Chiều', 'evening' => 'Tối']],
            ['key' => 'slot_status',  'label' => 'Tình trạng',   'type' => 'multi_select', 'options' => ['con_trong' => 'Còn trống', 'da_day' => 'Đã đầy']],
            ['key' => 'fee',          'label' => 'Phí',             'type' => 'multi_select', 'options' => ['free' => 'Miễn phí', 'paid' => 'Có phí']],
            ['key' => 'type',             'label' => 'Loại',             'type' => 'multi_select', 'options' => ['single' => 'Đánh đơn', 'double' => 'Đánh đôi']],
            ['key' => 'play_mode',        'label' => 'Loại kèo',         'type' => 'multi_select', 'options' => ['casual' => 'Kèo thường', 'competition' => 'Kèo thi đấu', 'practice' => 'Kèo tập luyện']],
            ['key' => 'gender',           'label' => 'Giới tính',         'type' => 'multi_select', 'options' => ['male' => 'Nam', 'female' => 'Nữ', 'mixed' => 'Nam nữ']],
            ['key' => 'club_type',        'label' => 'CLB',               'type' => 'multi_select', 'options' => ['thuong' => 'Kèo thường', 'clb' => 'Kèo CLB']],
        ],
        self::TAB_TOURNAMENT => [
            ['key' => 'distance',     'label' => 'Khoảng cách',  'type' => 'range',        'options' => null],
            ['key' => 'rating',        'label' => 'Điểm rating',   'type' => 'range', 'options' => null],
            ['key' => 'min_level',     'label' => 'Từ rating',     'type' => 'range', 'options' => null],
            ['key' => 'max_level',     'label' => 'Đến rating',    'type' => 'range', 'options' => null],
            ['key' => 'time_of_day',  'label' => 'Thời gian',      'type' => 'multi_select', 'options' => ['morning' => 'Sáng', 'afternoon' => 'Chiều', 'evening' => 'Tối']],
            ['key' => 'slot_status',  'label' => 'Tình trạng',    'type' => 'multi_select', 'options' => ['con_trong' => 'Còn trống', 'da_day' => 'Đã đầy']],
            ['key' => 'fee',             'label' => 'Phí',              'type' => 'multi_select', 'options' => ['free' => 'Miễn phí', 'paid' => 'Có phí']],
            ['key' => 'tournament_type', 'label' => 'Loại giải',        'type' => 'multi_select', 'options' => ['all' => 'Mọi lứa tuổi', 'youth' => 'Dưới 18', 'adult' => '18-55', 'senior' => 'Trên 55']],
            ['key' => 'gender',           'label' => 'Giới tính',         'type' => 'multi_select', 'options' => ['male' => 'Nam', 'female' => 'Nữ', 'mixed' => 'Nam nữ']],
            ['key' => 'club_type',        'label' => 'CLB',              'type' => 'multi_select', 'options' => ['thuong' => 'Giải thường', 'clb' => 'Giải CLB']],
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

    public static function getSubTabOptions(string $tab): array
    {
        $options = self::SUB_TAB_PER_TAB[$tab] ?? [];
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
            'sub_tabs' => self::getSubTabOptions($tab),
        ];
    }
}
