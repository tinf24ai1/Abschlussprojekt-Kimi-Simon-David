<?php
return [
    'module_title' => 'My Reading List',
    'module_title_singular' => 'Book',
    'table_name' => 'my_books',
    'primary_key' => 'book_id',
    'fields' => [
        'book_id' => ['label' => 'ID', 'type' => 'id', 'list_display' => false, 'form_display' => false],
        'title' => ['label' => 'Book Title', 'type' => 'text', 'list_display' => true, 'form_display' => true, 'required' => true, 'searchable' => true],
        'author_name' => ['label' => 'Author', 'type' => 'text', 'list_display' => true, 'form_display' => true, 'required' => true, 'searchable' => true],
        'publication_year' => ['label' => 'Year', 'type' => 'number', 'list_display' => true, 'form_display' => true, 'min' => 1000, 'max' => date('Y')],
        'date_read' => ['label' => 'Date Read', 'type' => 'date', 'list_display' => true, 'form_display' => true],
        'rating_stars' => ['label' => 'Rating (1-5)', 'type' => 'select', 'options' => [1=>'1 Star', 2=>'2 Stars', 3=>'3 Stars', 4=>'4 Stars', 5=>'5 Stars'], 'list_display' => true, 'form_display' => true, 'placeholder' => '-- Select Rating --'],
        'summary_notes' => ['label' => 'Synopsis/Notes', 'type' => 'textarea', 'list_display' => false, 'form_display' => true]
    ],
    'list_actions' => ['create', 'edit', 'delete'],
    'default_sort_column' => 'date_read',
    'default_sort_direction' => 'DESC',
    'records_per_page' => 10,
];
