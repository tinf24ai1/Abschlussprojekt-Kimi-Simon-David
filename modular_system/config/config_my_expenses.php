<?php
return [
    'module_title' => 'Expense Log',
    'module_title_singular' => 'Expense',
    'table_name' => 'financial_transactions',
    'primary_key' => 'transaction_id',
    'fields' => [
        'transaction_id' => ['label' => 'ID', 'type' => 'id', 'list_display' => false, 'form_display' => false],
        'transaction_date' => ['label' => 'Date', 'type' => 'date', 'list_display' => true, 'form_display' => true, 'required' => true],
        'vendor_name' => ['label' => 'Vendor/Payee', 'type' => 'text', 'list_display' => true, 'form_display' => true, 'required' => true, 'searchable' => true],
        'category_id' => [
            'label' => 'Category',
            'type' => 'foreign_key',
            'list_display' => true, // We'll aim to show category_name
            'form_display' => true,
            'required' => true,
            'lookup_table' => 'expense_categories',
            'lookup_id_column' => 'cat_id',
            'lookup_value_column' => 'category_name',
            'placeholder' => '-- Select Category --'
        ],
        'amount_spent' => ['label' => 'Amount', 'type' => 'currency', 'list_display' => true, 'form_display' => true, 'required' => true, 'min' => 0.01, 'step' => 0.01],
        'payment_method' => ['label' => 'Payment Method', 'type' => 'select', 'options' => ['Credit Card'=>'Credit Card', 'Cash'=>'Cash', 'Bank Transfer'=>'Bank Transfer', 'Debit Card' => 'Debit Card', 'Other' => 'Other'], 'list_display' => true, 'form_display' => true, 'placeholder' => '-- Select Method --'],
        'receipt_notes' => ['label' => 'Notes/Receipt Ref', 'type' => 'textarea', 'list_display' => false, 'form_display' => true]
    ],
    'list_actions' => ['create', 'edit', 'delete'],
    'default_sort_column' => 'transaction_date',
    'default_sort_direction' => 'DESC',
    'records_per_page' => 15,
];
