<?php

return [
    'home_page_section' => [
        'label' => 'Home page Sections',
        'validation_rules' => [
            'presentation_section' => 'text',
            'action_motivate_section' => 'string',
            'about_us_section' => [
                'title' => 'string',
                'intro' => 'text',
            ],
            'promo_section' => [
                'title' => 'string',
                'intro' => 'text_editor'
            ]
        ],
    ],
    'gallery_page_section' => [
        'label' => 'Gallery Page Sections',
        'validation_rules' => [
            'category_select' => [
                'category_select_placeholder' => 'string',
                'category_select_items' => 'function',
            ],
            'sort_select' => [
                'sort_select_placeholder' => 'string',
                'sort_select_items' => 'function',
            ],
            'search_input' => [
                'search_input_placeholder' => 'string',
            ],
            'gallery_items' => 'function'
        ]
    ],
    'aboutus_page_section' => [
        'label' => 'About Us Page Sections',
        'validation_rules' => [
            'title' => 'string',
            'main_text' => 'text_editor',
            'rated_pictures' => 'function'
        ],
    ],
    'editor_page_section' => [
        'label' => 'Editor Page Sections',
        'validation_rules' => [
            'width_input_label' => 'string',
            'width_input_title' => 'string',
            'heigh_input_label' => 'string',
            'heigh_input_title' => 'string',
            'pencil_title' => 'string',
            'fulfill_title' => 'string',
            'eraser_title' => 'string',
            'undo_title' => 'string',
            'redo_title' => 'string',
            'clear_title' => 'string',
            'colors_title' => 'string',
            'copyfield_title' => 'string',
            'new_draft_title' => 'string',
            'bead_preview_label' => 'string',
            'save_draft_title' => 'string'
        ],
    ]
];
