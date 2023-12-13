<?php
/*
Plugin Name: Stripe Subscriptions Import
Description: Import all the subscriptions from Stripe and create them in Woocommerce Subscriptions.
Version: 1.0
*/

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

global $siteUrl;
$siteUrl = site_url();

function pluginMenu() {
    add_menu_page(
        'Stripe Import Subs',
        'Stripe Import Subs',
        'manage_options',
        'import-stripe-subscriptions',
        'importStripePluginPage',
        'dashicons-database-import'
    );
}
add_action('admin_menu', 'pluginMenu');




function importStripePluginPage() {
    ?>

    <style>
        .data__wrapper{
            background: #fff;
            margin: 5px 0;
            padding: 20px;
            width: 50%;            
        }

        .data__group_header{
            display: flex;
            justify-content: space-between;
            padding: 20px;
            margin: 5px 0;
            border-bottom: 3px solid #000;   
            font-weight: bold;
            text-align: center;
        }
        .data__group_content{
            display: flex;
            justify-content: space-between;
            padding: 20px;
            margin: 5px 0;
            border-bottom: 1px solid #333;    
            text-align: center;        
        }

        .buttons__wrapper{
            display: flex;
            align-items: center;
            gap: 20px;
            width: 100%;
        }

        .inputs__group{
            display: flex;
            align-items: center;
            margin: 40px 0;
        }
    </style>

    <div class="wrap">
        <h2>Stripe Import Subs Settings</h2>
        <form method="post">
            <?php settings_fields('custom-email-reminder-settings'); ?>
            <?php do_settings_sections('custom-email-reminder'); ?>
            <div class="inputs__wrapper">
                <div class="inputs__group">
                    <label for="stripe_api" scope="row">Stripe API Key</label>
                    <td><input type="text" name="stripe_api" value="<?php echo get_option('stripe_api', ''); ?>" style="width: 50%" /></td>
                </div>             
            </div>

            <div class="buttons__wrapper">
                <?php submit_button('Get Subscriptions from Stripe', 'primary', 'submit'); 

                    submit_button('Import Subscriptions to Woocommerce', 'primary', 'import-subs'); 
                    submit_button('Erase All', 'secondary', 'erase-all'); 
                ?>


            </div>
        </form> 
    </div>

    <?php

    if (isset($_POST['submit'])) {
        $stripe_api = $_POST['stripe_api'];
        $stripe = new \Stripe\StripeClient($stripe_api);
        $limit = 20;
        $subscriptions = $stripe->subscriptions->all(['status' => 'active', 'limit' => $limit]); 
        $allSubscriptions = [$subscriptions];



        $count = 0;

        //print_r(sizeof($subscriptions->data));

        // while(sizeof($subscriptions->data)){
        //    echo  $count = $count + sizeof($subscriptions->data);

        //     $starting_after = end($subscriptions->data)->id;
        //     $subscriptions = $stripe->subscriptions->all(['status' => 'active', 'limit' => $limit, 'starting_after' => $starting_after]); 
        //     array_push($allSubscriptions, $subscriptions);
        //     file_put_contents(__DIR__ . "/temp/subscriptions.json", json_encode($allSubscriptions));
        // }

        file_put_contents(__DIR__ . "/temp/subscriptions.json", json_encode($allSubscriptions));
        update_option('stripe_api', $stripe_api);
       
        ?>  


        
        <div class="data__wrapper">   
            <div class="data__group_header">
                    <span>Customer ID</span>
                    <span>Email</span>
                    <span>Product</span>
                    <span>Stripe Source ID</span>
            </div>         
                <?php

                echo "TOTAL SUBSCRIPTIONS: $count <br><br>";

                foreach($allSubscriptions as $subscription_array){
                    
                    foreach($subscription_array as $subscription){
                    $customer = $stripe->customers->retrieve($subscription->customer,[]);
                    $product = $subscription->items->data[0]->plan->product;


                    ?>                
                        <div class="data__group_content">
                            <span><?php echo $subscription->customer; ?></span>
                            <span><?php echo $customer->email; ?></span>
                            <span><?php echo $product;?> </span>
                            <span><?php echo $customer->invoice_settings->default_payment_method;?> </span>
                        </div>
                    <?php                         
                } 
                }
                ?>
        </div>
    <?php }

    else if(isset($_POST['import-subs'])){
        createSubscriptionsInDatabase();
    }

    else if(isset($_POST['erase-all'])){
        eraseAllSubscriptionsImported();
    }

    ?>
    
    <?php
}


function createSubscriptionsInDatabase(){
    global $siteUrl;
    $allSubscriptions = json_decode(file_get_contents(__DIR__ . "/temp/subscriptions.json"));

    $woocommerce = new Client(
        $siteUrl,
        WOO_CONSUMER_KEY,
        WOO_CONSUMER_SECRET,
        [
            'version' => 'wc/v3',
        ]
    );

    $stripe_api = get_option('stripe_api', '');
    $stripe = new \Stripe\StripeClient($stripe_api);

    foreach(array_chunk($allSubscriptions, 40) as $batch){
        foreach($batch as $subs){
            foreach($subs->data as $subscription){
            
                $customer = $stripe->customers->retrieve($subscription->customer,[]);
                $current_user = get_user_by('email', $customer->email);
                $userId = $current_user->ID;
                $user_first_name = $current_user->first_name;
                $user_last_name = $current_user->last_name;
                $user_email = $customer->email;
                $subscription_status = $subscription->status;
                $interval = $subscription->items->data[0]->plan->interval;            
                $interval_count = $subscription->items->data[0]->plan->interval_count;
                $start_date = date('Y-m-d H:i:s', $subscription->start_date);
                $next_payment_date = date('Y-m-d H:i:s', $subscription->current_period_end);
                $stripe_customer_id = $subscription->customer;
                $stripe_source_id = $customer->invoice_settings->default_payment_method;
                $quantity = $subscription->items->data[0]->quantity;

                $product = $subscription->items->data[0]->plan->product;
            
                $product_id = "";
                $variation_id = "";
                $currency = "USD";

                //STANDARD
                if($product === 'prod_Otk6tNpBaFEvbI' || $product === 'prod_OTBQozjkjXeQRR'){
                    $product_id = 1580;
                    $variation_id = $interval === 'year' ? 1596 : 1589;

                //STANDARD GBP
                }elseif($product === 'prod_OyByOYZukt2ViG'){
                    $product_id = 1580;
                    $variation_id = $interval === 'year' ? 1596 : 1589; 
                    $currency = 'GBP';

                //BUSINESS
                }elseif($product === 'prod_Otk4nO3qlWbSs6' || $product === 'prod_P6Jo884N5giGpG' || $product === 'prod_OTBO873cqj2Wg5' || $product === 'prod_OTBPV7p4PFQA7c'){
                    $product_id = 1590;
                    $variation_id = $interval === 'year' ? 1592 : 1591;

                //BUSINESS 2X
                }elseif($product === 'prod_Oa7RNZdnzAp6Fx'){
                    $product_id = 1590;
                    $variation_id = $interval === 'year' ? 1592 : 1591;
                    $quantity = 2;
                
                //BUSINESS GBP
                }elseif($product === 'prod_OTBRTR9E1UB7A9' || $product === 'prod_OyC0RuvjirkRri'){
                    $product_id = 1590;
                    $variation_id = $interval === 'year' ? 1592 : 1591;
                    $currency = 'GBP';
                }

                //AGENCY
                elseif($product === 'prod_Otk8urLtkWsOI2'){
                    $product_id = 1593;
                    $variation_id = $interval === 'year' ? 1594 : 1595;

                //AGENCY GBP
                }elseif($product === 'prod_OyBlQMOUlVyUfC'){
                    $product_id = 1593;
                    $variation_id = $interval === 'year' ? 1594 : 1595;
                    $currency = 'GBP';
                }
                else{
                    $product_id = 927;
                    $variation_id = $interval === 'year' ? 929 : 928;
                }


                $subscriptionData = [
                    'customer_id'       => $userId,
                    'status'            => $subscription_status,
                    'currency'          => $currency,
                    'billing_period'    => $interval,
                    'billing_interval'  => $interval_count,
                    'start_date'        => $start_date,
                    'next_payment_date' => $next_payment_date,
                    'payment_method'    => 'stripe',
                    'payment_details'   => [
                    'post_meta' => [
                        "_stripe_customer_id" => $stripe_customer_id,
                        "_stripe_source_id"   => $stripe_source_id,
                    ]
                    ],
                    'billing' => [
                        'first_name' => $user_first_name,
                        'last_name'  => $user_last_name,
                        'address_1'  => '',
                        'address_2'  => '',
                        'city'       => '',
                        'state'      => '',
                        'postcode'   => '',
                        'country'    => '',
                        'email'      => $user_email,
                        'phone'      => ''
                    ],
                    'line_items' => [
                        [
                            'product_id' => $product_id,
                            'variation_id' => $variation_id,
                            'quantity'   => $quantity,                         
                        ],
                    ],

                ];  

                
                try{
                    $woocommerce->post('subscriptions', $subscriptionData);
                    echo '<div class="notice notice-success is-dismissible"><p>Subscriptions Imported!</p></div>';

                }catch (HttpClientException $e) {
                    echo '<pre><code>' . print_r($e->getMessage(), true) . '</code><pre>'; // Error message.
                    echo '<pre><code>' . print_r($e->getRequest(), true) . '</code><pre>'; // Last request data.
                    echo '<pre><code>' . print_r($e->getResponse(), true) . '</code><pre>'; // Last response data.
                }  
            
            }
        }
    }

}


function eraseAllSubscriptionsImported(){
    global $siteUrl;

    $woocommerce = new Client(
        $siteUrl,
        WOO_CONSUMER_KEY,
        WOO_CONSUMER_SECRET,
        [
            'version' => 'wc/v3',
        ]
    );

    $subscriptions = $woocommerce->get('subscriptions', ['per_page'=>100]);

    foreach($subscriptions as $subscription){
        try{
            $woocommerce->delete("subscriptions/$subscription->id", ['force' => true]);
            echo '<div class="notice notice-success is-dismissible"><p>Subscriptions Removed!</p></div>';

        }catch (HttpClientException $e) {
            echo '<pre><code>' . print_r($e->getMessage(), true) . '</code><pre>'; // Error message.
            echo '<pre><code>' . print_r($e->getRequest(), true) . '</code><pre>'; // Last request data.
            echo '<pre><code>' . print_r($e->getResponse(), true) . '</code><pre>'; // Last response data.
        } 
    }
}

