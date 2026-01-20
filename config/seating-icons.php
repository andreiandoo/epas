<?php

/**
 * Seating Map Icon Definitions
 *
 * Each icon has:
 * - key: unique identifier (used in code)
 * - label: display name in dropdown
 * - svg: SVG data in one of two formats:
 *   1. Path data only (viewBox assumed 0 0 24 24): 'M17 16l4-4m0...'
 *   2. Full SVG string: '<svg xmlns="...">...</svg>'
 *
 * To add a new icon:
 * 1. Find or create an SVG icon
 * 2. Either extract just the path 'd' attribute, OR paste the full SVG
 * 3. Add a new entry below with a unique key
 *
 * SVG sources: heroicons.com, lucide.dev, feathericons.com, flaticon.com
 */

return [
    'exit' => [
        'label' => 'Exit',
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" width="512" height="512" x="0" y="0" viewBox="0 0 512.005 512" style="enable-background:new 0 0 512 512" xml:space="preserve" class=""><g><path d="M320 277.336c-11.797 0-21.332 9.559-21.332 21.332v85.336c0 11.754-9.559 21.332-21.336 21.332h-64v-320c0-18.219-11.605-34.496-29.055-40.555l-6.316-2.113h99.371c11.777 0 21.336 9.578 21.336 21.336v64c0 11.773 9.535 21.332 21.332 21.332s21.332-9.559 21.332-21.332v-64c0-35.285-28.715-64-64-64H48c-.812 0-1.492.363-2.281.469-1.028-.086-2.008-.47-3.051-.47C19.137.004 0 19.138 0 42.669v384c0 18.219 11.605 34.496 29.055 40.555L157.44 510.02c4.352 1.343 8.68 1.984 13.227 1.984 23.531 0 42.664-19.137 42.664-42.668v-21.332h64c35.285 0 64-28.715 64-64v-85.336c0-11.773-9.535-21.332-21.332-21.332zm0 0" fill="#000000" opacity="1" data-original="#000000" class=""></path><path d="m505.75 198.254-85.336-85.332a21.33 21.33 0 0 0-23.25-4.633C389.207 111.598 384 119.383 384 128.004v64h-85.332c-11.777 0-21.336 9.555-21.336 21.332 0 11.777 9.559 21.332 21.336 21.332H384v64c0 8.621 5.207 16.406 13.164 19.715a21.335 21.335 0 0 0 23.25-4.63l85.336-85.335c8.34-8.34 8.34-21.824 0-30.164zm0 0" fill="#000000" opacity="1" data-original="#000000" class=""></path></g></svg>',
    ],

    'toilet' => [
        'label' => 'Toilet / WC',
        // Simple toilet icon
        'svg' => 'M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9H15V22H13V16H11V22H9V9H3V7H21V9Z',
    ],

    'stage' => [
        'label' => 'Stage',
        // Curtain/stage icon
        'svg' => 'M3 3H21V5H3V3ZM5 5V19C5 19 5 21 7 21H17C19 21 19 19 19 19V5M7 7C7 7 7 11 9 11C11 11 11 7 11 7M13 7C13 7 13 11 15 11C17 11 17 7 17 7M9 13H15V15H9V13Z',
    ],

    'drinks' => [
        'label' => 'Drinks',
        // Glass/drink icon
        'svg' => 'M7 2L5 12H7L8 22H16L17 12H19L17 2H7ZM9 4H15L16 10H8L9 4Z',
    ],

    'showers' => [
        'label' => 'Showers',
        // Shower head icon
        'svg' => 'M6 6C6 4.34 7.34 3 9 3H11V5H9C8.45 5 8 5.45 8 6V8H10V10H8V13C8 14.1 8.9 15 10 15H12V17H10C7.79 17 6 15.21 6 13V6ZM14 6L13 8L15 10L13 12L14 14L17 11L14 6ZM16 16V18H18V16H16ZM14 18V20H16V18H14ZM18 18V20H20V18H18ZM12 20V22H14V20H12ZM16 20V22H18V20H16ZM20 20V22H22V20H20Z',
    ],

    'camping' => [
        'label' => 'Camping Area',
        // Tent icon
        'svg' => 'M12 2L2 19H7L12 10L17 19H22L12 2ZM12 6L16.5 17H14L12 13L10 17H7.5L12 6Z',
    ],

    'dance_floor' => [
        'label' => 'Dance Floor',
        // Dancing person icon
        'svg' => 'M14 6C14 4.9 13.1 4 12 4C10.9 4 10 4.9 10 6C10 7.1 10.9 8 12 8C13.1 8 14 7.1 14 6ZM6 9L5 16H7.5L8 12L10 14V22H12V13L10.5 11L11 9H6ZM18 9H13L13.5 11L12 13V22H14V14L16 12L16.5 16H19L18 9Z',
    ],

    'food' => [
        'label' => 'Food',
        // Fork and knife icon
        'svg' => 'M11 9H9V2H7V9H5V2H3V9C3 11.12 4.66 12.84 6.75 12.97V22H9.25V12.97C11.34 12.84 13 11.12 13 9V2H11V9ZM16 6V14H18.5V22H21V2C18.24 2 16 4.24 16 7V6Z',
    ],

    'info_point' => [
        'label' => 'Info Point',
        // Information circle icon
        'svg' => 'M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V11H13V17ZM13 9H11V7H13V9Z',
    ],

    'entrance' => [
        'label' => 'Entrance',
        // Arrow entering door icon
        'svg' => 'M11 7L9.6 8.4L12.2 11H2V13H12.2L9.6 15.6L11 17L16 12L11 7ZM20 19H12V21H20C21.1 21 22 20.1 22 19V5C22 3.9 21.1 3 20 3H12V5H20V19Z',
    ],

    'cocktails' => [
        'label' => 'Cocktails / Bar',
        // Cocktail glass icon
        'svg' => 'M7.5 7L5.5 5L6.91 3.59L21 17.68L19.59 19.09L17.18 16.68C16.76 18.4 15.22 19.68 13.38 19.93V22H15V24H9V22H11V19.93C8.72 19.63 7 17.65 7 15.21V14L3 10L5 8L7.5 7ZM12 4C12 3.45 11.55 3 11 3H7V5H9V7L11 9L11.53 8.47L9 5.94V5H11C11.55 5 12 4.55 12 4Z',
    ],

    'atm' => [
        'label' => 'ATM',
        // Credit card / ATM icon
        'svg' => 'M20 4H4C2.89 4 2.01 4.89 2.01 6L2 18C2 19.11 2.89 20 4 20H20C21.11 20 22 19.11 22 18V6C22 4.89 21.11 4 20 4ZM20 18H4V12H20V18ZM20 8H4V6H20V8Z',
    ],

    'live_stage' => [
        'label' => 'Live Stage',
        // Microphone icon
        'svg' => 'M12 14C13.66 14 15 12.66 15 11V5C15 3.34 13.66 2 12 2C10.34 2 9 3.34 9 5V11C9 12.66 10.34 14 12 14ZM17.91 11C17.91 14.75 14.82 17.36 12 17.36C9.18 17.36 6.09 14.75 6.09 11H4C4 15.25 7.14 18.73 11 19.42V22H13V19.42C16.86 18.73 20 15.25 20 11H17.91Z',
    ],

    'tables' => [
        'label' => 'Tables',
        // Table icon
        'svg' => 'M4 6H20V8H4V6ZM2 10H22V12H2V10ZM6 14V20H8V14H6ZM16 14V20H18V14H16Z',
    ],

    'reception' => [
        'label' => 'Reception',
        // Desk/counter icon
        'svg' => 'M14 9V4H16V2H8V4H10V9C7.24 9 5 11.24 5 14V22H7V14C7 12.35 8.35 11 10 11H14C15.65 11 17 12.35 17 14V22H19V14C19 11.24 16.76 9 14 9ZM12 7C11.45 7 11 6.55 11 6C11 5.45 11.45 5 12 5C12.55 5 13 5.45 13 6C13 6.55 12.55 7 12 7Z',
    ],

    'escalator' => [
        'label' => 'Escalator',
        // Escalator icon
        'svg' => 'M20 8H18V6H16V8H14V10H16V12H18V10H20V8ZM22 14L14 22H2V20L10 12H22V14ZM20 16H12L6 20H20V16Z',
    ],

    'lift' => [
        'label' => 'Lift / Elevator',
        // Elevator icon
        'svg' => 'M7 2L11 6H8V10H6V6H3L7 2ZM17 22L13 18H16V14H18V18H21L17 22ZM2 12H22V14H2V12Z',
    ],

    'disability' => [
        'label' => 'Disability Access',
        // Wheelchair icon
        'svg' => 'M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM19 13V11H15V7H11C10.45 7 10 7.45 10 8V15C10 15.55 10.45 16 11 16H12V22H14V16H17L19 13ZM7 16C5.34 16 4 17.34 4 19C4 20.66 5.34 22 7 22C8.66 22 10 20.66 10 19C10 17.34 8.66 16 7 16Z',
    ],

    'gym' => [
        'label' => 'Gym',
        // Dumbbell icon
        'svg' => 'M20.57 14.86L22 13.43L20.57 12L17 15.57L8.43 7L12 3.43L10.57 2L9.14 3.43L7.71 2L5.57 4.14L4.14 2.71L2.71 4.14L4.14 5.57L2 7.71L3.43 9.14L2 10.57L3.43 12L7 8.43L15.57 17L12 20.57L13.43 22L14.86 20.57L16.29 22L18.43 19.86L19.86 21.29L21.29 19.86L19.86 18.43L22 16.29L20.57 14.86Z',
    ],

    'parking' => [
        'label' => 'Parking',
        // Parking sign icon
        'svg' => 'M13 3H6V21H10V15H13C16.31 15 19 12.31 19 9C19 5.69 16.31 3 13 3ZM13 11H10V7H13C14.1 7 15 7.9 15 9C15 10.1 14.1 11 13 11Z',
    ],

    'medical' => [
        'label' => 'Medical Point',
        // Medical cross icon
        'svg' => 'M19 3H14.82C14.4 1.84 13.3 1 12 1C10.7 1 9.6 1.84 9.18 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM12 3C12.55 3 13 3.45 13 4C13 4.55 12.55 5 12 5C11.45 5 11 4.55 11 4C11 3.45 11.45 3 12 3ZM14 17H10V14H7V10H10V7H14V10H17V14H14V17Z',
    ],

    'computer' => [
        'label' => 'Computer / Tech',
        // Computer/monitor icon
        'svg' => 'M20 18C21.1 18 21.99 17.1 21.99 16L22 6C22 4.9 21.1 4 20 4H4C2.9 4 2 4.9 2 6V16C2 17.1 2.9 18 4 18H0V20H24V18H20ZM4 6H20V16H4V6Z',
    ],

    'wardrobe' => [
        'label' => 'Wardrobe / Cloakroom',
        // Coat hanger icon
        'svg' => 'M12 4C13.1 4 14 4.9 14 6C14 6.74 13.6 7.39 13 7.73V8.5L21 14V16L12 10.5L3 16V14L11 8.5V7.73C10.4 7.39 10 6.74 10 6C10 4.9 10.9 4 12 4ZM4 18H20V20H4V18Z',
    ],

    // Add more icons below as needed
    // 'your_icon_key' => [
    //     'label' => 'Your Icon Label',
    //     'svg' => 'M... (SVG path data here)',
    // ],
];
