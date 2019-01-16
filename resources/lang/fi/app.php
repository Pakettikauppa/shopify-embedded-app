<?php

return [
    'shipping_method' => [
        'choose_sending_method' => 'Valitse lähetystapa',
    ],
    'error' => [
        'error_page' => 'Tapahtui virhe',
    ],
    'print_labels' => [
        'print_label' => 'Tulosta Osoitetarra',
        'fetch_all' => 'Tulosta kaikki osoitetarrat',
        'print_label_fulfill' => 'Tulosta osoitetarra & Fulfill',
        'return_label' => 'Tulosta palautustarra',
        'description' => 'Lista luoduista lähetyksistä',
        'order_id' => 'Tilaus nro',
        'tracking_code' => 'Seurantakoodi',
        'status' => 'Tila',
        'get_the_label' => 'Hae osoitetarra',
        'get_label_link' => 'Linkki',
        'back_to_orders' => 'Takaisin tilauksiin',
        'statuses' => [
            'created' => 'Lähetys on luotu',
            'sent' => 'Lähetys on jo luotu tilaukselle',
            'need_shipping_address' => 'Epäonnistui koska toimitusosoite puuttui',
            'no_shipping_service' => 'Ei toimitustapaa valittuna',
            'not_in_inventory' => 'Tuotetta ei varastossa',
        ],
    ],
    'settings' => [
        'activation_code' => 'Asiointikoodi',
        'create_activation_code' => 'Luo asiointikoodi (Helposti-koodi, aktivointikoodi) kaikille lähetyksille. Koodi lisätään tilauksen Notesiin, jolloinka se saattaa tulla näkyviin myös asiakkaalle meneviin tietoihin!',
        'testmode_on' => 'Kytke testitila päälle',
        'testmode_off' => 'Kytke tuotantotila päälle',
        'latest-news' => 'Ajankohtaista',
        'pickuppoints-settings' => 'Noutopisteasetukset',
        'api-settings' => 'API asetukset',
        'api-info' => 'Löydät API-tunnukset Pakettikaupan <a href="https://hallinta.pakettikauppa.fi/profile#api-keys" target="_blank">hallintapaneelista Profiili-sivulta</a>. Mikäli sinulla ei vielä ole tunnuksia Pakettikauppaan, voit rekisteröityä osoitteessa <a href="https://www.pakettikauppa.fi/" target="_blank">https://www.pakettikauppa.fi/</a>',
        'generic-settings' => 'Muut',
        'settings' => 'Asetukset',
        'language' => 'Kieli',
        'shipment_settings' => 'Lähetysasetukset',
        'default_shipping_method' => 'Oletus lähetystapa',
        'shopify_method' => 'Toimitustapa Shopifystä',
        'pk_method' => 'Pakettikaupan toimitustavat',
        'company_info' => 'Lähettäjän tiedot',
        'testing' => 'Testitila',
        'company_name' => 'Pakettikauppa',
        'instructions' => 'Lue lisää  palveluista <a href=":instruction_url">Pakettikaupan kotisivuilta</a>.',
        'shipping_method' => 'Toimitustapa',
        'additional_services' => 'Lisäpalvelut',
        'business_name' => 'Yritys',
        'address' => 'Osoite',
        'postcode' => 'Postinumero',
        'city' => 'Postitoimipaikka',
        'country' => 'Maa',
        'email' => 'Sähköposti',
        'phone' => 'Puhelinnumero',
        'enable_carrier_api' => 'Noutopistetoiminto tarvitsee Carrier Services APIn toimintaan. Shopify Liteen ei ole mahdollista saada noutopisteitä. Palvelu on Shopify Basicin käyttäjille 20 USD/kk tai ilmainen mikäli käytössä on vähintään vuosittainen tilaus. Voit muuttaa tilauksesi vuositilaukseksi Shopifyssä: settings > Account > Compare plan > Choose a plan > valitse ANNUAL. Muille kuin Shopify Basic ja Lite käyttäjille toiminto sisältyy kuukausimaksuun. Tämän jälkeen pyydä Shopifyn asiakaspalvelu kytkemään päälle Carrier Service API.',
        'pickuppoint_providers' => 'Minkä toimittajan noutopisteet haetaan?',
        'pickuppoints_count' => 'Kuinka monta lähintä noutopistettä näytetään ostajalle?',
        'pickuppoints_count_0' => 'Älä näytä noutopisteitä',
        'cash_on_delivery' => 'Postiennakon asetukset',
        'iban' => 'Pankkitili (IBAN)',
        'bic' => 'BIC',
        'test_mode' => 'Kytke testitila päälle',
        'save_settings' => 'Tallenna asetukset',
        'api_key' => 'API key',
        'api_secret' => 'API secret',
        'saved' => 'Asetukset ovat tallennettu',
        'setup_wizard' => 'Ohjattu asennus',
        'print_return_labels' => 'Luo aina palautuslähetys lähetyksen yhteyssä. Huom! Shopifyssä oleva seuranta toimii vain alkuperäiselle lähetykselle.',
        'include_discounted_price_in_trigger' => 'Ota hintarajan laskennassa mukaan tuotteiden alennukset.',
        'pickuppoints' => [
            'title' => 'Noutopisteiden asetukset',
            'provider' => 'Kuljetusyhtiö',
            'base_price' => 'Perushinta (€)',
            'trigger_price' => 'Hintaraja (€)',
            'triggered_price' => 'Hinta ylityksen jälkeen (€)',
        ],
        'wizard' => [
            'is_new_user' => 'Onko sinulla jo Pakettikaupan API tunnukset?',
            'register' => 'Rekisteröidy',
            'sign_contract' => 'Allekirjoita sopimus',
            'enter_api_credentials' => 'Syötä API tunnukset',
            'sign_contract_now' => 'Allekirjoita sopimus nyt',
            'yes' => 'Kyllä',
            'no_new' => 'Ei, olen uusi käyttäjä',
            'back_to_settings' => 'Takaisin asetuksiin',
            'next' => 'Seuraava',
        ],
    ],

    'messages' => [
        'error' => 'Virhe',
        'no_api' => 'API tunnuksia ei ole vielä syötetty',
        'ready' => 'Kaikki on valmista',
        'only_test_mode' => 'Ilman API tunnuksia voit käyttää ohjelmaa vain testitilassa',
        'no_api_set_error' => 'Aseta API tunnukset tai kytke testitila päälle <a href=":settings_url">asetuksissa</a>.',
        'invalid_credentials' => 'API tunnukset eivät ole oikein',
        'credentials_missing' => 'API tunnuksia ei ole syötetty',
        'no_tracking_info' => 'Seurantatiedot tilaukselle eivät ole vielä saatavilla',
        'success' => 'Tehtävä onnistui!',
        'fail' => 'Tehtävä epäonnistui',
        'api_credentials_saved' => 'API tunnukset ovat nyt tallennettu. Siirry seuraavaan vaiheeseen.',
        'wait_for_email' => 'Odota sähköpostia myyntitiimiltämme',
        'register_first' => 'Sinun täytyy ensin rekisteröityä',
        'in-testing' => 'Liitäntäsi on nyt testitilassa!',
        'in-production' => 'Liitäntäsi on nyt tuotantotilassa!',
    ],
    'tracking_info' => [
        'transaction' => 'Lähetys',
        'title' => 'Seurantatapahtuma',
        'status' => 'Status',
        'postcode' => 'Postinumero',
        'post_office' => 'Postitoimipaikka',
        'timestamp' => 'Aikaleima',
    ],

    'status_codes' => [
        "31" => [
            "full" => "Lähetys on kuljetuksessa",
            "short" => "Kuljetuksessa"
        ],
        "22" => [
            "full" => "Lähetys on luovutettu vastaanottajalle",
            "short" => "Luovutettu",
        ],
        "56" => [
            "full" => "Lähetystä ei ole toimitettu – luovutusyritys on tehty",
            "short" => "Ei toimitettu",
        ],
        "48" => [
            "full" => "Lähetys on lastattu runkokuljetukseen",
            "short" => "Kuljetuksessa",
        ],
        "71" => [
            "full" => "Lähetys on lastattu jakelukuljetukseen",
            "short" => "Jakelussa",
        ],
        "91" => [
            "full" => "Lähetys on saapunut postitoimipaikkaan",
            "short" => "Toimipaikassa",
        ],
        "77" => [
            "full" => "Lähetys palautuu lähettäjälle",
            "short" => "Palautuu",
        ],
        "38" => [
            "full" => "Lähetykseen liittyvä postiennakkomaksu on suoritettu",
            "short" => "Maksettu",
        ],
        "68" => [
            "full" => "Lähetyksen EDI-tiedot vastaanotettu lähettäjältä",
            "short" => "Tiedot vastaanotettu",
        ],
        "13" => [
            "full" => "Lähetys on noudettu lähettäjältä",
            "short" => "Noudettu",
        ],
        "99" => [
            "full" => "Lähetys lähdössä ulkomaille",
            "short" => "Lähdössä ulkomaille",
        ],
        "45" => [
            "full" => "Lähetyksestä on lähetetty saapumisilmoitus",
            "short" => "Saapumisilmoitus",
        ],
        "20" => [
            "full" => "Lähetylle on tehty poikkeama",
            "short" => "Poikkeama",
        ],
        // matkahuolto codes
        "LS" => [
            "full" => "Lähtevän hyllytys",
            "short" => "Kuljetuksessa"
        ],
        'LA' => [
            "full" => "Lastattu runkokuormaan",
            "short" => "Kuljetuksessa"
        ],
        'JA' => [
            "full" => "Saapunut määräasemalle",
            "short" => "Määräasemalla"
        ],
        'SA' => [
            "full" => "Saapunut määräpakettipisteeseen",
            "short" => "Määräpakettipisteessä"
        ],
        'IL' => [
            "full" => "Lähetyksestä on lähetetty saapumisilmoitus",
            "short" => "Saapumisilmoitus"
        ],
        'LU' => [
            "full" => "Lähetys on luovutettu vastaanottajalle",
            "short" => "Luovutettu"
        ],
    ],
];