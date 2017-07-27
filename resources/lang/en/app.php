<?php

return [
    'shipping_method' => [
        'choose_sending_method' => 'Choose sending method',
    ],
    'print_labels' => [
        'print_label' => 'Print labels',
        'print_label_fulfill' => 'Print labels & Fulfill',
        'return_label' => 'Print return label',
        'description' => 'List of created shipments',
        'order_id' => 'Order ID',
        'tracking_code' => 'Tracking code',
        'status' => 'Status',
        'get_the_label' => 'Get the shipping label',
        'get_label_link' => 'Link',
        'statuses' => [
            'created' => 'Shipping has been created',
            'sent' => 'Shipping for the order already exists',
            'need_shipping_address' => 'Failed due to absence of shipping address',
            'no_shipping_service' => 'Shipping service for the rate is not set',
        ],
    ],
    'settings' => [
        'settings' => 'Settings',
        'shipment_settings' => 'Shipment settings',
        'company_info' => 'Company information',
        'testing' => 'Testing',
        'company_name' => 'Pakettikauppa',
        'instructions' => 'Learn more about the application at the <a href=":instruction_url">:company_name Help Center</a>.',
        'shipping_method' => 'Shipping method',
        'additional_services' => 'Additional services',
        'business_name' => 'Business name',
        'address' => 'Address',
        'postcode' => 'Postcode',
        'city' => 'City',
        'country' => 'Country',
        'email' => 'Email',
        'phone' => 'Phone',
        'cash_on_delivery' => 'Cash on delivery settings',
        'iban' => 'Bank account number',
        'bic' => 'BIC code',
        'test_mode' => 'Enable test mode',
        'save_settings' => 'Save settings',
        'api_key' => 'API key',
        'api_secret' => 'API secret',
        'setup_wizard' => 'Wizard setup',
        'wizard' => [
            'is_new_user' => 'Do you have Pakettikauppa API credentials?',
            'register' => 'Register',
            'sign_contract' => 'Sign the contract',
            'enter_api_credentials' => 'Enter API credentials',
            'sign_contract_now' => 'Sign contract now',
            'yes' => 'Yes',
            'no_new' => 'No, I\'m a new user',
            'back_to_settings' => 'Back to settings',
            'next' => 'Next',
        ],
    ],

    'messages' => [
        'error' => 'Error',
        'no_api' => 'No API credentials are set',
        'ready' => 'Everything is ready',
        'only_test_mode' => 'Without API credentials you can use the application only in test mode',
        'no_api_set_error' => 'Setup API credentials or turn on the test mode in <a href=":settings_url">Settings</a>.',
        'invalid_credentials' => 'API credentials are not valid',
        'no_tracking_info' => 'Tracking information for the order is not available',
        'success' => 'Task was successful!',
        'fail' => 'Procedure has failed',
        'api_credentials_saved' => 'API credentials have been successfully saved. Go to the next step.',
        'wait_for_email' => 'Wait for an email from our sales team',
        'register_first' => 'You must register first',
    ],
    'tracking_info' => [
        'transaction' => 'Transaction',
        'title' => 'Tracking information',
        'status' => 'Status',
        'postcode' => 'Postcode',
        'post_office' => 'Post office',
        'timestamp' => 'Event timestamp',
    ],

    'status_codes' => [
        "31" => [
            "full" => "Item is in transport",
            "short" => "In transport"
        ],
        "22" => [
            "full" => "Item has been handed over to the recipient",
            "short" => "Delivered",
        ],
        "56" => [
            "full" => "Item not delivered – delivery attempt made",
            "short" => "Not delivered",
        ],
        "48" => [
            "full" => "Item is loaded onto a means of transport",
            "short" => "In transit",
        ],
        "71" => [
            "full" => "Item is ready for delivery transportation",
            "short" => "In delivery",
        ],
        "91" => [
            "full" => "Item is arrived to a post office",
            "short" => "In post office",
        ],
        "77" => [
            "full" => "Item is returning to the sender",
            "short" => "Returning",
        ],
        "38" => [
            "full" => "C.O.D payment is paid to the sender",
            "short" => "Paid",
        ],
        "68" => [
            "full" => "Pre-information is received from sender",
            "short" => "Pre-informed",
        ],
        "13" => [
            "full" => "Item is collected from sender - picked up",
            "short" => "Collected",
        ],
        "99" => [
            "full" => "Outbound",
            "short" => "Outbound",
        ],
        "45" => [
            "full" => "Informed consignee of arrival",
            "short" => "Informed",
        ],
        "20" => [
            "full" => "Exception",
            "short" => "Exception",
        ],
    ],
];