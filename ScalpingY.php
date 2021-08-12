<?php

include("config.php");
date_default_timezone_set('Asia/Jakarta');

class Scalping 
{

    public  $balance_idr = 0;
    public  $balance_coin = 0;
    public  $coin = "";
    public  $difference = 0;
    public  $difference_sell = 0;
    private $price_buy = 0;
    private $price_sell = 0;
    private $your_buy = 0;
    private $your_sell = 0;
    private $ready_buy = false;
    private $ready_sell = false;
    private $status_buy = false; //order_id buy
    private $status_sell = false; //order_id sell
    private $cancel_buy_all = false; //order_id buy
    private $cancel_sell_all = false; //order_id buy
    private $skip_buy = false; //order_id buy
    private $skip_sell = false; //order_id buy
    private $min_sell = 1; //minimal jual di harga ini + 1%
    private $price_by_low = 0; //sebagai batasan stop looping
    private $price_buy_low_percentage = 0; //perbedaan antara harga beli dengan harga bawah yang diset
    private $dynamic_border = 0; //dynamic price adding to price by low
    private $max_buy_history = 0; //harga terbesar selama history trading
    private $smart_contract = true; //jalankan smart contract
    private $count_history = 19; //ambil berapa row history
    private $temp_price_history = 0; // harga history default bila tidak ditemukan pada history transaksi
    private $diff_buy = 0.01; // beda diff buy
    private $diff_sell = 0.002; // beda diff sell
    private $cut_loss = false; // beda diff buy
    private $stop_buy_price = 999999999999999; // stop buy
    private $margin = 3; // jeda margin dengan harga buy terbaru
    private $margin_plus = 1.5; // jumlah penambahan harga margin saat perbedaan harga support melewati margin top yang ditentikan
    private $set_maxsaldo   = 500000;


    private $url_private = 'https://indodax.com/tapi';
    private $url_list_data = 'http://localhost:8000/api/';

    // Please find Key from trade API Indodax exchange
    private $key = '';
    // Please find Secret Key from trade API Indodax exchange
    private $secretKey = '';
   
    //email from api 
    private $email = '';
    

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        // parent::__construct();
        // $this->key = env("INDODAX_KEY");
        // $this->secretKey = env("INDODAX_SECRETKEY");
        // $this->email = env("INDODAX_MAIL");
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->coin = $this->argument('koin');
        if (empty($this->coin)) {
            $this->coin = $this->ask('Masukan nama koin?');
        }

        $this->price_by_low         = $this->ask('Masukan Support Price (0)? ') ?? 0;
        $this->stop_buy_price       = $this->ask('Masukan Stop Buy Price (999999999999999)') ?? 999999999999999;
        $this->count_history        = $this->ask('Masukan Count History (20)') ?? 19;
        $this->smart_contract       = $this->ask('Nonaktifkan Smart Contract (false) ?') ?? true;
        $this->margin               = $this->ask('Masukan Margin (3%)? ') ?? 3; 
        $this->margin_plus          = $this->ask('Masukan Margin Plus (1.5%)? ') ?? 1.5; 
        $this->diff_buy             = $this->ask('Masukan Difference buy (0.01) ?' ) ?? 0.01;
        $this->diff_sell            = $this->ask('Masukan Difference sell (0.002) ?') ?? 0.002;
        $this->cut_loss             = $this->ask('Aktifkan Stoploss (false) ?') ?? false;

        // $this->report_server();

        
        if (!empty($this->coin)) {
         
            for ($i=0; $i < 10000; $i++) { 
                //init

                $this->cancel_buy_all = false;
                $this->cancel_sell_all = false;
                
                $this->transHistory();
                
                if($this->price_sell > $this->stop_buy_price) {
                    $this->skip_buy = true;
                    $this->ready_buy = false;
                    $this->skip_sell = false;
                    $this->warn("============ ". $this->coin." Stop Buy ==========\n");
                    
                }else{

                    if($this->max_buy_history > 0  && $this->smart_contract == TRUE){
                        
                        if($this->price_by_low < $this->price_buy){

                            if($this->max_buy_history > $this->price_sell){
                                $this->skip_buy = false;
                                $this->skip_sell = true;
                                $this->warn("============ ". $this->coin." Smart Contract 1 ==========\n");
                            }else{
                                $this->skip_buy = false;
                                $this->skip_sell = false;
                                $this->warn("============ ". $this->coin." Smart Contract 2 ==========\n");
                            }

                        }else{
                            if($this->cut_loss && !empty($this->price_by_low) && !empty($this->price_buy)){
                                $this->skip_buy = true;
                                $this->skip_sell = false;
                                $this->ready_sell = true;
                                $this->set_cutloss();
                                $this->warn("============ Terminated ". $this->coin." Stop Loss ==========\n");
                                
                            }else{

                                $this->skip_buy = true;
                                $this->skip_sell = true;
                                $this->warn("============ Stop ". $this->coin." Bot ==========\n");
                            }
                        }

                    }else{
                        if($this->price_by_low > $this->price_buy && !empty($this->price_by_low) && !empty($this->price_buy && $this->cut_loss)){
                            $this->skip_buy = true;
                            $this->skip_sell = false;
                            $this->ready_sell = true;
                            $this->set_cutloss();
                            $this->warn("============ Terminated ". $this->coin." Stop Loss ==========\n");
                            
                            
                        }else{
                            $this->skip_buy = false;
                            $this->skip_sell = false;
                            $this->warn("============ Start ". $this->coin." Bot ==========\n");
                           
                        }
                        
                    }
                }
                
                // ..Smart Contract logic
               
                $this->price_buy_low_percentage = (($this->max_buy_history - $this->price_by_low) / $this->price_by_low) * 100;
                
                if($this->price_buy_low_percentage > $this->margin){
                    $this->dynamic_border = round($this->price_by_low * ($this->margin_plus/100)); 
                    $this->price_by_low = $this->dynamic_border + $this->price_by_low; // set new price_by_low
                }
                // ..Dynamic Border logic 

                $this->get_balance();
                $this->info("balance idr            : ". $this->balance_idr);
                $this->info("balance ".$this->coin."           : ". $this->balance_coin);
            
                
                
                $this->get_depth_koin($this->coin);
    
                
                $this->get_buy();

                if (!$this->skip_buy) {
                    $this->set_buy(); 
                } else {
                    $this->warn("status buy             : SKIP!");
                }
                if (!$this->skip_sell) {
                    $this->set_sell();
                } else {
                    $this->warn("status sell            : SKIP!");
                }

                $this->info("My Set Buy             : ".$this->your_buy);
                $this->info("My Set Sell            : ".$this->your_sell);
                $this->info("Price Buy              : ".$this->price_buy);
                $this->info("Price sell             : ".$this->price_sell);
                $this->info("Highest Buy History    : ".$this->max_buy_history);
                $this->info("Support Price          : ".$this->price_by_low);
                $this->info("Support Margin         : ".$this->price_buy_low_percentage);
                $this->info("Support Margin Plus    : ".$this->dynamic_border);
                $this->warn("\n". $this->coin." Smart Contract Condition \n");
                
                $this->info("Stop Buy Price         : ".$this->stop_buy_price);
                $this->warn(($this->cut_loss)?"Stop Loss              : ON":"Stop Loss              : OFF");
                $this->info("Count History          : ".$this->count_history);
                $this->info("Margin                 : ".$this->margin);
                $this->info("Margin Plus            : ".$this->margin_plus);
                $this->info("Flat Difference Buy    : ".$this->diff_buy);
                $this->info("Flat Difference Sell   : ".$this->diff_sell);
                $this->warn("\n============ ". $this->coin." (".date("Y/m/d H:i:s").") ==========\n");
                $this->report_server();

                sleep(5);
                unset($this->info);
                unset($this->warn);
                unset($this->report_server);
                $flag = 1;
                
            }
        } else {
            $this->coin = $this->ask('koin kosong!');
        }

    }
    
    public function init()
    {
        $this->key          = $this->config["INDODAX_KEY"];
        $this->secretKey    = $this->config["INDODAX_SECRETKEY"];
        $this->email        = $this->config["INDODAX_MAIL"];


        $this->init_server();
    }


    public function init_server()
    {
        $url = $this->config['url_server']."api/cron/".$this->ID_TABLE;
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        ));
    
        $response = curl_exec($curl);
    
        curl_close($curl);
        $json = json_decode ($response);

        if(!empty($json->id_coin) && $json->active){
            $this->log("true");

            $this->coin = $json->id_coin;

            $this->set_maxsaldo = $json->max_saldo;
            // $this->skip_buy     = $json->skip_buy;
            // $this->skip_sell    = $json->skip_sell;

            $this->price_by_low         = $json->price_by_low ?? 0;
            $this->stop_buy_price       = $json->stop_buy_price  ?? 999999999999999;
            $this->count_history        = $json->count_history  ?? 19;
            $this->smart_contract       = $json->smart_contract ?? true;
            $this->margin               = $json->margin  ?? 3; 
            $this->margin_plus          = $json->margin_plus  ?? 1.5; 
            $this->diff_buy             = $json->diff_buy  ?? 0.01;
            $this->diff_sell            = $json->diff_sell  ?? 0.002;
            $this->cut_loss             = $json->cut_loss ?? false;


            $this->report_server();


            if (!empty($this->coin)) {
                $this->get_depth_koin($this->coin);

                for ($i=0; $i < 5; $i++) { 
                    //init
    
                    $this->cancel_buy_all = false;
                    $this->cancel_sell_all = false;
                    
                    $this->transHistory();
                    
                    if($this->price_sell > $this->stop_buy_price) {
                        $this->skip_buy = true;
                        $this->ready_buy = false;
                        $this->skip_sell = false;
                        $this->warn("============ ". $this->coin." Stop Buy ==========\n");
                        exit;
                    }else{
    
                        if($this->max_buy_history > 0  && $this->smart_contract == TRUE){
                            
                            if($this->price_by_low < $this->price_buy){
    
                                if($this->max_buy_history > $this->price_sell){
                                    $this->skip_buy = false;
                                    $this->skip_sell = true;
                                    $this->warn("============ ". $this->coin." Smart Contract 1 ==========\n");
                                }else{
                                    $this->skip_buy = false;
                                    $this->skip_sell = false;
                                    $this->warn("============ ". $this->coin." Smart Contract 2 ==========\n");
                                }
    
                            }else{
                                if($this->cut_loss && !empty($this->price_by_low) && !empty($this->price_buy)){
                                    $this->skip_buy = true;
                                    $this->skip_sell = false;
                                    $this->ready_sell = true;
                                    $this->set_cutloss();
                                    $this->warn("============ Terminated ". $this->coin." Stop Loss ==========\n");
                                    
                                }else{
    
                                    $this->skip_buy = true;
                                    $this->skip_sell = true;
                                    $this->warn("============ Stop ". $this->coin." Bot ==========\n");
                                }
                            }
    
                        }else{
                            if($this->price_by_low > $this->price_buy && !empty($this->price_by_low) && !empty($this->price_buy && $this->cut_loss)){
                                $this->skip_buy = true;
                                $this->skip_sell = false;
                                $this->ready_sell = true;
                                $this->set_cutloss();
                                $this->warn("============ Terminated ". $this->coin." Stop Loss ==========\n");
                                
                                
                            }else{
                                $this->skip_buy = false;
                                $this->skip_sell = false;
                                $this->warn("============ Start ". $this->coin." Bot ==========\n");
                               
                            }
                            
                        }
                    }
                    
                    // ..Smart Contract logic
                   
                    $this->price_buy_low_percentage = (($this->max_buy_history - $this->price_by_low) / $this->price_by_low) * 100;
                    
                    if($this->price_buy_low_percentage > $this->margin){
                        $this->dynamic_border = round($this->price_by_low * ($this->margin_plus/100)); 
                        $this->price_by_low = $this->dynamic_border + $this->price_by_low; // set new price_by_low
                    }
                    // ..Dynamic Border logic 
    
                    $this->get_balance();
                    $this->info("balance idr            : ". $this->balance_idr);
                    $this->info("balance ".$this->coin."           : ". $this->balance_coin);
                
                    
                    
                    $this->get_depth_koin($this->coin);
        
                    
                    $this->get_buy();
    
                    if (!$this->skip_buy) {
                        $this->set_buy(); 
                    } else {
                        $this->warn("status buy             : SKIP!");
                    }
                    if (!$this->skip_sell) {
                        $this->set_sell();
                    } else {
                        $this->warn("status sell            : SKIP!");
                    }
    
                    $this->info("My Set Buy             : ".$this->your_buy);
                    $this->info("My Set Sell            : ".$this->your_sell);
                    $this->info("Price Buy              : ".$this->price_buy);
                    $this->info("Price sell             : ".$this->price_sell);
                    $this->info("Highest Buy History    : ".$this->max_buy_history);
                    $this->info("Support Price          : ".$this->price_by_low);
                    $this->info("Support Margin         : ".$this->price_buy_low_percentage);
                    $this->info("Support Margin Plus    : ".$this->dynamic_border);
                    $this->warn("\n". $this->coin." Smart Contract Condition \n");
                    
                    $this->info("Stop Buy Price         : ".$this->stop_buy_price);
                    $this->warn(($this->cut_loss)?"Stop Loss              : ON":"Stop Loss              : OFF");
                    $this->info("Count History          : ".$this->count_history);
                    $this->info("Margin                 : ".$this->margin);
                    $this->info("Margin Plus            : ".$this->margin_plus);
                    $this->info("Flat Difference Buy    : ".$this->diff_buy);
                    $this->info("Flat Difference Sell   : ".$this->diff_sell);
                    $this->warn("\n============ ". $this->coin." (".date("Y/m/d H:i:s").") ==========\n");
                    $this->report_server();
    
                    sleep(5);
                    unset($this->info);
                    unset($this->warn);
                    unset($this->report_server);
                    $flag = 1;
                    
                }
                $this->send_report();

            } else {
                $this->coin = $this->ask('koin kosong!');
            }

        } elseif (isset($json->active) && $json->active == 0) {
            $this->log("cron tidak aktif");
            
        } else {

            $this->log("\n".$url);
            $this->log("else ".$response);
        }

        echo print_r($json);
    }


    public function get_balance()
    {
        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($res_json);

        $data = [
	        'method' => 'getInfo',
	        'timestamp' => '1578304294000',
	        'recvWindow' => '1578303937000'
	    ];
        $post_data = http_build_query($data, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secretKey);
        
        $headers = ['Key:'.$this->key,'Sign:'.$sign];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->url_private,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        ));

        $response = curl_exec($curl);
        
        curl_close($curl);
        $res_json = json_decode($response,true);
        if ($res_json['success']) {
            $this->balance_idr = $res_json['return']['balance']['idr'];
            $this->balance_coin= $res_json['return']['balance'][$this->coin];
        }
    }


    public function report_server()
    {
        unset($curl);
        unset($response);
        unset($json);
        unset($url);

        $url = $this->url_list_data.'engine-scalping';
    
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query([
                'key'       => $this->key,
                'email'     => $this->email,
                'coin'      => $this->coin,
                'coin_name' => $this->coin,
                'secretKey' => $this->secretKey,
            ]),
            CURLOPT_RETURNTRANSFER => true
        ));
    
        $response = curl_exec($curl);
    
        curl_close($curl);
        $json = json_decode ($response);
        // echo $response ;die;    

        if (!empty($json->error)) {
            $this->error("ERR = ". $response);
            exit();
            // echo $response ;die;    
        } else {
            
        }
    
        
    }
    public function get_depth_koin($pair_id)
    {
        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($json);
        unset($url);
        unset($ticker_sell);
        unset($ticker_buy);
        unset($hitung);

        $url = 'https://indodax.com/api/depth/'.$pair_id."idr";
    
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        ));
    
        $response = curl_exec($curl);
    
        curl_close($curl);
        $json = json_decode ($response);

        if (!empty($json->error)) {
            $this->error("ERR = ". $response);
            exit();
            // echo $response ;die;    
        } else {
            $ticker_sell = 0;
            $ticker_buy = 0;
            foreach ($json->buy as $key => $value) {
                $hitung = $value[0] * $value[1];
                if ($hitung > 90000) {
                    $ticker_buy = $value[0];
                    break;    
                }
            }

            foreach ($json->sell as $key => $value) {
                $hitung = $value[0] * $value[1];
                if ($hitung > 90000) {
                    $ticker_sell = $value[0];
                    break;    
                }
            }


            // $this->difference = (($json->ticker->sell-1) - ($json->ticker->buy+1))/($json->ticker->buy+1) * 100;


            $this->difference = (($ticker_sell-1) - ($ticker_buy+1))/($ticker_buy+1) * 100;
            if ($this->difference < $this->diff_buy ) {
                $this->ready_buy = false;
                // $this->error("difference             : ".$this->difference);
            } else {
                $this->ready_buy = true;
                // $this->info("difference             : ".$this->difference);
            }

            if($this->max_buy_history > 0){

                $this->difference_sell = $this->max_buy_history + round($this->max_buy_history * $this->diff_sell);
                if (($ticker_sell-1) <= $this->difference_sell ) {
                    $this->ready_sell = false;
                } else {
                    $this->ready_sell = true;
    
                }
            }else{
                $this->ready_sell = false;
            }


            $this->price_sell       = ($ticker_sell);
            $this->price_buy        = ($ticker_buy);


        }
    }
    

    public function get_buy()
    {

        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($res_json);
        
        $data = [
	        'method' => 'openOrders',
	        'pair'  => $this->coin."_idr",
	        'timestamp' => '1578304294000',
	        'recvWindow' => '1578303937000'
	    ];
        $post_data = http_build_query($data, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secretKey);
        
        $headers = ['Key:'.$this->key,'Sign:'.$sign];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->url_private,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        ));

        $response = curl_exec($curl);
        
        curl_close($curl);
        $res_json = json_decode($response,true);
        $this->status_sell = false;
        $this->status_buy = false;
        
        if ($res_json['success']) {
            // $this->info(print_r($res_json['return']['orders']));  
            $this->get_depth_koin($this->coin);

            foreach ($res_json['return']['orders'] as $key => $value) {

                if ($value['type'] == "buy") {
                    if ($value['price'] < $this->price_buy || $this->difference < $this->diff_buy || $this->cancel_buy_all || $this->skip_buy) { //jika harga tidak paling atas di cancel
                        $this->warn("cancel order buy       :".$value['price'] ."-----". $this->price_buy);  
                        //cancel order
                        $this->cancel_order($value['order_id'],'buy');
                        
                        $this->status_buy = false;
                        $this->cancel_buy_all = true;
                    } else {
                        $this->status_buy = true;
                    }
                    
                } else {
                    $this->status_sell = true;

                    if($this->max_buy_history > 0){

                        $this->difference_sell = $this->max_buy_history + round($this->max_buy_history * $this->diff_sell);
                        if ($this->price_sell < $this->difference_sell ) {
                            $this->error("cancel order sell      : HOLD ".$value['price'] ."-----". $this->price_sell);  
                            
                        } else {
                            if ($value['price'] > $this->price_sell || $this->cancel_sell_all) { //jika harga tidak paling atas di cancel
                                $this->warn("cancel order sell      : ".$value['price'] ."-----". $this->price_sell);  
                                //cancel order
                                $this->cancel_order($value['order_id'],'sell');
                                
                                $this->status_sell = false;
                                $this->cancel_sell_all = true;

                            }
                        }
                    }else{
                        $this->warn("cancel order sell      : ".$value['price'] ."-----". $this->price_sell);  
                        $this->cancel_order($value['order_id'],'sell');
                        $this->status_sell = false;
                        $this->cancel_sell_all = true;
                        $this->error("cancel order sell      : HOLD ".$value['price'] ."-----". $this->price_sell);  
                    }
                }
                    
            }
            // $this->balance_idr = $res_json['return']['balance']['idr'];
            // $this->balance_coin= $res_json['return']['balance'][$this->coin];
        } else {
            $this->error("ERR       : ".$response);  
        }
    }


    public function cancel_order($order_id,$type)
    {
        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($res_json);
    
        $data = [
	        'method' => 'cancelOrder',
            'pair' => $this->coin.'_idr',
            'order_id' => $order_id,
            'type'      => $type,
	        'timestamp' => '1578304294000',
	        'recvWindow' => '1578303937000'
	    ];
        $post_data = http_build_query($data, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secretKey);
        
        $headers = ['Key:'.$this->key,'Sign:'.$sign];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->url_private,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        ));

        $response = curl_exec($curl);
        
        curl_close($curl);
        $res_json = json_decode($response,true);
        if ($res_json['success']) {
            $this->balance_idr = $res_json['return']['balance']['idr'];
            $this->balance_coin= $res_json['return']['balance'][$this->coin];
        }
    }

    
    public function set_buy()
    {
        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($res_json);

        $idr_buy = $this->balance_idr;
        $check_buy = $idr_buy;
        if ($this->set_maxsaldo) {
            $idr_buy    = $this->set_maxsaldo - (($this->balance_hold_coin +$this->balance_coin ) *$this->price_buy) - $this->order_buy_pending;
            $check_buy  = $this->balance_idr - $idr_buy;

            $this->info("set_maxsaldo      : ".($this->set_maxsaldo));
            $this->info("order_buy_pending      : ".($this->order_buy_pending));


            if ($check_buy < 0 ) {
                $idr_buy = $this->balance_idr;
            }
        }

        if ($this->ready_buy && $idr_buy > 20000) {
            
            $this->your_buy         = ($this->price_buy + 1);


            $data = [
                'method' => 'trade',
                'timestamp' => '1578304294000',
                'recvWindow' => '1578303937000',
                'pair' => $this->coin.'_idr',
                'type' => 'buy',
                'price' => $this->your_buy,
                'idr' => $idr_buy,
            ];
            $post_data = http_build_query($data, '', '&');
            $sign = hash_hmac('sha512', $post_data, $this->secretKey);
            
            $headers = ['Key:'.$this->key,'Sign:'.$sign];
    
            $curl = curl_init();
    
            curl_setopt_array($curl, array(
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_URL => $this->url_private,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true
            ));
    
            $response = curl_exec($curl);
            
            curl_close($curl);
            $res_json = json_decode($response,true);
            if ($res_json['success']) {
                $this->warn("set buy price          : ".$this->your_buy);
                $this->warn("set buy nominal        : ".$res_json['return']['remain_rp']);
                $this->cancel_buy_all = false;
            } else {
                $this->error("ERR           : ".$response);

            }
            
        } else { 
            
        }

    }

    public function set_sell()
    {
        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($res_json);

        if ($this->ready_sell && $this->balance_coin > 0) {
            $this->your_sell        = ($this->price_sell- 1);

            $data = [
                'method' => 'trade',
                'timestamp' => '1578304294000',
                'recvWindow' => '1578303937000',
                'pair' => $this->coin.'_idr',
                'type' => 'sell',
                'price' => $this->your_sell,
                // 'idr' => $this->balance_idr,
                $this->coin => $this->balance_coin
            ];
            $post_data = http_build_query($data, '', '&');
            $sign = hash_hmac('sha512', $post_data, $this->secretKey);
            
            $headers = ['Key:'.$this->key,'Sign:'.$sign];
            
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_URL => $this->url_private,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true
            ));
            
            $response = curl_exec($curl);
            
            curl_close($curl);
            $res_json = json_decode($response,true);
        
            if ($res_json['success']) {
                $this->warn("set sell price         : ".$this->your_sell);
                
            } else {
                $this->error("ERR           : ".$response);
            
            }
            
        } elseif (!$this->ready_sell && $this->balance_coin > 0) { 
            $this->warn("set sell               : HOLD! ");
        
        }else { }
    }

    public function transHistory(){
        
        unset($prices);
        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($json); 
        unset($maksimal);
        unset($trades);
        unset($count);
        unset($limit);
       
        $data = [
            'method' => 'tradeHistory',
            'timestamp' => '1578304294000',
            'recvWindow' => '1578303937000',
            'pair' => $this->coin.'_idr'
        ];
        

        $post_data = http_build_query($data, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->secretKey);
        $headers = ['Key:'.$this->key,'Sign:'.$sign];
    
        $curl = curl_init();
    
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_URL => $this->url_private,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true
        ));
    
        $response = curl_exec($curl);
     
        $json = json_decode($response,true);
        
        curl_close($curl);
        
        $trades = $json['return']['trades'];
        
        $count = isset($trades) ? count($trades) : 0;
        
        $this->temp_price_history = (empty($this->balance_coin) && empty($this->your_sell) && !empty($this->balance_idr))? 0 : $this->max_buy_history;

        if(!empty($count)){
            
            $limit = ($count <= 4) ? 4 : $this->count_history;
        
            for($i=0; $i<=$limit; $i++){
                if($trades[$i]['type'] == "buy"){
                    $prices[] = $trades[$i]['price']; 
                }
            }

            $prices = (!empty($prices) && empty($this->balance_coin) && empty($this->your_sell) && !empty($this->balance_idr)) ? 0 : $prices;
            $maksimal = empty($prices) ? $this->temp_price_history: max($prices);
            
        }else{
            $maksimal = $this->temp_price_history;
        }
        
        $this->max_buy_history = $maksimal;
    
    }

    public function set_cutloss()
    {
        unset($data);
        unset($headers);
        unset($post_data);
        unset($sign);
        unset($curl);
        unset($response);
        unset($res_json);

        if ($this->ready_sell && $this->balance_coin > 0) {
            $this->your_sell        = ($this->price_buy - 5);

            $data = [
                'method' => 'trade',
                'timestamp' => '1578304294000',
                'recvWindow' => '1578303937000',
                'pair' => $this->coin.'_idr',
                'type' => 'sell',
                'price' => $this->your_sell,
                // 'idr' => $this->balance_idr,
                $this->coin => $this->balance_coin
            ];
            $post_data = http_build_query($data, '', '&');
            $sign = hash_hmac('sha512', $post_data, $this->secretKey);
            
            $headers = ['Key:'.$this->key,'Sign:'.$sign];
            
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_URL => $this->url_private,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true
            ));
            
            $response = curl_exec($curl);
            
            curl_close($curl);
            $res_json = json_decode($response,true);
        
            if ($res_json['success']) {
                $this->warn("set sell price         : ".$this->your_sell);
                
            } else {
                $this->error("ERR           : ".$response);
            
            }
            
        } elseif (!$this->ready_sell && $this->balance_coin > 0) { 
            $this->warn("set sell           : HOLD! ");
        
        }else { }
    }

    function send_report()
    {
                    
        $curl = curl_init();
        $data = http_build_query([
                    "JSON_RAW"      => [
                        "balance_idr" => number_format($this->balance_idr) ,
                        $this->coin." price/diff"   => number_format($this->price_buy)."/".$this->difference,
                        "balance_coin" => intval($this->balance_coin) ."|".number_format(($this->balance_hold_coin +$this->balance_coin ) *$this->price_buy) ,
                    ],
                    "ID_TABLE"      => $this->ID_TABLE,
                ]);

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config['url_server']."api/send_report",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => array(
            "authorization: YVA0eGVwZTh5RzRuTUgyVEdCMFlETXhxWXMweWh4Mm1NU2tCYUhsOFl4TmtqZDZjdkFsNEJldXZOWGFuZDczdg",
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            // $this->Loging("cron_auto_replay_error" , $err);
            echo "cURL Error #:" . $err;
        } else {
            // $this->Loging("cron_auto_replay_success" , ['response' => $response]);
            echo $response;
        }
    }
    
    public function warn($text)
    {
        echo  "\e[33m".$text."\e[0m\n";
    }

    public function info($text)
    {
        echo  "\e[32m".$text."\e[0m\n";
    }

    public function error($text)
    {
        echo  "\e[31m".$text."\e[0m\n";

    }

    public function log($text)
    {
        echo "\e[21m".$text;
    }
}


if (isset($argc)) {
    $val = getopt(null, ["id:"]);


    if (empty($val['id'])) {
        echo "\nrun :\nphp scalping.php --id=1\n";die;
    }

    $scalping = new Scalping();
    $scalping->config = $config;
    $scalping->ID_TABLE = $val['id'];
    $scalping->init();

}
die;