<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;

class CategoryTemplate
{
    /**
     * The default category tree, mirroring the user's spreadsheet layout.
     * Groups are top-level; children are the leaf categories used when recording.
     *
     * @return array<int, array{type: string, name: string, color: string, icon: string, children: array<int, string>}>
     */
    public static function tree(): array
    {
        return [
            [
                'type' => 'income',
                'name' => 'Gaji',
                'color' => '#16a34a',
                'icon' => 'briefcase',
                'children' => ['Magang'],
            ],
            [
                'type' => 'income',
                'name' => 'Side Jobs',
                'color' => '#0d9488',
                'icon' => 'laptop',
                'children' => ['ShopeeFood'],
            ],
            [
                'type' => 'income',
                'name' => 'Investasi',
                'color' => '#2563eb',
                'icon' => 'trending-up',
                'children' => ['Dividen'],
            ],
            [
                'type' => 'expense',
                'name' => 'Pokok',
                'color' => '#dc2626',
                'icon' => 'home',
                'children' => ['Kost', 'Makan', 'Internet', 'Subscribe AI', 'Bensin', 'Kopi+Gula'],
            ],
            [
                'type' => 'expense',
                'name' => 'Transportation',
                'color' => '#ea580c',
                'icon' => 'truck',
                'children' => ['Service', 'Ganti Oli', 'E-Money'],
            ],
            [
                'type' => 'expense',
                'name' => 'Shopping Bulanan',
                'color' => '#7c3aed',
                'icon' => 'shopping-bag',
                'children' => [
                    'Deterjen', 'Pewangi', 'Parfum', 'Facial wash', 'Serum (opt)', 'Sunscreen',
                    'Hair Tonic', 'Deodorant', 'Parfum ruangan', 'Body wash', 'Sikat + Pasta',
                    'Shampo', 'Tissue', 'Lain-lain',
                ],
            ],
            [
                'type' => 'expense',
                'name' => 'Shopping Sekunder',
                'color' => '#db2777',
                'icon' => 'gift',
                'children' => ['Self Reward'],
            ],
            [
                'type' => 'expense',
                'name' => 'Debts',
                'color' => '#b91c1c',
                'icon' => 'debt',
                'children' => ['Cicilan Motor', 'Kartu Kredit', 'Pinjaman'],
            ],
        ];
    }

    /**
     * Create the default category tree for a user. Safe to call more than once:
     * existing groups and children are left untouched.
     */
    public static function seedFor(User $user): void
    {
        foreach (self::tree() as $groupIndex => $group) {
            $parent = Category::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'parent_id' => null,
                    'type' => $group['type'],
                    'name' => $group['name'],
                ],
                [
                    'color' => $group['color'],
                    'icon' => $group['icon'],
                    'sort_order' => $groupIndex,
                ]
            );

            foreach ($group['children'] as $childIndex => $childName) {
                Category::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'parent_id' => $parent->id,
                        'name' => $childName,
                    ],
                    [
                        'type' => $group['type'],
                        'color' => $group['color'],
                        'sort_order' => $childIndex,
                    ]
                );
            }
        }
    }
}
